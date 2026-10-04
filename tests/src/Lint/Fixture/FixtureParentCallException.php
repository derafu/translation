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

use Derafu\Translation\Exception\Core\TranslatableRuntimeException;

/**
 * A translatable exception that builds its own message in its constructor.
 */
class FixtureParentCallException extends TranslatableRuntimeException
{
    public function __construct(private readonly string $uri)
    {
        parent::__construct(['No route for {uri}.', 'uri' => $uri], 404);
    }
}
