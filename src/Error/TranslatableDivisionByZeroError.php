<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Error;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Contract\TranslatableMessageAwareInterface;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use DivisionByZeroError;

/**
 * Translatable version of DivisionByZeroError.
 */
class TranslatableDivisionByZeroError extends DivisionByZeroError implements TranslatableInterface, TranslatableMessageAwareInterface
{
    use TranslatableExceptionTrait;
}
