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
 * A class with methods that receive the id of a message, like the ones that some
 * packages have to build their translatable messages.
 */
class FixtureMessageMethods
{
    /**
     * The id is the first argument and the domain is the third, if it is given.
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null): TranslatableMessage
    {
        // The id is passed on: the message is the one of whoever calls.
        return new TranslatableMessage($id, $parameters, $domain ?? 'fixture');
    }

    /**
     * The id is the first argument and the domain is always the same.
     */
    public static function translate(string $id): TranslatableMessage
    {
        return new TranslatableMessage($id, [], 'fixture');
    }

    /**
     * The domain is the first argument and the id the second.
     */
    public function withDomain(string $domain, string $id): TranslatableMessage
    {
        return new TranslatableMessage($id, [], $domain);
    }

    /**
     * A message of its own: it is a message like any other.
     */
    public function own(): TranslatableMessage
    {
        return new TranslatableMessage('Own message of the class.', [], 'fixture');
    }
}
