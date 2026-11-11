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
 * A translatable exception that makes its parent: `new parent` is the class that
 * it extends.
 */
final class FixtureNewParentException extends FixtureDefaultMessageException
{
    public static function likeTheParent(): FixtureDefaultMessageException
    {
        return new parent('Made as the parent.');
    }
}
