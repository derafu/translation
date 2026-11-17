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

use Derafu\Translation\TranslatableMessage;

/**
 * What an object that builds messages with an id offers, as an interface: a
 * wider type than the class of `FixtureMessageMethods`.
 */
interface FixtureMessageSource
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null): TranslatableMessage;
}
