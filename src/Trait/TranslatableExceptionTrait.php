<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Trait;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\TranslatableMessage;
use InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Trait for exceptions that what to supports translatable messages.
 *
 * This exception trait can work with regular strings, arrays or translatable
 * messages:
 *
 *   - string: Will be used as both the message and translation key.
 *   - array: First element is the message, remaining elements are parameters.
 *   - TranslatableInterface: Will be used directly.
 */
trait TranslatableExceptionTrait
{
    /**
     * The default translation domain.
     *
     * @var string
     */
    protected string $defaultDomain = 'errors';

    /**
     * The default locale to use when translating.
     *
     * `null` defers to the translator's own configured default locale (via
     * `trans()`) or to `'en'` for ICU formatting when no translator is
     * available at all (via `TranslatableMessage::__toString()`). This must
     * stay `null` by default: hardcoding a locale here would silently
     * override the translator's own configured locale whenever `trans()`
     * is called without an explicit `$locale` argument.
     *
     * @var string|null
     */
    protected ?string $defaultLocale = null;

    /**
     * The translatable message instance.
     */
    protected TranslatableInterface $translatableMessage;

    /**
     * Creates a new exception with translation support.
     *
     * @param string|array|TranslatableInterface $message The exception message:
     *   - string: Will be used as both message and translation key.
     *   - array: First element must be string (message), remaining elements are
     *     parameters.
     *   - TranslatableInterface: Will be used directly.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for exception
     * chaining.
     * @throws InvalidArgumentException When an empty array is provided or first
     * array element is not string.
     */
    public function __construct(
        string|array|TranslatableInterface $message,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $stringMessage = $this->normalizeMessage($message);

        parent::__construct($stringMessage, $code, $previous);
    }

    /**
     * Translates the message using the provided translator.
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
        return $this->translatableMessage->trans(
            $translator,
            $locale ?? $this->defaultLocale
        );
    }

    /**
     * Serializes the exception without the stack trace, which may contain
     * non-serializable values (closures, resources). The trace is intentionally
     * excluded so the exception can be safely stored in sessions (e.g. flash
     * messages). After unserialization getTrace() returns an empty array.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'message'             => $this->getMessage(),
            'code'                => $this->getCode(),
            'file'                => $this->getFile(),
            'line'                => $this->getLine(),
            'previous'            => $this->getPrevious(),
            'defaultDomain'       => $this->defaultDomain,
            'defaultLocale'       => $this->defaultLocale,
            'translatableMessage' => $this->translatableMessage,
        ];
    }

    /**
     * Restores the exception state from serialized data.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->message             = $data['message'];
        $this->code                = $data['code'];
        $this->file                = $data['file'];
        $this->line                = $data['line'];
        $this->defaultDomain       = $data['defaultDomain'];
        $this->defaultLocale       = $data['defaultLocale'];
        $this->translatableMessage = $data['translatableMessage'];
    }

    /**
     * Normalize the $message into a TranslatableMessage and return the default
     * string for the exception.
     *
     * @param string|array|TranslatableInterface $message
     * @return string
     */
    protected function normalizeMessage(string|array|TranslatableInterface $message): string
    {
        if (is_array($message)) {
            if (empty($message)) {
                throw new InvalidArgumentException(
                    'Message array cannot be empty.'
                );
            }
            $msg = array_shift($message);
            if (!is_string($msg)) {
                throw new InvalidArgumentException(
                    'First element of message array must be a string.'
                );
            }
            $this->translatableMessage = new TranslatableMessage(
                $msg,
                $message,
                $this->defaultDomain,
                $this->defaultLocale
            );
        } elseif (is_string($message)) {
            $this->translatableMessage = new TranslatableMessage(
                $message,
                [],
                $this->defaultDomain,
                $this->defaultLocale
            );
        } else {
            $this->translatableMessage = $message;
        }

        return (string) $this->translatableMessage;
    }
}
