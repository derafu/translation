<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Lint\Fixture;

use RuntimeException;

/**
 * An exception class that is not translatable: it extends a native one.
 */
final class FixtureNativeChildException extends RuntimeException
{
    public static function fail(): never
    {
        throw new static('Fails.');
    }

    public static function failWithSelf(): never
    {
        throw new self('Fails.');
    }
}
