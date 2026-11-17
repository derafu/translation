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
 * A class that uses a property that it inherits.
 */
final class FixtureTypedReceiversChild extends FixtureTypedReceivers
{
    /**
     * @return list<mixed>
     */
    public function child(): array
    {
        return [
            $this->inherited->trans('Property of the parent'),
        ];
    }
}
