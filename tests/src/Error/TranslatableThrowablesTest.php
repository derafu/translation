<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Error;

use ArithmeticError;
use Closure;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Error\TranslatableArithmeticError;
use Derafu\Translation\Error\TranslatableDivisionByZeroError;
use Derafu\Translation\Error\TranslatableError;
use Derafu\Translation\Error\TranslatableTypeError;
use Derafu\Translation\Error\TranslatableValueError;
use Derafu\Translation\Exception\Core\TranslatableJsonException;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use DivisionByZeroError;
use Error;
use Exception;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Throwable;
use TypeError;
use ValueError;

/**
 * The classes that are translatable versions of the native `Error` family and
 * of `JsonException` work like the translatable exceptions: the message can be
 * given with named parameters, it can be translated, it keeps its code and its
 * previous throwable, and it survives being serialized.
 *
 * An `Error` is not an `Exception`: it is not caught by `catch (Exception)`.
 */
#[CoversClass(TranslatableError::class)]
#[CoversClass(TranslatableTypeError::class)]
#[CoversClass(TranslatableValueError::class)]
#[CoversClass(TranslatableArithmeticError::class)]
#[CoversClass(TranslatableDivisionByZeroError::class)]
#[CoversClass(TranslatableJsonException::class)]
#[CoversClass(TranslatableMessage::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
final class TranslatableThrowablesTest extends TestCase
{
    /**
     * @return array<string, array{Closure(mixed ...): (Throwable&TranslatableInterface), class-string, bool}>
     */
    public static function throwablesProvider(): array
    {
        return [
            'Error' => [fn (mixed ...$a) => new TranslatableError(...$a), Error::class, false],
            'TypeError' => [fn (mixed ...$a) => new TranslatableTypeError(...$a), TypeError::class, false],
            'ValueError' => [fn (mixed ...$a) => new TranslatableValueError(...$a), ValueError::class, false],
            'ArithmeticError' => [fn (mixed ...$a) => new TranslatableArithmeticError(...$a), ArithmeticError::class, false],
            'DivisionByZeroError' => [fn (mixed ...$a) => new TranslatableDivisionByZeroError(...$a), DivisionByZeroError::class, false],
            'JsonException' => [fn (mixed ...$a) => new TranslatableJsonException(...$a), JsonException::class, true],
        ];
    }

    /**
     * @return array<string, array{Closure(mixed ...): (Throwable&TranslatableInterface)}>
     */
    public static function factoriesProvider(): array
    {
        return array_map(fn (array $case) => [$case[0]], self::throwablesProvider());
    }

    /**
     * @param Closure(mixed ...): (Throwable&TranslatableInterface) $create
     * @param class-string $native
     */
    #[DataProvider('throwablesProvider')]
    public function testItIsTheNativeClassAndIsTranslatable(Closure $create, string $native, bool $isException): void
    {
        $throwable = $create('Failed.');

        $this->assertInstanceOf($native, $throwable);
        $this->assertInstanceOf(Throwable::class, $throwable);
        $this->assertInstanceOf(TranslatableInterface::class, $throwable);
        // Only the exceptions are caught by `catch (Exception)`.
        $this->assertSame($isException, $throwable instanceof Exception);
    }

    /**
     * @param Closure(mixed ...): (Throwable&TranslatableInterface) $create
     */
    #[DataProvider('factoriesProvider')]
    public function testTheMessageCanBeGivenWithNamedParameters(Closure $create): void
    {
        $this->assertSame('Cannot do 3.', $create(['Cannot do {n}.', 'n' => 3])->getMessage());
    }

    /**
     * @param Closure(mixed ...): (Throwable&TranslatableInterface) $create
     */
    #[DataProvider('factoriesProvider')]
    public function testItIsTranslated(Closure $create): void
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Cannot do {n}.' => 'No se puede hacer {n}.'], 'es', 'errors+intl-icu');

        $this->assertSame('No se puede hacer 3.', $create(['Cannot do {n}.', 'n' => '3'])->trans($translator));
    }

    /**
     * @param Closure(mixed ...): (Throwable&TranslatableInterface) $create
     */
    #[DataProvider('factoriesProvider')]
    public function testItKeepsItsCodeAndItsPreviousThrowable(Closure $create): void
    {
        $previous = new Exception('Previous.');
        $throwable = $create('Failed.', 7, $previous);

        $this->assertSame(7, $throwable->getCode());
        $this->assertSame($previous, $throwable->getPrevious());
    }

    /**
     * @param Closure(mixed ...): (Throwable&TranslatableInterface) $create
     */
    #[DataProvider('factoriesProvider')]
    public function testItSurvivesBeingSerialized(Closure $create): void
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Cannot do {n}.' => 'No se puede hacer {n}.'], 'es', 'errors+intl-icu');

        $original = $create(['Cannot do {n}.', 'n' => '3'], 5);
        $copy = unserialize(serialize($original));

        $this->assertInstanceOf($original::class, $copy);
        $this->assertSame('Cannot do 3.', $copy->getMessage());
        $this->assertSame(5, $copy->getCode());
        $this->assertSame('No se puede hacer 3.', $copy->trans($translator));
    }
}
