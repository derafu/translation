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
use Derafu\Translation\Contract\TranslatableMessageInterface;
use Derafu\Translation\Exception\Core\TranslatableLogicException as LogicException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Derafu\Translation\TranslatableMessage;
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
     * The translatable value of the exception: what it translates with. It is a
     * `TranslatableMessage` when the exception is made from a string or an
     * array, and what was given when it is made from a `TranslatableInterface`.
     */
    protected TranslatableInterface $translatable;

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
        return $this->translatable->trans(
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
            'translatable'        => $this->translatable,
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
        // The key of the data that was serialized before it was renamed is
        // accepted, so an exception stored in a session still can be read.
        $this->translatable = $data['translatable'] ?? $data['translatableMessage'];
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
            $this->translatable = new TranslatableMessage(
                $msg,
                $message,
                $this->defaultDomain,
                $this->defaultLocale
            );
        } elseif (is_string($message)) {
            $this->translatable = new TranslatableMessage(
                $message,
                [],
                $this->defaultDomain,
                $this->defaultLocale
            );
        } else {
            $this->translatable = $message;
        }

        return $this->translatable instanceof Throwable
            ? $this->translatable->getMessage()
            : (string) $this->translatable;
    }

    /**
     * The translatable value of the exception.
     *
     * It can be carried without the exception (a flash message in a session, a
     * record in a queue), which an exception can not do without its trace.
     */
    public function getTranslatable(): TranslatableInterface
    {
        return $this->translatable;
    }

    /**
     * The translatable value of the exception, as a message that can be read:
     * its id, its parameters and its domain.
     *
     * @throws LogicException If the exception was made from a translatable value
     * that is not a message.
     */
    public function getTranslatableMessage(): TranslatableMessageInterface
    {
        if (!$this->translatable instanceof TranslatableMessageInterface) {
            throw new LogicException([
                'The translatable value of the exception {class} is not a message: it is a {type}.',
                'class' => static::class,
                'type' => $this->translatable::class,
            ]);
        }

        return $this->translatable;
    }
}
