<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Exception\Logic;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use InvalidArgumentException;

/**
 * Translatable version of InvalidArgumentException.
 */
class TranslatableInvalidArgumentException extends InvalidArgumentException implements TranslatableInterface
{
    use TranslatableExceptionTrait;
}
