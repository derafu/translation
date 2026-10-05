<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Contract\TranslatableMessageInterface;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(TranslatableMessage::class)]
#[UsesClass(TranslatableRuntimeException::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
final class TranslatableMessageTest extends TestCase
{
    public function testBasicIcuMessage(): void
    {
        $message = new TranslatableMessage(
            'Hello {name}',
            ['name' => 'John'],
            null,
            'en'
        );

        $this->assertSame('Hello John', (string) $message);
    }

    public function testMessageWithMultipleParameters(): void
    {
        $message = new TranslatableMessage(
            '{count, plural, one{# message} other{# messages}} from {sender}',
            [
                'count' => 5,
                'sender' => 'Admin',
            ],
            null,
            'en'
        );

        $this->assertSame('5 messages from Admin', (string) $message);
    }

    public function testMessageWithTranslator(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with(
                'hello.world',
                ['name' => 'John'],
                'messages',
                'es'
            )
            ->willReturn('¡Hola John!');

        $message = new TranslatableMessage(
            'hello.world',
            ['name' => 'John'],
            'messages',
            'en'
        );

        $this->assertSame('¡Hola John!', $message->trans($translator, 'es'));
    }

    public function testFallbackOnInvalidIcuFormat(): void
    {
        // An invalid ICU message should return the original message.
        $message = new TranslatableMessage(
            'Hello {name',  // Missing closing brace for the placeholder.
            ['name' => 'John'],
            null,
            'en'
        );

        $this->assertSame('Hello {name', (string) $message);
    }

    public function testGenderSelect(): void
    {
        $message = new TranslatableMessage(
            '{gender, select, female{She is} male{He is} other{They are}} {status}',
            [
                'gender' => 'female',
                'status' => 'online',
            ],
            null,
            'en'
        );

        $this->assertSame('She is online', (string) $message);
    }

    public function testNestedParameters(): void
    {
        $message = new TranslatableMessage(
            'User {user} has {count, plural, one{# message} other{# messages}} in {folder}',
            [
                'user' => 'admin',
                'count' => 2,
                'folder' => 'inbox',
            ],
            null,
            'en'
        );

        $this->assertSame(
            'User admin has 2 messages in inbox',
            (string) $message
        );
    }

    private function translator(string $locale = 'es'): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());

        return $translator;
    }

    /**
     * Without an entry in the catalogue, Symfony does not apply ICU: it would
     * replace the name of the parameter inside the braces (`{John}`). The
     * message must come out as its own string, the same as `(string)`.
     */
    public function testWithoutAnEntryItIsFormattedLikeTheMessageItself(): void
    {
        $message = new TranslatableMessage('Hello {name}', ['name' => 'John'], 'errors', 'en');

        $this->assertSame('Hello John', $message->trans($this->translator()));
        $this->assertSame((string) $message, $message->trans($this->translator()));
    }

    public function testWithoutAnEntryAnIcuPluralIsFormatted(): void
    {
        $message = new TranslatableMessage(
            '{count, plural, one{# message} other{# messages}}',
            ['count' => 5],
            'errors',
            'en'
        );

        $this->assertSame('5 messages', $message->trans($this->translator()));
    }

    public function testWithoutAnEntryAndWithoutParametersTheTextIsKept(): void
    {
        $message = new TranslatableMessage('Not Found', [], 'errors', 'en');

        $this->assertSame('Not Found', $message->trans($this->translator()));
    }

    public function testWithAnEntryItIsTranslated(): void
    {
        $translator = $this->translator();
        $translator->addResource('array', ['Hello {name}' => 'Hola {name}'], 'es', 'errors+intl-icu');

        $message = new TranslatableMessage('Hello {name}', ['name' => 'John'], 'errors');

        $this->assertSame('Hola John', $message->trans($translator));
    }

    public function testAnEntryOfTheFallbackLocaleIsFound(): void
    {
        $translator = $this->translator('fr');
        $translator->setFallbackLocales(['es']);
        $translator->addResource('array', ['Hello {name}' => 'Hola {name}'], 'es', 'errors+intl-icu');

        $message = new TranslatableMessage('Hello {name}', ['name' => 'John'], 'errors');

        $this->assertSame('Hola John', $message->trans($translator, 'fr'));
    }

    public function testAnEntryOfAnotherDomainIsNotAnEntry(): void
    {
        $translator = $this->translator();
        $translator->addResource('array', ['Hello {name}' => 'Hola {name}'], 'es', 'other+intl-icu');

        $message = new TranslatableMessage('Hello {name}', ['name' => 'John'], 'errors');

        $this->assertSame('Hello John', $message->trans($translator));
    }

    public function testWithoutADomainTheDefaultDomainIsLookedUp(): void
    {
        $translator = $this->translator();
        $translator->addResource('array', ['Hello {name}' => 'Hola {name}'], 'es', 'messages+intl-icu');

        $message = new TranslatableMessage('Hello {name}', ['name' => 'John']);

        $this->assertSame('Hola John', $message->trans($translator));
    }

    public function testTheMessageCanBeRead(): void
    {
        $message = new TranslatableMessage('Hello {name}', ['name' => 'John'], 'greetings', 'es');

        $this->assertInstanceOf(TranslatableMessageInterface::class, $message);
        $this->assertSame('Hello {name}', $message->getMessage());
        $this->assertSame(['name' => 'John'], $message->getParameters());
        $this->assertSame('greetings', $message->getDomain());
        $this->assertSame('es', $message->getDefaultLocale());
    }

    public function testTheDomainAndTheLocaleThatWereNotGivenAreNull(): void
    {
        $message = new TranslatableMessage('Hello');

        $this->assertSame([], $message->getParameters());
        $this->assertNull($message->getDomain());
        $this->assertNull($message->getDefaultLocale());
    }

    public function testTheMessageIsEncodedAsItsData(): void
    {
        $message = new TranslatableMessage('Hello {name}', ['name' => 'Ana'], 'auth', 'es');

        $this->assertInstanceOf(JsonSerializable::class, $message);
        $this->assertSame(
            '{"message":"Hello {name}","parameters":{"name":"Ana"},"domain":"auth","defaultLocale":"es"}',
            json_encode($message)
        );
    }

    /**
     * What a store that keeps JSON gives back (the session of Mezzio, for
     * example) has the names of the getters, so a view can translate from it.
     */
    public function testWhatAJsonStoreGivesBackHasTheNamesOfTheGetters(): void
    {
        $message = new TranslatableMessage('Hello {name}', ['name' => 'Ana'], 'auth');

        $stored = json_decode((string) json_encode($message), true);

        $this->assertSame(
            [
                'message' => $message->getMessage(),
                'parameters' => $message->getParameters(),
                'domain' => $message->getDomain(),
                'defaultLocale' => $message->getDefaultLocale(),
            ],
            $stored
        );
    }

    public function testADomainAndALocaleThatWereNotGivenAreNullInTheData(): void
    {
        $this->assertSame(
            ['message' => 'Hello', 'parameters' => [], 'domain' => null, 'defaultLocale' => null],
            (new TranslatableMessage('Hello'))->jsonSerialize()
        );
    }

    public function testAMessageInsideTheMessageIsExportedAsItsOwnData(): void
    {
        $message = new TranslatableMessage(
            'Component {component}: {message}',
            ['component' => 'accordion', 'message' => new TranslatableMessage('Item title is required.', [], 'errors')]
        );

        $this->assertSame(
            [
                'component' => 'accordion',
                'message' => [
                    'message' => 'Item title is required.',
                    'parameters' => [],
                    'domain' => 'errors',
                    'defaultLocale' => null,
                ],
            ],
            json_decode((string) json_encode($message), true)['parameters']
        );
    }

    public function testATranslatableExceptionInsideTheMessageIsExportedAsItsMessageData(): void
    {
        $inner = new TranslatableRuntimeException(['Cannot read {file}.', 'file' => 'a.txt']);
        $message = new TranslatableMessage('Failed: {reason}', ['reason' => $inner]);

        $this->assertSame(
            ['reason' => ['message' => 'Cannot read {file}.', 'parameters' => ['file' => 'a.txt'], 'domain' => 'errors', 'defaultLocale' => null]],
            json_decode((string) json_encode($message), true)['parameters']
        );
    }

    public function testATranslatableExceptionThatIsNotAMessageIsExportedAsItsText(): void
    {
        $custom = new class () implements TranslatableInterface {
            public function trans(\Symfony\Contracts\Translation\TranslatorInterface $translator, ?string $locale = null): string
            {
                return 'translated';
            }

            public function __toString(): string
            {
                return 'Custom text.';
            }
        };
        $inner = new TranslatableRuntimeException($custom);

        $this->assertSame(
            ['reason' => 'Custom text.'],
            json_decode((string) json_encode(new TranslatableMessage('Failed: {reason}', ['reason' => $inner])), true)['parameters']
        );
    }

    public function testAnExceptionThatIsNotTranslatableInsideTheMessageIsExportedAsItsText(): void
    {
        $message = new TranslatableMessage('Failed: {reason}', ['reason' => new RuntimeException('The disk is full.')]);

        $this->assertSame(
            ['reason' => 'The disk is full.'],
            json_decode((string) json_encode($message), true)['parameters']
        );
    }
}
