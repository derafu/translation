<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation;

use Closure;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Core\TranslatableException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\Lint\MessageMethod;
use Derafu\Translation\Lint\MessageReferenceScanner;
use Derafu\Translation\Lint\ThrowReferenceScanner;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use Derafu\Translation\Translation\TranslationResourceProvider;
use Derafu\Translation\TranslationResourceRegistrar;
use Derafu\Translation\TranslatorFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The errors of this package are translatable, like the ones of the packages
 * that use it: they are `InvalidArgumentException` that can be translated.
 */
#[CoversClass(TranslationResourceRegistrar::class)]
#[CoversClass(TranslatableInvalidArgumentException::class)]
#[CoversClass(TranslationResourceProvider::class)]
#[CoversClass(MessageReferenceScanner::class)]
#[UsesClass(MessageMethod::class)]
#[CoversClass(ThrowReferenceScanner::class)]
#[UsesClass(TranslatorFactory::class)]
#[UsesClass(TranslatableException::class)]
#[UsesClass(TranslatableMessage::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
final class TranslatableErrorsTest extends TestCase
{
    /**
     * @return array<string, array{Closure(): mixed, string}>
     */
    public static function errorsProvider(): array
    {
        $registrar = fn () => new TranslationResourceRegistrar(TranslatorFactory::create('en'));
        $errors = __DIR__ . '/../fixtures/registrar-errors';

        return [
            'directory that does not exist' => [
                fn () => $registrar()->registerDirectory('/nonexistent/directory'),
                'Translation directory "/nonexistent/directory" does not exist.',
            ],
            'file that does not follow the convention' => [
                fn () => $registrar()->registerDirectory($errors . '/bad-name'),
                'does not follow the "domain.locale.format" naming convention.',
            ],
            'extension that is not recognized' => [
                fn () => $registrar()->registerDirectory($errors . '/unsupported-extension'),
                'Unrecognized translation file extension "txt" for file ',
            ],
            'message that is an empty array' => [
                fn () => new TranslatableException([]),
                'Message array cannot be empty.',
            ],
            'message whose first element is not a string' => [
                fn () => new TranslatableException([123, 'param' => 'value']),
                'First element of message array must be a string.',
            ],
        ];
    }

    /**
     * @param Closure(): mixed $do
     */
    #[DataProvider('errorsProvider')]
    public function testEveryErrorIsATranslatableInvalidArgumentException(Closure $do, string $message): void
    {
        $exception = null;
        try {
            $do();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertInstanceOf(TranslatableInvalidArgumentException::class, $exception);
        $this->assertStringContainsString($message, $exception->getMessage());
    }

    /**
     * The error of a message that is not valid is made with a message that is:
     * it does not enter the same error again.
     */
    public function testAnInvalidMessageDoesNotMakeTheErrorLoop(): void
    {
        try {
            new TranslatableException([]);
            $this->fail('The empty array was accepted.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertNull($e->getPrevious());
            $this->assertSame('Message array cannot be empty.', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{Closure(): mixed, string}>
     */
    public static function spanishProvider(): array
    {
        $registrar = fn () => new TranslationResourceRegistrar(TranslatorFactory::create('en'));
        $errors = __DIR__ . '/../fixtures/registrar-errors';

        return [
            'directory that does not exist' => [
                fn () => $registrar()->registerDirectory('/nonexistent/directory'),
                'El directorio de traducciones "/nonexistent/directory" no existe.',
            ],
            'file that does not follow the convention' => [
                fn () => $registrar()->registerDirectory($errors . '/bad-name'),
                'no sigue la convención de nombre "dominio.locale.formato".',
            ],
            'extension that is not recognized' => [
                fn () => $registrar()->registerDirectory($errors . '/unsupported-extension'),
                'Extensión de archivo de traducciones no reconocida "txt" para el archivo ',
            ],
            'message that is an empty array' => [
                fn () => new TranslatableException([]),
                'El arreglo del mensaje no puede estar vacío.',
            ],
            'message whose first element is not a string' => [
                fn () => new TranslatableException([123]),
                'El primer elemento del arreglo del mensaje debe ser un string.',
            ],
            'message scanner, file' => [
                fn () => (new MessageReferenceScanner())->scanFile('/nonexistent/file.php'),
                'El archivo /nonexistent/file.php no existe.',
            ],
            'message scanner, directory' => [
                fn () => (new MessageReferenceScanner())->scanDirectory('/nonexistent/directory'),
                'El directorio /nonexistent/directory no existe.',
            ],
            'message scanner, method that does not exist' => [
                fn () => new MessageReferenceScanner([new MessageMethod(TranslatableException::class, 'missing')]),
                'El método Derafu\\Translation\\Exception\\Core\\TranslatableException::missing no existe.',
            ],
            'message scanner, argument that does not exist' => [
                fn () => new MessageReferenceScanner([new MessageMethod(TranslatableException::class, 'trans', id: 'missing')]),
                'no tiene el argumento missing.',
            ],
            'throw scanner, file' => [
                fn () => (new ThrowReferenceScanner())->scanFile('/nonexistent/file.php'),
                'El archivo /nonexistent/file.php no existe.',
            ],
            'throw scanner, directory' => [
                fn () => (new ThrowReferenceScanner())->scanDirectory('/nonexistent/directory'),
                'El directorio /nonexistent/directory no existe.',
            ],
        ];
    }

    /**
     * Every message of the package has its translation: it is what the catalogue
     * of the package is for.
     *
     * @param Closure(): mixed $do
     */
    #[DataProvider('spanishProvider')]
    public function testEveryErrorHasItsSpanishTranslation(Closure $do, string $spanish): void
    {
        $translator = TranslatorFactory::create('es', [], [new TranslationResourceProvider()]);

        $exception = null;
        try {
            $do();
        } catch (TranslatableException|TranslatableInvalidArgumentException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString($spanish, $exception->trans($translator));
    }
}
