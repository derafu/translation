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
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translatable exception that is not made with the trait of the package, so
 * it has no `$defaultDomain`: its messages have no domain to be read.
 */
final class FixtureNoDomainException extends RuntimeException implements TranslatableInterface
{
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->getMessage();
    }
}
