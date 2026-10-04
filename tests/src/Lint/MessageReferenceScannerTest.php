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

use Derafu\Translation\Lint\MessageReference;
use Derafu\Translation\Lint\MessageReferenceScanner;
use Derafu\Translation\Lint\MessageReferenceVisitor;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use InvalidArgumentException;
use PhpParser\Error as ParseError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

/**
 * Finds the messages that the code builds to be translated: the ones given to
 * a translatable exception or to a `TranslatableMessage`.
 *
 * It reads the code (nothing runs), so a comment or a string that only looks
 * like a `new` is never taken for one. The classes are told apart by what they
 * are (a throwable that is translatable), not by their name.
 *
 * It only finds references. Whether each one has an entry in a catalogue is for
 * whoever uses it to decide.
 */
#[CoversClass(MessageReferenceScanner::class)]
#[CoversClass(MessageReference::class)]
#[CoversClass(MessageReferenceVisitor::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
#[UsesClass(TranslatableMessage::class)]
final class MessageReferenceScannerTest extends TestCase
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

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/translation-lint-' . uniqid('', true);
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }

    /**
     * Scans the body of a function written in a temporary file.
     *
     * @return list<MessageReference>
     */
    private function scan(string $body): array
    {
        $file = $this->directory() . '/code.php';
        file_put_contents($file, <<<PHP
            <?php

            namespace Scanned;

            use Derafu\\Translation\\Exception\\Core\\TranslatableException;
            use Derafu\\Translation\\Exception\\Logic\\TranslatableInvalidArgumentException as InvalidArgumentException;
            use Derafu\\Translation\\TranslatableMessage;
            use Derafu\\TestsTranslation\\Lint\\Fixture\\FixtureDefaultMessageException;
            use Derafu\\TestsTranslation\\Lint\\Fixture\\FixtureDomainException;
            use Derafu\\TestsTranslation\\Lint\\Fixture\\FixtureParentCallException;
            use RuntimeException;
            use stdClass;

            function probe(): void
            {
                {$body}
            }
            PHP);

        return (new MessageReferenceScanner())->scanFile($file);
    }

    /**
     * @param list<MessageReference> $references
     * @return list<array{0: string|null, 1: string|null}>
     */
    private function idsAndDomains(array $references): array
    {
        return array_map(fn (MessageReference $r) => [$r->id, $r->domain], $references);
    }

    public function testItFindsTheMessageOfATranslatableException(): void
    {
        $references = $this->scan("throw new TranslatableException('Something failed.');");

        $this->assertCount(1, $references);
        $this->assertSame('Something failed.', $references[0]->id);
        $this->assertSame('errors', $references[0]->domain);
        $this->assertSame(\Derafu\Translation\Exception\Core\TranslatableException::class, $references[0]->class);
        $this->assertFalse($references[0]->isDynamic());
    }

    public function testItReportsTheFileAndTheLine(): void
    {
        $references = $this->scan("\$a = 1;\n    throw new TranslatableException('Line.');");

        $this->assertCount(1, $references);
        $this->assertSame(17, $references[0]->line);
        $this->assertStringEndsWith('/code.php', $references[0]->file);
    }

    public function testItTakesTheMessageFromTheFirstElementOfAnArray(): void
    {
        $references = $this->scan("throw new TranslatableException(['Hello {name}.', 'name' => \$name]);");

        $this->assertSame([['Hello {name}.', 'errors']], $this->idsAndDomains($references));
    }

    public function testItFindsTheClassesThatAreImportedWithAnAlias(): void
    {
        $references = $this->scan("throw new InvalidArgumentException('Invalid.');");

        $this->assertSame([['Invalid.', 'errors']], $this->idsAndDomains($references));
    }

    public function testItTakesTheDomainOfTheClass(): void
    {
        $references = $this->scan("throw new FixtureDomainException('Own domain.');");

        $this->assertSame([['Own domain.', 'fixture']], $this->idsAndDomains($references));
    }

    public function testItTakesTheMessageFromANamedArgument(): void
    {
        $references = $this->scan("throw new TranslatableException(code: 4, message: 'Named.');");

        $this->assertSame([['Named.', 'errors']], $this->idsAndDomains($references));
    }

    public function testItIgnoresThrowablesThatAreNotTranslatable(): void
    {
        $references = $this->scan(
            "throw new RuntimeException('Native.');\n"
            . "throw new \\InvalidArgumentException('Also native.');"
        );

        $this->assertSame([], $references);
    }

    /**
     * A class with a constructor of its own (`new RouteNotFoundException($uri)`)
     * does not take the message as an argument: it builds it, and that is found
     * in the class. What it is given is not a message, so it is not dynamic.
     */
    public function testWhatIsGivenToAConstructorThatDoesNotTakeAMessageIsNotAMessage(): void
    {
        $this->assertSame([], $this->scan('throw new FixtureParentCallException($uri);'));
    }

    public function testItTakesTheMessageFromTheParameterThatIsCalledMessage(): void
    {
        $references = $this->scan(
            "throw new FixtureDefaultMessageException('Given.', 3);\n"
            . 'throw new FixtureDefaultMessageException(code: 3);'
        );

        // The second one uses the default of the class, found in the class.
        $this->assertSame([['Given.', 'errors']], $this->idsAndDomains($references));
    }

    public function testItIgnoresClassesThatFailWhenTheyAreLoaded(): void
    {
        $loader = static function (string $class): void {
            if ($class === 'Scanned\\Unloadable') {
                throw new \Error('Interface "Missing\\Dependency" not found');
            }
        };
        spl_autoload_register($loader);

        try {
            $references = $this->scan("throw new Unloadable('Optional dependency.');");
        } finally {
            spl_autoload_unregister($loader);
        }

        $this->assertSame([], $references);
    }

    public function testItIgnoresClassesThatAreNotThrowables(): void
    {
        $this->assertSame([], $this->scan("\$a = new stdClass('x'); \$b = new \\ArrayObject(['y']);"));
    }

    public function testItIgnoresClassesThatCanNotBeLoaded(): void
    {
        $this->assertSame([], $this->scan("throw new \\Not\\Installed\\SomeException('Unknown.');"));
    }

    public function testItIgnoresWhatOnlyLooksLikeANew(): void
    {
        $references = $this->scan(
            "// throw new TranslatableException('In a comment.');\n"
            . "\$text = \"new TranslatableException('In a string.')\";\n"
            . "/* new TranslatableException('In a block comment.') */"
        );

        $this->assertSame([], $references);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dynamicMessagesProvider(): array
    {
        return [
            'variable' => ['throw new TranslatableException($message);'],
            'concatenation' => ["throw new TranslatableException('Failed: ' . \$reason);"],
            'sprintf' => ["throw new TranslatableException(sprintf('Failed: %s', \$reason));"],
            'interpolation' => ['throw new TranslatableException("Failed: {$reason}");'],
            'constant' => ['throw new TranslatableException(self::MESSAGE);'],
            'array with a variable' => ["throw new TranslatableException([\$message, 'a' => 1]);"],
            'empty array' => ['throw new TranslatableException([]);'],
            'spread' => ['throw new TranslatableException(...$arguments);'],
        ];
    }

    #[DataProvider('dynamicMessagesProvider')]
    public function testAMessageThatIsNotALiteralIsReportedAsDynamic(string $code): void
    {
        $references = $this->scan($code);

        $this->assertCount(1, $references);
        $this->assertNull($references[0]->id);
        $this->assertTrue($references[0]->isDynamic());
    }

    public function testATranslatableMessageIsFoundWithItsDomain(): void
    {
        $references = $this->scan(
            "new TranslatableMessage('One.');\n"
            . "new TranslatableMessage('Two.', ['a' => 1], 'custom');\n"
            . "new TranslatableMessage(message: 'Three.', domain: 'named');"
        );

        $this->assertSame(
            [['One.', 'messages'], ['Two.', 'custom'], ['Three.', 'named']],
            $this->idsAndDomains($references)
        );
        $this->assertSame(TranslatableMessage::class, $references[0]->class);
    }

    public function testADomainThatIsNotALiteralIsReportedAsDynamic(): void
    {
        $references = $this->scan("new TranslatableMessage('Message.', [], \$domain);");

        $this->assertCount(1, $references);
        $this->assertSame('Message.', $references[0]->id);
        $this->assertNull($references[0]->domain);
        $this->assertTrue($references[0]->isDynamic());
    }

    /**
     * A spread can carry the domain (or any other argument), so it is not
     * known by reading.
     */
    public function testAnArgumentThatIsSpreadMakesTheDomainDynamic(): void
    {
        $references = $this->scan("new TranslatableMessage('Message.', ...\$rest);");

        $this->assertCount(1, $references);
        $this->assertSame('Message.', $references[0]->id);
        $this->assertNull($references[0]->domain);
    }

    public function testAMessageThatIsAlreadyATranslatableMessageIsFoundOnce(): void
    {
        $references = $this->scan(
            "throw new TranslatableException(new TranslatableMessage('Nested.', [], 'custom'));"
        );

        $this->assertSame([['Nested.', 'custom']], $this->idsAndDomains($references));
    }

    public function testItFindsTheReferencesWhereverANewCanBe(): void
    {
        $references = $this->scan(
            "\$f = function () { throw new TranslatableException('In a closure.'); };\n"
            . "\$g = fn () => new TranslatableException('In an arrow function.');\n"
            . "foo(new TranslatableException('As an argument.'));\n"
            . "return [new TranslatableException('In an array.')];"
        );

        $ids = array_map(fn (MessageReference $r) => $r->id, $references);
        sort($ids);

        $this->assertSame(
            ['As an argument.', 'In a closure.', 'In an array.', 'In an arrow function.'],
            $ids
        );
    }

    public function testItFindsAnAnonymousClassThatExtendsATranslatableOne(): void
    {
        $references = $this->scan("throw new class ('Anonymous.') extends TranslatableException {};");

        $this->assertSame([['Anonymous.', 'errors']], $this->idsAndDomains($references));
    }

    private function scanFixtures(): array
    {
        return (new MessageReferenceScanner())->scanDirectory(__DIR__ . '/Fixture');
    }

    public function testItResolvesSelfAndStaticToTheClassThatIsBeingRead(): void
    {
        $domains = [];
        $dynamic = 0;
        foreach ($this->scanFixtures() as $reference) {
            if (!str_ends_with($reference->file, 'FixtureDomainException.php')) {
                continue;
            }
            if ($reference->id === null) {
                $dynamic++;
                $this->assertSame('fixture', $reference->domain);

                continue;
            }
            $domains[$reference->id] = $reference->domain;
        }

        $this->assertSame('fixture', $domains['Thing {name} failed.']);
        $this->assertSame('fixture', $domains['Made with self.']);
        // `new static($message)`, whose message is only known when it runs.
        $this->assertSame(1, $dynamic);
    }

    public function testItFindsTheMessageOfAConstructorThatCallsParent(): void
    {
        $references = array_filter(
            $this->scanFixtures(),
            fn (MessageReference $r) => str_ends_with($r->file, 'FixtureParentCallException.php')
        );

        $this->assertSame([['No route for {uri}.', 'errors']], $this->idsAndDomains(array_values($references)));
    }

    public function testAMessageThatIsOnlyPassedOnByAConstructorIsNotAReference(): void
    {
        $references = array_values(array_filter(
            $this->scanFixtures(),
            fn (MessageReference $r) => str_ends_with($r->file, 'FixtureDefaultMessageException.php')
        ));

        // The default of `$message` is a message; `parent::__construct($message)`
        // is the same message passed on, not a new dynamic one.
        $this->assertSame([['Default message.', 'errors']], $this->idsAndDomains($references));
    }

    public function testAnotherVariableInsideAConstructorIsStillDynamic(): void
    {
        $references = $this->scan(<<<'PHP'
            $x = new class () {
                public function __construct()
                {
                }
            };
            throw new TranslatableException($other);
            PHP);

        $this->assertCount(1, $references);
        $this->assertTrue($references[0]->isDynamic());
    }

    public function testItScansEveryPhpFileOfADirectoryInOrder(): void
    {
        $directory = $this->directory();
        mkdir($directory . '/sub');
        file_put_contents($directory . '/b.php', "<?php\nnew \\Derafu\\Translation\\Exception\\Core\\TranslatableException('In b.');");
        file_put_contents($directory . '/a.php', "<?php\nnew \\Derafu\\Translation\\Exception\\Core\\TranslatableException('In a.');");
        file_put_contents($directory . '/sub/c.php', "<?php\nnew \\Derafu\\Translation\\Exception\\Core\\TranslatableException('In c.');");
        file_put_contents($directory . '/notes.txt', "new TranslatableException('Not php.')");

        $references = (new MessageReferenceScanner())->scanDirectory($directory);

        $this->assertSame(
            ['In a.', 'In b.', 'In c.'],
            array_map(fn (MessageReference $r) => $r->id, $references)
        );
    }

    public function testItFailsWhenTheDirectoryDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MessageReferenceScanner())->scanDirectory('/does/not/exist');
    }

    public function testItFailsWhenTheFileDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MessageReferenceScanner())->scanFile('/does/not/exist.php');
    }

    /**
     * A file that can not be read would look like one with no messages.
     */
    public function testASyntaxErrorIsNotHidden(): void
    {
        $file = $this->directory() . '/broken.php';
        file_put_contents($file, '<?php new TranslatableException(;');

        $this->expectException(ParseError::class);

        (new MessageReferenceScanner())->scanFile($file);
    }
}
