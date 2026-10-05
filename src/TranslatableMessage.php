<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation;

use Derafu\Translation\Contract\TranslatableAwareInterface;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Contract\TranslatableMessageInterface;
use IntlException;
use JsonSerializable;
use MessageFormatter;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * A translatable message that supports ICU message formatting.
 *
 * This class implements TranslatableMessageInterface to provide a message that
 * can be translated using a translator or fallback to ICU formatting when no
 * translator is available.
 */
final class TranslatableMessage implements TranslatableMessageInterface
{
    /**
     * Creates a new translatable message.
     *
     * @param string $message The message used for translation. This should be a
     * valid ICU message format string as it will be used as fallback when no
     * translator is available.
     * @param array<string, mixed> $parameters Parameters for translation
     * placeholders.
     * @param string|null $domain The translation domain or `null` for default
     * domain.
     * @param string $defaultLocale The locale to use for ICU formatting when no
     * translator is available. This must be a valid ICU locale identifier.
     */
    public function __construct(
        private readonly string $message,
        private readonly array $parameters = [],
        private readonly ?string $domain = null,
        private readonly ?string $defaultLocale = null
    ) {
    }

    /**
     * Translates the message using the provided translator.
     *
     * If the translator knows its catalogues (`TranslatorBagInterface`, like
     * Symfony's) and has no entry for the message, the message is formatted as
     * it is by `__toString()`. Without an entry, Symfony does not apply ICU: it
     * would replace the name of each parameter inside the braces, leaving
     * `{value}` in the text.
     *
     * @param TranslatorInterface $translator The translator to use.
     * @param string|null $locale The locale to translate to or `null` for
     * default.
     * @return string The translated message.
     */
    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null
    ): string {
        $locale ??= $this->defaultLocale;

        if (
            $translator instanceof TranslatorBagInterface
            && !$translator->getCatalogue($locale)->has($this->message, $this->domain ?? 'messages')
        ) {
            return (string) $this;
        }

        return $translator->trans(
            $this->message,
            $this->normalizeParameters(false),
            $this->domain,
            $locale
        );
    }

    /**
     * Converts the message to a string using ICU formatting when no translator
     * is available.
     *
     * @return string The formatted message. If ICU formatting fails, returns
     * the raw message as fallback.
     */
    public function __toString(): string
    {
        try {
            $formatter = new MessageFormatter(
                $this->defaultLocale ?? 'en',
                $this->message
            );
        } catch (IntlException $e) {
            return $this->message;
        }

        $result = $formatter->format($this->normalizeParameters(true));

        if ($result === false) {
            // Log error if needed: intl_get_error_message().
            return $this->message;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritDoc}
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * {@inheritDoc}
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultLocale(): ?string
    {
        return $this->defaultLocale;
    }

    /**
     * {@inheritDoc}
     *
     * A parameter that is a translatable value is exported as its own data (a
     * message inside the message), and any other throwable as its message: an
     * exception is not data, and `json_encode()` would make it an empty object.
     * What is not JSON stays as `json_encode()` makes it, so a date, an object or
     * a resource does not come back as it was: it is not a format to rebuild the
     * message from.
     */
    public function jsonSerialize(): array
    {
        return [
            'message' => $this->message,
            'parameters' => array_map(
                fn (mixed $value) => $this->exportParameter($value),
                $this->parameters
            ),
            'domain' => $this->domain,
            'defaultLocale' => $this->defaultLocale,
        ];
    }

    /**
     * A parameter as data.
     */
    private function exportParameter(mixed $value): mixed
    {
        if ($value instanceof TranslatableAwareInterface) {
            $translatable = $value->getTranslatable();

            if ($translatable instanceof JsonSerializable) {
                return $translatable;
            }
        }

        return $value instanceof Throwable ? $value->getMessage() : $value;
    }

    /**
     * The parameters as they are given to the translator or to ICU.
     *
     * A throwable is turned into its message: to a string it would be the dump
     * of PHP (class, file, line and trace), which is not a text for a person.
     * A translatable throwable is left as it is when a translator is going to
     * translate the message, because the translator translates it too (it is a
     * message nested in this one); without a translator its message is what is
     * formatted.
     *
     * @param bool $withoutTranslator Whether the message is formatted without a
     * translator.
     * @return array<string, mixed>
     */
    private function normalizeParameters(bool $withoutTranslator): array
    {
        return array_map(
            fn (mixed $value) => $value instanceof Throwable
                && ($withoutTranslator || !$value instanceof TranslatableInterface)
                ? $value->getMessage()
                : $value,
            $this->parameters
        );
    }
}
