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

use Derafu\Translation\Exception\Core\TranslatableException;

/**
 * A translatable exception that uses its own domain.
 */
final class FixtureDomainException extends TranslatableException
{
    protected string $defaultDomain = 'fixture';

    public static function forThing(string $name): static
    {
        return new static(['Thing {name} failed.', 'name' => $name]);
    }

    public static function forSelf(): self
    {
        return new self('Made with self.');
    }

    public static function forDynamic(string $message): static
    {
        return new static($message);
    }
}
