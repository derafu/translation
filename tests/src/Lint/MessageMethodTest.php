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
use Derafu\TestsTranslation\Lint\Fixture\FixtureMessageUser;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\Lint\MessageMethod;
use Derafu\Translation\Lint\MessageReference;
use Derafu\Translation\Lint\MessageReferenceScanner;
use Derafu\Translation\Lint\MessageReferenceVisitor;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

/**
 * Some packages build their messages with a method that receives the id
 * (`$this->trans('Send')`), and inside it the id is a variable. The calls are
 * where the messages are, so the scanner can be told which methods receive an
 * id, by their class (the full name), their method, where the id is and which
 * domain they use.
 *
 * It knows which calls are of those methods when it knows the class of what is
 * called: `$this`, `self`, `static`, `parent` and the name of a class (and so the
 * subclasses). A call on something that it can not know the class of is reported
 * as a message that can not be checked, never ignored.
 */
#[CoversClass(MessageMethod::class)]
#[CoversClass(MessageReferenceScanner::class)]
#[CoversClass(MessageReferenceVisitor::class)]
#[CoversClass(MessageReference::class)]
#[UsesClass(TranslatableMessage::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
final class MessageMethodTest extends TestCase
{
    /**
     * @return list<MessageMethod>
     */
    private function methods(): array
    {
        return [
            new MessageMethod(FixtureMessageMethods::class, 'trans', domain: 'fixture', domainArgument: 2),
            new MessageMethod(FixtureMessageMethods::class, 'translate', domain: 'fixture'),
            new MessageMethod(FixtureMessageMethods::class, 'withDomain', id: 1, domainArgument: 0),
        ];
    }

    /**
     * @return list<MessageReference>
     */
    private function scanUser(): array
    {
        return (new MessageReferenceScanner($this->methods()))
            ->scanFile(__DIR__ . '/Fixture/FixtureMessageUser.php')
        ;
    }

    /**
     * @param list<MessageReference> $references
     * @return array<string, string|null>
     */
    private function domainsById(array $references): array
    {
        $domains = [];
        foreach ($references as $reference) {
            if (!$reference->isDynamic()) {
                $domains[(string) $reference->id] = $reference->domain;
            }
        }

        return $domains;
    }

    public function testTheCallsOfThoseMethodsAreMessages(): void
    {
        $domains = $this->domainsById($this->scanUser());

        $this->assertSame('fixture', $domains['Send']);
        $this->assertSame('fixture', $domains['Static with self']);
        $this->assertSame('fixture', $domains['Static with static']);
        $this->assertSame('fixture', $domains['Static with parent']);
        $this->assertSame('fixture', $domains['Static with the class']);
    }

    public function testTheDomainCanComeInTheCall(): void
    {
        $domains = $this->domainsById($this->scanUser());

        // By position, by name, and as the first argument of another method.
        $this->assertSame('custom', $domains['Name']);
        $this->assertSame('named', $domains['Named arguments']);
        $this->assertSame('chosen', $domains['With the domain first']);
    }

    public function testEachBranchOfATernaryIsAMessageAndAnEmptyOneIsNot(): void
    {
        $domains = $this->domainsById($this->scanUser());

        $this->assertArrayHasKey('First.', $domains);
        $this->assertArrayHasKey('Second.', $domains);
        $this->assertArrayNotHasKey('', $domains);
    }

    public function testAnIdThatIsNotALiteralAndAReceiverThatIsNotKnownAreReported(): void
    {
        $dynamic = array_values(array_filter(
            $this->scanUser(),
            fn (MessageReference $reference) => $reference->isDynamic()
        ));

        // `$this->trans($variable)` and `$other->trans(...)`.
        $this->assertCount(2, $dynamic);
        $this->assertSame(FixtureMessageMethods::class, $dynamic[0]->class);
        $this->assertSame(FixtureMessageMethods::class, $dynamic[1]->class);
    }

    public function testTheExpressionOfAReceiverThatIsNotKnownIsTheCall(): void
    {
        $dynamic = array_values(array_filter(
            $this->scanUser(),
            fn (MessageReference $reference) => $reference->isDynamic()
        ));

        $this->assertSame('$this->trans($variable)', $dynamic[0]->expression);
        $this->assertSame("\$other->trans('Receiver that is not known')", $dynamic[1]->expression);
        $this->assertSame(FixtureMessageUser::class . '::run', $dynamic[1]->function);
    }

    public function testACallOfAnotherClassWithTheSameNameIsNotAMessage(): void
    {
        $this->assertArrayNotHasKey('Class that is not one of them', $this->domainsById($this->scanUser()));
    }

    /**
     * Inside the method the id is a variable that is passed on: it is the message
     * of whoever calls, not one that is not known. A message of its own counts.
     */
    public function testTheIdThatTheMethodPassesOnIsNotAMessage(): void
    {
        $references = (new MessageReferenceScanner($this->methods()))
            ->scanFile(__DIR__ . '/Fixture/FixtureMessageMethods.php')
        ;

        $this->assertSame(
            [['Own message of the class.', 'fixture']],
            array_map(fn (MessageReference $r) => [$r->id, $r->domain], $references)
        );
    }

    public function testWithoutTellingWhichMethodsAreMessagesTheirIdsAreNotSeen(): void
    {
        $references = (new MessageReferenceScanner())
            ->scanFile(__DIR__ . '/Fixture/FixtureMessageUser.php')
        ;

        $this->assertSame([], $references);
    }

    public function testTheDomainIsTheDefaultOneWhenNothingSaysOtherwise(): void
    {
        $file = sys_get_temp_dir() . '/translation-method-' . uniqid('', true) . '.php';
        file_put_contents($file, "<?php\n\\Derafu\\TestsTranslation\\Lint\\Fixture\\FixtureMessageMethods::translate('Default domain.');\n");

        try {
            $references = (new MessageReferenceScanner([
                new MessageMethod(FixtureMessageMethods::class, 'translate'),
            ]))->scanFile($file);
        } finally {
            unlink($file);
        }

        $this->assertSame([['Default domain.', 'messages']], array_map(fn (MessageReference $r) => [$r->id, $r->domain], $references));
    }

    public function testTheClassMustExist(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        new MessageReferenceScanner([new MessageMethod('Not\\A\\Class', 'trans')]);
    }

    public function testTheMethodMustExist(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        new MessageReferenceScanner([new MessageMethod(FixtureMessageMethods::class, 'missing')]);
    }

    public function testTheArgumentThatHasTheIdMustExist(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        new MessageReferenceScanner([new MessageMethod(FixtureMessageMethods::class, 'trans', id: 'missing')]);
    }

    public function testTheArgumentThatHasTheDomainMustExist(): void
    {
        $this->expectException(TranslatableInvalidArgumentException::class);

        new MessageReferenceScanner([new MessageMethod(FixtureMessageMethods::class, 'trans', domainArgument: 9)]);
    }
}
