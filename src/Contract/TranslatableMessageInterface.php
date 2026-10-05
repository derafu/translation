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

use JsonSerializable;

/**
 * A translatable message: a message that is its own translation id, with the
 * values of its parameters and the domain of its translations.
 *
 * It is what is behind `TranslatableMessage`, and what a translatable exception
 * is made of. It can be read, so a message can be logged by its id and its
 * parameters, sent as data (for example to an API client that translates by
 * itself), or checked in a test without a translator.
 *
 * It is also exported as data (`JsonSerializable`), so `json_encode()` of a
 * message gives its id, its parameters and its domain, and not an empty object.
 * It is a representation to read, for logs, APIs and the stores that keep JSON
 * (the session of Mezzio, for example): the message is not rebuilt from it, it is
 * translated from its data (`message|trans(parameters, domain)`).
 */
interface TranslatableMessageInterface extends TranslatableInterface, JsonSerializable
{
    /**
     * The message: an ICU message format string, which is also its translation
     * id.
     */
    public function getMessage(): string;

    /**
     * The values of the parameters of the message.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;

    /**
     * The translation domain, or `null` for the default domain.
     */
    public function getDomain(): ?string;

    /**
     * The locale used to format the message when there is no translator, or
     * `null` for the default one.
     */
    public function getDefaultLocale(): ?string;

    /**
     * The message as data, to be encoded as JSON.
     *
     * @return array{message: string, parameters: array<string, mixed>, domain: string|null, defaultLocale: string|null}
     */
    public function jsonSerialize(): array;
}
