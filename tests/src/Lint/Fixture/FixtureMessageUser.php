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
 * A class that uses the methods of `FixtureMessageMethods`, by being one.
 */
class FixtureMessageUser extends FixtureMessageMethods
{
    /**
     * @return list<TranslatableMessage|string>
     */
    public function run(mixed $variable, bool $flag, object $other): array
    {
        return [
            $this->trans('Send'),
            $this->trans('Name', [], 'custom'),
            $this->trans(domain: 'named', id: 'Named arguments'),
            self::translate('Static with self'),
            static::translate('Static with static'),
            parent::translate('Static with parent'),
            FixtureMessageMethods::translate('Static with the class'),
            $this->withDomain('chosen', 'With the domain first'),
            $this->trans($variable),
            $this->trans($flag ? 'First.' : 'Second.'),
            $this->trans(''),
            $other->trans('Receiver that is not known'),
            FixtureOtherTranslator::translate('Class that is not one of them'),
        ];
    }
}
