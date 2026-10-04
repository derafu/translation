<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Lint;

use Derafu\TestsTranslation\Lint\Fixture\FixtureMessageMethods;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\Lint\MessageMethod;
use Derafu\Translation\Lint\MessageReference;
use Derafu\Translation\Lint\MessageReferenceScanner;
use Derafu\Translation\Lint\MessageReferenceVisitor;
use Derafu\Translation\Lint\ThrowReference;
use Derafu\Translation\Lint\ThrowReferenceScanner;
use Derafu\Translation\Lint\ThrowReferenceVisitor;
use Derafu\Translation\Lint\TranslationAudit;
use Derafu\Translation\Lint\TranslationAuditReport;
use Derafu\Translation\SimpleTranslationResourceProvider;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use Derafu\Translation\TranslationResourceRegistrar;
use Derafu\Translation\TranslatorFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Checks everything that a package needs to be translated, in one call, so the
 * test of each package is a few lines and all the logic is here.
 *
 * It only finds facts, in a report: which messages can not be checked, which have
 * no translation, which entries of the catalogues no message of the code uses,
 * and which exceptions are not translatable. What is a problem is for the test of
 * each package to say, by asserting that the lists it cares about are empty.
 */
#[CoversClass(TranslationAudit::class)]
#[CoversClass(TranslationAuditReport::class)]
#[UsesClass(MessageMethod::class)]
#[UsesClass(MessageReference::class)]
#[UsesClass(MessageReferenceVisitor::class)]
#[UsesClass(ThrowReference::class)]
#[UsesClass(ThrowReferenceVisitor::class)]
#[UsesClass(MessageReferenceScanner::class)]
#[UsesClass(ThrowReferenceScanner::class)]
#[UsesClass(TranslationResourceRegistrar::class)]
#[UsesClass(TranslatorFactory::class)]
#[UsesClass(SimpleTranslationResourceProvider::class)]
#[UsesClass(TranslatableMessage::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
final class TranslationAuditTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->remove($directory);
        }
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }

        rmdir($path);
    }

    /**
     * Makes a package: a `src` directory with PHP files and a `translations`
     * directory with catalogues.
     *
     * @param array<string, string> $files File name => the PHP that goes in it,
     * after the header.
     * @param array<string, array<string, string>> $catalogues File name => the
     * entries of the catalogue.
     * @return array{string, SimpleTranslationResourceProvider}
     */
    private function package(array $files, array $catalogues = []): array
    {
        $root = sys_get_temp_dir() . '/translation-audit-' . uniqid('', true);
        mkdir($root . '/src', 0777, true);
        mkdir($root . '/translations');
        $this->directories[] = $root;

        foreach ($files as $name => $code) {
            file_put_contents($root . '/src/' . $name, "<?php\n\nnamespace Audited;\n\n"
                . "use Derafu\\Translation\\Exception\\Core\\TranslatableException;\n"
                . "use Derafu\\Translation\\TranslatableMessage;\n\n" . $code);
        }
        foreach ($catalogues as $name => $entries) {
            file_put_contents($root . '/translations/' . $name, '<?php return ' . var_export($entries, true) . ';');
        }

        return [$root . '/src', new SimpleTranslationResourceProvider([$root . '/translations'])];
    }

    private function audit(string $source, SimpleTranslationResourceProvider $provider, array $allowed = []): TranslationAuditReport
    {
        return (new TranslationAudit())->audit($source, $provider, 'es', $allowed);
    }

    public function testAPackageThatIsTranslatedHasNoFindings(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(): void { throw new TranslatableException('Failed {name}.'); }"],
            ['errors+intl-icu.es.php' => ['Failed {name}.' => 'Falló {name}.']]
        );

        $report = $this->audit($source, $provider);

        $this->assertFalse($report->nothingFound);
        $this->assertSame([], $report->dynamicMessages);
        $this->assertSame([], $report->missingTranslations);
        $this->assertSame([], $report->notUsedBySources);
        $this->assertSame([], $report->notTranslatable);
    }

    public function testAMessageThatIsNotALiteralIsAFinding(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => 'function a($m): void { throw new TranslatableException($m); }'],
            ['errors+intl-icu.es.php' => []]
        );

        $report = $this->audit($source, $provider);

        $this->assertCount(1, $report->dynamicMessages);
        $this->assertTrue($report->dynamicMessages[0]->isDynamic());
        $this->assertSame([], $report->missingTranslations);
    }

    public function testAMessageWithoutAnEntryIsAFinding(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(): void { throw new TranslatableException('No entry.'); }"],
            ['errors+intl-icu.es.php' => []]
        );

        $report = $this->audit($source, $provider);

        $this->assertCount(1, $report->missingTranslations);
        $this->assertSame('No entry.', $report->missingTranslations[0]->id);
        $this->assertSame('errors', $report->missingTranslations[0]->domain);
    }

    /**
     * An entry that no message of the code uses is a fact about the code that
     * was read: whether it is a problem is for the test to say.
     */
    public function testAnEntryThatNoMessageOfTheCodeUsesIsAFinding(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(): void { throw new TranslatableException('Used.'); }"],
            ['errors+intl-icu.es.php' => ['Used.' => 'Usado.', 'Not used.' => 'No usado.']]
        );

        $report = $this->audit($source, $provider);

        $this->assertSame([['domain' => 'errors', 'id' => 'Not used.']], $report->notUsedBySources);
    }

    /**
     * Every domain is checked, not only `errors`.
     */
    public function testEveryDomainOfTheCataloguesIsChecked(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(): void { new TranslatableMessage('Hello.', [], 'custom'); }"],
            [
                'custom+intl-icu.es.php' => ['Hello.' => 'Hola.', 'Unused in custom.' => 'No usado.'],
                'other+intl-icu.es.php' => ['Unused in other.' => 'No usado.'],
            ]
        );

        $report = $this->audit($source, $provider);

        $this->assertSame(
            [['domain' => 'custom', 'id' => 'Unused in custom.'], ['domain' => 'other', 'id' => 'Unused in other.']],
            $report->notUsedBySources
        );
        $this->assertSame([], $report->missingTranslations);
    }

    public function testAMessageOfAnotherDomainDoesNotUseTheEntryOfThisOne(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(): void { new TranslatableMessage('Hello.', [], 'custom'); }"],
            ['errors+intl-icu.es.php' => ['Hello.' => 'Hola.']]
        );

        $report = $this->audit($source, $provider);

        $this->assertCount(1, $report->missingTranslations);
        $this->assertCount(1, $report->notUsedBySources);
    }

    public function testAnExceptionThatIsNotTranslatableIsAFinding(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(): void { throw new \\RuntimeException('Native.'); }"],
            ['errors+intl-icu.es.php' => []]
        );

        $report = $this->audit($source, $provider);

        $this->assertCount(1, $report->notTranslatable);
        $this->assertSame(RuntimeException::class, $report->notTranslatable[0]->class);
        // A package that has only native exceptions has no messages either.
        $this->assertTrue($report->nothingFound);
    }

    /**
     * An exception of another package that must be thrown as it is can be
     * allowed, class by class: it is an explicit decision of whoever uses this.
     */
    public function testAnAllowedExceptionIsNotAFinding(): void
    {
        [$source, $provider] = $this->package(
            [
                'a.php' => "function a(): void { throw new \\RuntimeException('Native.'); }\n"
                    . "function b(): void { throw new TranslatableException('Translated.'); }",
            ],
            ['errors+intl-icu.es.php' => ['Translated.' => 'Traducido.']]
        );

        $this->assertSame([], $this->audit($source, $provider, [RuntimeException::class])->notTranslatable);
        $this->assertCount(1, $this->audit($source, $provider, [InvalidArgumentException::class])->notTranslatable);
        $this->assertCount(1, $this->audit($source, $provider)->notTranslatable);
    }

    public function testFindingNothingIsAFact(): void
    {
        [$source, $provider] = $this->package(['a.php' => 'function a(): void {}'], ['errors+intl-icu.es.php' => []]);

        $this->assertTrue($this->audit($source, $provider)->nothingFound);
    }

    public function testTheLocaleIsTheOneOfTheCatalogue(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(): void { throw new TranslatableException('Failed.'); }"],
            ['errors+intl-icu.fr.php' => ['Failed.' => 'Échec.']]
        );

        $this->assertCount(1, $this->audit($source, $provider)->missingTranslations);
        $this->assertSame([], (new TranslationAudit())->audit($source, $provider, 'fr')->missingTranslations);
    }

    public function testSeveralProvidersCanBeGiven(): void
    {
        [$source, $first] = $this->package(
            ['a.php' => "function a(): void { throw new TranslatableException('Failed.'); }"],
            ['errors+intl-icu.es.php' => ['Failed.' => 'Falló.']]
        );
        [, $second] = $this->package([], ['other+intl-icu.es.php' => ['Other.' => 'Otro.']]);

        $report = (new TranslationAudit())->audit($source, [$first, $second]);

        $this->assertSame([['domain' => 'other', 'id' => 'Other.']], $report->notUsedBySources);
    }

    /**
     * The calls of the methods that receive the id of a message are messages,
     * checked against the catalogue of their domain.
     */
    public function testTheMessagesOfTheMethodsThatReceiveAnIdAreChecked(): void
    {
        $method = new MessageMethod(FixtureMessageMethods::class, 'translate', domain: 'fixture');
        $code = "function a(): void { \\Derafu\\TestsTranslation\\Lint\\Fixture\\FixtureMessageMethods::translate('Send.'); }";

        [$source, $provider] = $this->package(['a.php' => $code], ['fixture+intl-icu.es.php' => []]);
        $report = (new TranslationAudit())->audit($source, $provider, 'es', [], [$method]);

        $this->assertCount(1, $report->missingTranslations);
        $this->assertSame('Send.', $report->missingTranslations[0]->id);
        $this->assertSame('fixture', $report->missingTranslations[0]->domain);

        [$source, $provider] = $this->package(['a.php' => $code], ['fixture+intl-icu.es.php' => ['Send.' => 'Enviar.']]);
        $report = (new TranslationAudit())->audit($source, $provider, 'es', [], [$method]);
        $this->assertSame([], $report->missingTranslations);
        $this->assertSame([], $report->notUsedBySources);
    }

    public function testWithoutTheMethodsTheirMessagesAreNotSeen(): void
    {
        $code = "function a(): void { \\Derafu\\TestsTranslation\\Lint\\Fixture\\FixtureMessageMethods::translate('Send.'); }";
        [$source, $provider] = $this->package(['a.php' => $code], ['fixture+intl-icu.es.php' => ['Send.' => 'Enviar.']]);

        $report = $this->audit($source, $provider);

        $this->assertTrue($report->nothingFound);
        $this->assertSame([['domain' => 'fixture', 'id' => 'Send.']], $report->notUsedBySources);
    }

    public function testTheDirectoryMustExist(): void
    {
        [, $provider] = $this->package([]);

        $this->expectException(TranslatableInvalidArgumentException::class);

        $this->audit('/does/not/exist', $provider);
    }

    /**
     * `describe()` only turns findings into lines for the message of a failed
     * test: it says where they are and what they are, and it does not say whether
     * they are a problem.
     */
    public function testDescribeSaysWhereAMessageIsRelativeToTheAuditedDirectory(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(\$m): void { throw new TranslatableException('No entry.'); throw new TranslatableException(\$m); }"],
            ['errors+intl-icu.es.php' => []]
        );

        $report = $this->audit($source, $provider);

        $this->assertSame(['a.php:8 "No entry." [errors]'], $report->describe($report->missingTranslations));
        $this->assertSame(
            ['a.php:8 Audited\\a: new \\Derafu\\Translation\\Exception\\Core\\TranslatableException($m)'],
            $report->describe($report->dynamicMessages)
        );
    }

    public function testDescribeSaysTheEntriesThatNoMessageUses(): void
    {
        [$source, $provider] = $this->package(
            ['a.php' => "function a(): void { throw new TranslatableException('Used.'); }"],
            ['errors+intl-icu.es.php' => ['Used.' => 'Usado.', 'Not used.' => 'No usado.']]
        );

        $report = $this->audit($source, $provider);

        $this->assertSame(['"Not used." [errors]'], $report->describe($report->notUsedBySources));
    }

    public function testDescribeSaysTheExceptionsAndWhetherTheyCouldBeLoaded(): void
    {
        [$source, $provider] = $this->package(
            [
                'a.php' => "function a(): void { throw new \\RuntimeException('Native.'); }\n"
                    . "function b(): void { throw new \\Not\\Installed\\Failure('Unknown.'); }\n"
                    . 'class Child extends \\RuntimeException {}',
            ],
            ['errors+intl-icu.es.php' => []]
        );

        $report = $this->audit($source, $provider);

        $this->assertSame(
            [
                'a.php:8 RuntimeException',
                'a.php:9 Not\\Installed\\Failure (it could not be loaded)',
                'a.php:10 Audited\\Child extends RuntimeException',
            ],
            $report->describe($report->notTranslatable)
        );
    }

    public function testDescribeOfNothingIsNothing(): void
    {
        [$source, $provider] = $this->package(['a.php' => 'function a(): void {}'], ['errors+intl-icu.es.php' => []]);

        $this->assertSame([], $this->audit($source, $provider)->describe([]));
    }
}
