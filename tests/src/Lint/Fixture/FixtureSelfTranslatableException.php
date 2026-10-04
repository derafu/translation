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

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use RuntimeException;

/**
 * An exception that extends a native one and is translatable itself, like the
 * ones of this package.
 */
class FixtureSelfTranslatableException extends RuntimeException implements TranslatableInterface
{
    use TranslatableExceptionTrait;
}
