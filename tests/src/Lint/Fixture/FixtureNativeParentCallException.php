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

use LogicException;

/**
 * An exception class that is not translatable and makes its parent: `new
 * parent` is the class that it extends.
 */
final class FixtureNativeParentCallException extends LogicException
{
    public static function failLikeTheParent(): never
    {
        throw new parent('Fails.');
    }
}
