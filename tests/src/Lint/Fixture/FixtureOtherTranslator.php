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

/**
 * A class that is not one of the methods that receive an id, with a method that
 * has the same name as one of them.
 */
final class FixtureOtherTranslator
{
    public static function translate(string $text): string
    {
        return $text;
    }
}
