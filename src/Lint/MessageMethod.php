<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Lint;

/**
 * A method that receives the id of a message: its calls are messages that the
 * code builds to be translated, like `$this->trans('Send')`.
 *
 * Inside such a method the id is a variable, so the scanner can not read it
 * there: the calls are where the messages are. This tells the scanner which
 * methods they are, where the id is and which domain the message uses.
 *
 * It is for the methods of a package: a class of this one (the full name, with
 * `::class`) and one of its methods, as they are written in its code.
 *
 * Part of the lint tools: it is for tools and tests, never for the code that
 * runs the package.
 */
final readonly class MessageMethod
{
    /**
     * @param string $class The full name of the class that has the method (use
     * `::class`). The calls on its subclasses are of the method too. That it
     * exists is checked when the scanner is made.
     * @param string $method Name of the method.
     * @param string|null $domain Domain of the messages of the method. If it is
     * not given, it is the default one of `TranslatableMessage` (`messages`). When
     * the call gives its own domain (`$domainArgument`), that one is used.
     * @param int|string $id Where the id is: the position of the argument
     * (starting at 0) or its name.
     * @param int|string|null $domainArgument Where the domain is, if the call
     * can give it: the position of the argument or its name.
     */
    public function __construct(
        public string $class,
        public string $method,
        public ?string $domain = null,
        public int|string $id = 0,
        public int|string|null $domainArgument = null
    ) {
    }
}
