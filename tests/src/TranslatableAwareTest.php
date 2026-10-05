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

use Derafu\Translation\Contract\TranslatableAwareInterface;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Contract\TranslatableMessageAwareInterface;
use Derafu\Translation\Contract\TranslatableMessageInterface;
use Derafu\Translation\Error\TranslatableError;
use Derafu\Translation\Exception\Core\TranslatableException;
use Derafu\Translation\Exception\Core\TranslatableLogicException;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translatable exception gives the translatable value that it is made of, so
 * it can be carried without the exception (a flash message in a session, a record
 * in a queue), and as a message that can be read (its id, its parameters and its
 * domain) to log it or to send it as data.
 */
#[CoversTrait(TranslatableExceptionTrait::class)]
#[CoversClass(TranslatableException::class)]
#[UsesClass(TranslatableLogicException::class)]
#[UsesClass(TranslatableMessage::class)]
final class TranslatableAwareTest extends TestCase
{
    /**
     * Every class of the package that is a translatable exception or error, found
     * by looking at the files, so a new one can not be left out.
     *
     * @return array<string, array{class-string}>
     */
    public static function provideTheClassesOfThePackage(): array
    {
        $classes = [];
        $files = array_merge(
            glob(dirname(__DIR__, 2) . '/src/Exception/*/*.php') ?: [],
            glob(dirname(__DIR__, 2) . '/src/Error/*.php') ?: []
        );
        foreach ($files as $file) {
            $class = 'Derafu\\Translation\\'
                . str_replace('/', '\\', substr($file, strlen(dirname(__DIR__, 2) . '/src/'), -4));
            $classes[$class] = [$class];
        }

        return $classes;
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideTheClassesOfThePackage')]
    public function testEveryTranslatableThrowableOfThePackageGivesItsMessage(string $class): void
    {
        $throwable = new $class(['Hello {name}', 'name' => 'John']);

        $this->assertInstanceOf(TranslatableMessageAwareInterface::class, $throwable);
        $this->assertInstanceOf(TranslatableAwareInterface::class, $throwable);
        $this->assertSame('Hello {name}', $throwable->getTranslatableMessage()->getMessage());
    }

    public function testTheTranslatableValueOfAnExceptionMadeFromAStringIsItsMessage(): void
    {
        $exception = new TranslatableRuntimeException('Cannot read the file.');

        $this->assertInstanceOf(TranslatableMessageInterface::class, $exception->getTranslatable());
        $this->assertSame('Cannot read the file.', $exception->getTranslatableMessage()->getMessage());
        $this->assertSame([], $exception->getTranslatableMessage()->getParameters());
        $this->assertSame('errors', $exception->getTranslatableMessage()->getDomain());
        $this->assertSame($exception->getTranslatable(), $exception->getTranslatableMessage());
    }

    public function testTheTranslatableValueOfAnExceptionMadeFromAnArrayHasItsParameters(): void
    {
        $exception = new TranslatableRuntimeException(['Cannot read {file}.', 'file' => 'a.txt']);

        $message = $exception->getTranslatableMessage();

        $this->assertSame('Cannot read {file}.', $message->getMessage());
        $this->assertSame(['file' => 'a.txt'], $message->getParameters());
    }

    public function testTheTranslatableValueThatWasGivenIsTheOneThatIsGiven(): void
    {
        $given = new TranslatableMessage('Cannot read {file}.', ['file' => 'a.txt'], 'files');
        $exception = new TranslatableRuntimeException($given);

        $this->assertSame($given, $exception->getTranslatable());
        $this->assertSame($given, $exception->getTranslatableMessage());
        $this->assertSame('files', $exception->getTranslatableMessage()->getDomain());
    }

    /**
     * A translatable value that is not a message is valid: it translates, but it
     * can not be read as a message.
     */
    public function testATranslatableValueThatIsNotAMessageIsGivenButNotAsAMessage(): void
    {
        $given = new class () implements TranslatableInterface {
            public function trans(TranslatorInterface $translator, ?string $locale = null): string
            {
                return 'translated';
            }

            public function __toString(): string
            {
                return 'Custom text.';
            }
        };
        $exception = new TranslatableRuntimeException($given);

        $this->assertSame($given, $exception->getTranslatable());
        $this->assertSame('Custom text.', $exception->getMessage());

        try {
            $exception->getTranslatableMessage();
            $this->fail('A translatable value that is not a message was given as a message.');
        } catch (TranslatableLogicException $e) {
            $this->assertStringContainsString(TranslatableRuntimeException::class, $e->getMessage());
            $this->assertStringContainsString($given::class, $e->getMessage());
        }
    }

    public function testTheTranslatableValueSurvivesTheSerializationOfTheException(): void
    {
        $exception = new TranslatableRuntimeException(['Cannot read {file}.', 'file' => 'a.txt'], 5);

        $restored = unserialize(serialize($exception));

        $this->assertInstanceOf(TranslatableRuntimeException::class, $restored);
        $this->assertSame('Cannot read {file}.', $restored->getTranslatableMessage()->getMessage());
        $this->assertSame(['file' => 'a.txt'], $restored->getTranslatableMessage()->getParameters());
        $this->assertSame(5, $restored->getCode());
    }

    /**
     * An exception that was serialized before the key of its translatable value
     * was renamed (a flash message that was in a session) can still be read.
     */
    public function testAnExceptionSerializedWithTheOldKeyCanStillBeRead(): void
    {
        $message = new TranslatableMessage('Cannot read {file}.', ['file' => 'a.txt'], 'errors', 'en');
        $exception = (new ReflectionClass(TranslatableRuntimeException::class))->newInstanceWithoutConstructor();

        $exception->__unserialize([
            'message' => 'Cannot read a.txt.',
            'code' => 0,
            'file' => __FILE__,
            'line' => __LINE__,
            'previous' => null,
            'defaultDomain' => 'errors',
            'defaultLocale' => 'en',
            'translatableMessage' => $message,
        ]);

        $this->assertSame($message, $exception->getTranslatable());
    }

    public function testAnErrorGivesItsMessageToo(): void
    {
        $error = new TranslatableError(['Something broke: {what}', 'what' => 'the disk']);

        $this->assertSame(['what' => 'the disk'], $error->getTranslatableMessage()->getParameters());
    }
}
