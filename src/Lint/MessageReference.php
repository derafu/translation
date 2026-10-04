<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Lint;

/**
 * A message found in the code that is built to be translated: the one given to
 * a translatable exception or to a `TranslatableMessage`.
 *
 * Part of the lint tools: it is for tools and tests, never for the code that
 * runs the package.
 */
final readonly class MessageReference
{
    /**
     * @param string|null $id The message (its translation id), or `null` when
     * it is not a literal and so it is only known when the code runs.
     * @param string|null $domain The translation domain of the message, or
     * `null` when it is not a literal.
     * @param class-string $class The class that is built with the message: the
     * translatable exception or `TranslatableMessage`.
     * @param string $file The file where the message was found.
     * @param int $line The line where the message was found.
     */
    public function __construct(
        public ?string $id,
        public ?string $domain,
        public string $class,
        public string $file,
        public int $line
    ) {
    }

    /**
     * Whether the message or its domain are only known when the code runs, so
     * they can not be checked by reading it.
     */
    public function isDynamic(): bool
    {
        return $this->id === null || $this->domain === null;
    }
}
