<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Contract;

use Derafu\Translation\Exception\Core\TranslatableLogicException;

/**
 * Something that is made of a translatable value and gives it as a message.
 *
 * The translatable value of an object can be any `TranslatableInterface`: it is
 * not always a message. This is for the objects whose value is a message that
 * can be read (its id, its parameters and its domain), so it fails when it is not.
 */
interface TranslatableMessageAwareInterface extends TranslatableAwareInterface
{
    /**
     * The translatable value of the object, as a message that can be read.
     *
     * @throws TranslatableLogicException If the translatable value of the object
     * is not a message.
     */
    public function getTranslatableMessage(): TranslatableMessageInterface;
}
