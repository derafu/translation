<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Exception\Core;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Core\TranslatableException;
use Derafu\Translation\TranslatableMessage;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[CoversClass(TranslatableException::class)]
#[CoversClass(TranslatableMessage::class)]
final class TranslatableExceptionTest extends TestCase
{
    public function testConstructWithParameters(): void
    {
        $exception = new TranslatableException([
            'error.test',
            'param' => 'value',
        ]);

        // Locale is null: with no explicit locale (constructor arg or
        // trans() argument), the call must defer to the translator's own
        // configured locale, not silently override it. See
        // testTransWithoutExplicitLocaleUsesTranslatorDefaultLocale() below
        // for the real-Translator regression test this guards.
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with(
                'error.test',
                ['param' => 'value'],
                'errors',
                null
            )
            ->willReturn('Test with value');

        $this->assertSame(
            'Test with value',
            $exception->trans($translator)
        );
    }

    public function testConstructWithEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message array cannot be empty.');

        new TranslatableException([]);
    }

    public function testConstructWithInvalidArrayFirstElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'First element of message array must be a string.'
        );

        new TranslatableException([123, 'param' => 'value']);
    }

    public function testConstructWithPreviousException(): void
    {
        $previous = new Exception('Previous error');
        $exception = new TranslatableException(
            'Test message',
            0,
            $previous
        );

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testIcuFormatting(): void
    {
        $exception = new TranslatableException([
            'Error with {param}',
            'param' => 'test',
        ]);

        // Test default ICU formatting without translator.
        $this->assertSame(
            'Error with test',
            $exception->getMessage()
        );
    }

    public function testExceptionInheritance(): void
    {
        $exception = new TranslatableException('Test');

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertInstanceOf(Throwable::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
    }

    public function testConstructWithString(): void
    {
        $exception = new class ('validation.required') extends TranslatableException {};

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with(
                'validation.required',
                [],
                'errors',
                null
            )
            ->willReturn('This field is required');

        $this->assertSame(
            'This field is required',
            $exception->trans($translator)
        );
    }

    public function testConstructWithArrayParameters(): void
    {
        $exception = new class ([
            'validation.min_length',
            'field' => 'password',
            'min' => 8,
        ]) extends TranslatableException {};

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with(
                'validation.min_length',
                ['field' => 'password', 'min' => 8],
                'errors',
                null
            )
            ->willReturn('The password must be at least 8 characters');

        $this->assertSame(
            'The password must be at least 8 characters',
            $exception->trans($translator)
        );
    }

    public function testConstructWithTranslatableMessage(): void
    {
        $message = new TranslatableMessage(
            'validation.email',
            ['email' => 'test@example.com'],
            'errors',
            'en'
        );

        $exception = new class ($message) extends TranslatableException {};

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with(
                'validation.email',
                ['email' => 'test@example.com'],
                'errors',
                'en'
            )
            ->willReturn('Invalid email: test@example.com');

        $this->assertSame(
            'Invalid email: test@example.com',
            $exception->trans($translator)
        );
    }

    public function testConstructWithArrayWithoutString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('First element of message array must be a string.');

        new class ([123, 'param' => 'value']) extends TranslatableException {};
    }

    public function testMessageWithoutTranslator(): void
    {
        $exception = new class ([
            'The field {field} must be at least {min} characters',
            'field' => 'password',
            'min' => 8,
        ]) extends TranslatableException {};

        // Without a translator, it should use ICU formatting directly.
        $this->assertSame(
            'The field password must be at least 8 characters',
            $exception->getMessage()
        );
    }

    public function testCustomDomainAndLocale(): void
    {
        $exception = new class ('custom.message') extends TranslatableException {
            protected string $defaultDomain = 'custom';

            protected ?string $defaultLocale = 'es';
        };

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with(
                'custom.message',
                [],
                'custom',
                'es'
            )
            ->willReturn('Mensaje personalizado');

        $this->assertSame(
            'Mensaje personalizado',
            $exception->trans($translator)
        );
    }

    /**
     * Regression test for a real bug: `trans()` called with no explicit
     * locale used to hardcode 'en' (the trait's old default), silently
     * overriding the translator's own configured locale instead of
     * deferring to it. With a real Translator (not a mock asserting call
     * arguments), that meant: no catalogue entry for 'en', so Symfony fell
     * back to formatting the id itself with plain strtr() and bare
     * (non-`%name%`) parameter keys — corrupting the output by replacing
     * the parameter name substring inside its own surrounding braces
     * (`{reason}` became `{Connection timed out}`, not the intended
     * substitution). This must resolve to the translator's real 'es'
     * catalogue entry instead.
     */
    public function testTransWithoutExplicitLocaleUsesTranslatorDefaultLocale(): void
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'Error sending the message: {reason}.' => 'Error al enviar el mensaje: {reason}.',
        ], 'es', 'errors+intl-icu');

        $exception = new TranslatableException([
            'Error sending the message: {reason}.',
            'reason' => 'Connection timed out',
        ]);

        $this->assertSame(
            'Error al enviar el mensaje: Connection timed out.',
            $exception->trans($translator)
        );
    }
}
