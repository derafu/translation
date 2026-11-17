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
 * A class that calls the methods that receive an id on its properties, which are
 * typed in different ways: each call says in its text what the scanner has to
 * do with it.
 */
class FixtureTypedReceivers extends FixtureMessageMethods
{
    public function __construct(
        protected readonly FixtureMessageMethods $inherited,
        private readonly FixtureMessageMethods $promoted,
        private readonly ?FixtureMessageMethods $nullable,
        private readonly FixtureMessageMethods|null $union,
        private readonly ?self $itself,
        private readonly FixtureMessageSource $wide,
        private readonly FixtureOtherTranslator $other,
    ) {
    }

    /**
     * @return list<mixed>
     */
    public function run(): array
    {
        return [
            $this->promoted->trans('Promoted property'),
            $this->nullable?->trans('Nullsafe property'),
            $this->union?->trans('Union with null'),
            $this->itself?->trans('Property of its own class'),
            $this->wide->trans('Property with a type that is wider'),
            $this->other::translate('Property of another class'),
        ];
    }
}
