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
 * An exception that the code throws, or declares, that is not translatable.
 *
 * Part of the lint tools: it is for tools and tests, never for the code that
 * runs the package.
 */
final readonly class ThrowReference
{
    /**
     * @param string $class The exception that is thrown (`throw new X`), or the
     * class that is declared (`class X extends Y`).
     * @param string|null $extends The class that the declared class extends, or
     * `null` when it is a `throw`.
     * @param string $file The file where it was found.
     * @param int $line The line where it was found.
     * @param bool $loadable Whether the class could be loaded. If it could not
     * be, it is not known whether it can be translated: it is reported
     * anyway, because what is thrown is an exception.
     */
    public function __construct(
        public string $class,
        public ?string $extends,
        public string $file,
        public int $line,
        public bool $loadable
    ) {
    }

    /**
     * Whether it is a class that extends an exception that is not translatable,
     * and not a `throw` of one.
     */
    public function isDeclaration(): bool
    {
        return $this->extends !== null;
    }
}
