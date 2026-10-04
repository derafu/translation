<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Exception\Core;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use JsonException;

/**
 * Translatable version of JsonException.
 */
class TranslatableJsonException extends JsonException implements TranslatableInterface
{
    use TranslatableExceptionTrait;
}
