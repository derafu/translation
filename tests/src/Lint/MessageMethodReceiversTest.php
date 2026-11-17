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
use Derafu\TestsTranslation\Lint\Fixture\FixtureTypedReceivers;
use Derafu\Translation\Lint\MessageMethod;
use Derafu\Translation\Lint\MessageReference;
use Derafu\Translation\Lint\MessageReferenceScanner;
use Derafu\Translation\Lint\MessageReferenceVisitor;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use Error;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

/**
 * A call of a method that receives an id is known by the class of what it is
 * called on. Besides `$this`, `self`, `static`, `parent` and the name of a class,
 * the scanner knows the class of a property of `$this` and of a variable that is
 * a parameter of the function, from the type that they are declared with.
 *
 * What is declared is not always what there is: a type that is wider than the
 * class of the method can be an object of that class, so the call is reported as
 * a message that can not be checked, like what it can not know at all. Only a
 * receiver that is known to be of another class is not a message.
 *
 * The code that is not valid on purpose (a call on a variable without a type) is
 * written here as text, as the code that the scanner reads.
 */
#[CoversClass(MessageMethod::class)]
#[CoversClass(MessageReferenceScanner::class)]
#[CoversClass(MessageReferenceVisitor::class)]
#[CoversClass(MessageReference::class)]
#[UsesClass(TranslatableMessage::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
final class MessageMethodReceiversTest extends TestCase
{
    private const string HEADER = <<<'PHP'
        <?php

        namespace Scanned;

        use Derafu\TestsTranslation\Lint\Fixture\FixtureMessageMethods;
        use Derafu\TestsTranslation\Lint\Fixture\FixtureMessageSource;
        use Derafu\TestsTranslation\Lint\Fixture\FixtureOtherTranslator;
        use Derafu\TestsTranslation\Lint\Fixture\FixtureTypedReceivers;

        PHP;

    /**
     * @return list<MessageMethod>
     */
    private function methods(): array
    {
        return [
            new MessageMethod(FixtureMessageMethods::class, 'trans', domain: 'fixture', domainArgument: 2),
        ];
    }

    /**
     * Scans code written in a temporary file. If it declares classes, they are
     * loaded first, because the scanner knows the type of a property from the
     * class.
     *
     * @param list<MessageMethod>|null $methods The methods that receive an id.
     * @return list<MessageReference>
     */
    private function scan(string $code, bool $declares = false, ?array $methods = null): array
    {
        $file = sys_get_temp_dir() . '/translation-receivers-' . uniqid('', true) . '.php';
        file_put_contents($file, self::HEADER . $code);

        try {
            if ($declares) {
                require $file;
            }

            return (new MessageReferenceScanner($methods ?? $this->methods()))->scanFile($file);
        } finally {
            unlink($file);
        }
    }

    /**
     * @param list<MessageReference> $references
     * @return list<string>
     */
    private function messages(array $references): array
    {
        return array_values(array_map(
            fn (MessageReference $reference) => (string) $reference->id,
            array_filter($references, fn (MessageReference $reference) => !$reference->isDynamic())
        ));
    }

    /**
     * @param list<MessageReference> $references
     * @return list<string>
     */
    private function notChecked(array $references): array
    {
        return array_values(array_map(
            fn (MessageReference $reference) => (string) $reference->expression,
            array_filter($references, fn (MessageReference $reference) => $reference->isDynamic())
        ));
    }

    /**
     * @return list<MessageReference>
     */
    private function scanFixture(string $name): array
    {
        return (new MessageReferenceScanner($this->methods()))->scanFile(__DIR__ . '/Fixture/' . $name . '.php');
    }

    public function testThePropertiesOfThisAreKnownByTheirType(): void
    {
        $references = $this->scanFixture('FixtureTypedReceivers');

        // Promoted, nullable (also with `?->`), union with `null` and of its
        // own class (`self`). The one of another class is not a message.
        $this->assertSame(
            ['Promoted property', 'Nullsafe property', 'Union with null', 'Property of its own class'],
            $this->messages($references)
        );
        $this->assertSame(
            ['$this->wide->trans(\'Property with a type that is wider\')'],
            $this->notChecked($references)
        );
    }

    public function testAPropertyThatIsInheritedIsKnownByItsType(): void
    {
        $references = $this->scanFixture('FixtureTypedReceiversChild');

        $this->assertSame(['Property of the parent'], $this->messages($references));
        $this->assertSame([], $this->notChecked($references));
    }

    public function testTheParametersOfAFunctionAreKnownByTheirType(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(
                FixtureMessageMethods $class,
                ?FixtureMessageMethods $nullable,
                FixtureMessageMethods|null $union,
                FixtureOtherTranslator $other,
            ): void {
                $class->trans('Class');
                $nullable->trans('Nullable');
                $union?->trans('Union with null');
                $other->trans('Another class');
            }
            PHP);

        $this->assertSame(['Class', 'Nullable', 'Union with null'], $this->messages($references));
        $this->assertSame([], $this->notChecked($references));
    }

    public function testTheParametersOfAMethodAreKnownByTheirType(): void
    {
        $references = $this->scan(<<<'PHP'
            final class ReceiverOfItself extends FixtureMessageMethods
            {
                public function run(self $peer): void
                {
                    $peer->trans('Parameter of its own class');
                }
            }
            PHP, declares: true);

        $this->assertSame(['Parameter of its own class'], $this->messages($references));
    }

    public function testAVariableOfAClosureIsKnownWhenItIsAParameterOrItIsTaken(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(FixtureMessageMethods $class, $untyped): void
            {
                $parameter = function (FixtureMessageMethods $inner): void {
                    $inner->trans('Parameter of the closure');
                };
                $taken = function () use ($class): void {
                    $class->trans('Variable taken with use');
                };
                $takenWithoutType = function () use ($untyped): void {
                    $untyped->trans('Variable taken without a type');
                };
                $notTaken = function (): void {
                    $class->trans('Variable that is not taken');
                };
            }
            PHP);

        $this->assertSame(['Parameter of the closure', 'Variable taken with use'], $this->messages($references));
        $this->assertSame(
            [
                '$untyped->trans(\'Variable taken without a type\')',
                '$class->trans(\'Variable that is not taken\')',
            ],
            $this->notChecked($references)
        );
    }

    public function testAnArrowFunctionSeesTheVariablesOfTheFunctionThatHasIt(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(FixtureMessageMethods $class): void
            {
                $outer = fn () => $class->trans('Variable of the function');
                $nested = fn () => fn () => $class->trans('Variable of the function, nested');
                $shadowed = fn (FixtureOtherTranslator $class) => $class->trans('Parameter that hides it');
            }
            PHP);

        $this->assertSame(
            ['Variable of the function', 'Variable of the function, nested'],
            $this->messages($references)
        );
        $this->assertSame([], $this->notChecked($references));
    }

    public function testAVariableOutsideOfAFunctionIsNotKnown(): void
    {
        $references = $this->scan(<<<'PHP'
            $outside->trans('Variable outside of a function');
            $arrow = fn () => $outside->trans('Variable of nobody, in an arrow function');
            PHP);

        $this->assertSame([], $this->messages($references));
        $this->assertSame(
            [
                '$outside->trans(\'Variable outside of a function\')',
                '$outside->trans(\'Variable of nobody, in an arrow function\')',
            ],
            $this->notChecked($references)
        );
    }

    public function testAVariableThatIsNotAParameterIsNotKnown(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(): void
            {
                $assigned = new FixtureMessageMethods();
                $assigned->trans('Variable that is assigned');
            }
            PHP);

        $this->assertSame([], $this->messages($references));
        $this->assertSame(['$assigned->trans(\'Variable that is assigned\')'], $this->notChecked($references));
    }

    public function testATypeThatIsNotOneClassIsNotKnown(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(
                object $object,
                string $text,
                $untyped,
                FixtureMessageMethods|FixtureOtherTranslator $either,
                \Missing\Nowhere $missing,
            ): void {
                $object->trans('Object');
                $text->trans('Text');
                $untyped->trans('Without a type');
                $either->trans('Union of two classes');
                $missing->trans('Class that does not exist');
            }
            PHP);

        $this->assertSame([], $this->messages($references));
        $this->assertCount(5, $this->notChecked($references));
    }

    public function testATypeThatCanNotBeLoadedIsNotKnown(): void
    {
        $loader = static function (string $class): void {
            if ($class === 'Scanned\\Unloadable') {
                throw new Error('Interface "Missing\\Dependency" not found');
            }
        };
        spl_autoload_register($loader);

        try {
            $references = $this->scan(<<<'PHP'
                function probe(Unloadable $receiver): void
                {
                    $receiver->trans('Class that fails when it is loaded');
                }
                PHP);
        } finally {
            spl_autoload_unregister($loader);
        }

        $this->assertSame([], $this->messages($references));
        $this->assertSame(
            ['$receiver->trans(\'Class that fails when it is loaded\')'],
            $this->notChecked($references)
        );
    }

    public function testATypeThatIsWiderThanTheClassOfTheMethodIsNotKnown(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(FixtureMessageSource $source): void
            {
                $source->trans('Interface that the class implements');
            }
            PHP);

        $this->assertSame([], $this->messages($references));
        $this->assertSame(
            ['$source->trans(\'Interface that the class implements\')'],
            $this->notChecked($references)
        );
    }

    public function testACallIsReportedOnceWhenSeveralClassesCouldBeTheOne(): void
    {
        $references = $this->scan(
            <<<'PHP'
                function probe(FixtureMessageSource $source): void
                {
                    $source->trans('Interface that two classes implement');
                }
                PHP,
            methods: [
                new MessageMethod(FixtureMessageMethods::class, 'trans', domain: 'fixture'),
                new MessageMethod(FixtureTypedReceivers::class, 'trans', domain: 'fixture'),
            ]
        );

        $this->assertCount(1, $references);
    }

    public function testACallOnAParentClassByItsNameIsNotAMessageOfTheSubclass(): void
    {
        $references = $this->scan(
            <<<'PHP'
                function probe(): void
                {
                    FixtureTypedReceivers::translate('Subclass by its name');
                    FixtureMessageMethods::translate('Parent by its name');
                }
                PHP,
            methods: [new MessageMethod(FixtureTypedReceivers::class, 'translate', domain: 'fixture')]
        );

        $this->assertSame(['Subclass by its name'], $this->messages($references));
        $this->assertSame([], $this->notChecked($references));
    }

    public function testACallOnThisInAParentClassIsNotAMessageOfTheSubclass(): void
    {
        $references = $this->scan(
            <<<'PHP'
                class ReceiverBase
                {
                    public function trans(string $id): void
                    {
                    }

                    public function run(): void
                    {
                        $this->trans('Call on this, in the parent');
                    }
                }

                final class ReceiverSub extends ReceiverBase
                {
                    public function control(): void
                    {
                        $this->trans('Control, in the subclass');
                    }
                }
                PHP,
            declares: true,
            methods: [new MessageMethod('Scanned\\ReceiverSub', 'trans', domain: 'fixture')]
        );

        // The call of the subclass is the control: it shows that the code is read,
        // so that the one of the parent is not found because it is not one of the
        // class, and not because nothing was read.
        $this->assertSame(['Control, in the subclass'], $this->messages($references));
        $this->assertSame([], $this->notChecked($references));
    }

    public function testAVariableThatIsNamedByAnExpressionIsNotKnown(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(string $name): void
            {
                $$name->trans('Variable variable');
            }
            PHP);

        $this->assertSame([], $this->messages($references));
        $this->assertSame(['${$name}->trans(\'Variable variable\')'], $this->notChecked($references));
    }

    public function testTheNullOfAUnionCanBeAnyOfItsTypes(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(null|FixtureMessageMethods $leading, FixtureMessageMethods|string $text): void
            {
                $leading->trans('Null first');
                $text->trans('Class or text');
            }
            PHP);

        $this->assertSame(['Null first'], $this->messages($references));
        $this->assertSame(['$text->trans(\'Class or text\')'], $this->notChecked($references));
    }

    public function testAnInterfaceOrAnEnumOfAnotherTypeIsNotAMessage(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(\Countable $interface, \Random\IntervalBoundary $enum, FixtureMessageMethods $class): void
            {
                $interface->trans('Interface that is not of the class');
                $enum->trans('Enum');
                $class->trans('Control');
            }
            PHP);

        // The call on the class is the control: it shows that the code is read, so
        // that the others are not messages because their types are not the class,
        // and not because nothing was read.
        $this->assertSame(['Control'], $this->messages($references));
        $this->assertSame([], $this->notChecked($references));
    }

    public function testAMethodThatIsNotOneOfThemOnAKnownReceiverIsNotAMessage(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(FixtureMessageMethods $class): void
            {
                $class->own();
                $class->trans('Control');
            }
            PHP);

        // The call of `trans()` is the control: it shows that the code is read, so
        // that `own()` is not a message because it is not one of the methods, and
        // not because nothing was read.
        $this->assertSame(['Control'], $this->messages($references));
        $this->assertSame([], $this->notChecked($references));
    }

    public function testThePropertiesThatCanNotBeKnownByTheirTypeAreNotKnown(): void
    {
        $references = $this->scan(<<<'PHP'
            final class ReceiverOfProperties extends FixtureMessageMethods
            {
                private $untyped;

                private string $text = '';

                private FixtureMessageMethods|FixtureOtherTranslator|null $either = null;

                public function run(string $name): void
                {
                    $this->untyped->trans('Property without a type');
                    $this->text->trans('Property that is not a class');
                    $this->either->trans('Property that is a union of two classes');
                    $this->missing->trans('Property that does not exist');
                    $this->{$name}->trans('Property that has no name');
                }
            }
            PHP, declares: true);

        $this->assertSame([], $this->messages($references));
        $this->assertCount(5, $this->notChecked($references));
    }

    public function testAPrivatePropertyOfTheParentIsNotKnown(): void
    {
        $references = $this->scan(<<<'PHP'
            class ReceiverWithPrivate extends FixtureMessageMethods
            {
                private FixtureMessageMethods $hidden;
            }

            final class ReceiverOfPrivate extends ReceiverWithPrivate
            {
                public function run(): void
                {
                    $this->hidden->trans('Private property of the parent');
                }
            }
            PHP, declares: true);

        $this->assertSame([], $this->messages($references));
        $this->assertCount(1, $this->notChecked($references));
    }

    public function testAPropertyOfAnAnonymousClassIsNotKnown(): void
    {
        $references = $this->scan(<<<'PHP'
            function probe(): object
            {
                return new class () {
                    private FixtureMessageMethods $messages;

                    public function run(): void
                    {
                        $this->messages->trans('Property of an anonymous class');
                    }
                };
            }
            PHP);

        $this->assertSame([], $this->messages($references));
        $this->assertCount(1, $this->notChecked($references));
    }
}
