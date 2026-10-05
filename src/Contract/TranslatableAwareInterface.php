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

/**
 * Something that is made of a translatable value and gives it: a translatable
 * exception, for example.
 *
 * The value is what the object translates with. It is given as it is, so it can
 * be carried without the object, like a flash message in a session or a record
 * in a queue, which an exception can not do without its trace.
 */
interface TranslatableAwareInterface
{
    /**
     * The translatable value of the object.
     */
    public function getTranslatable(): TranslatableInterface;
}
