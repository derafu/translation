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
 * Something that carries headers of an HTTP response, and gives them: a
 * translatable exception that an HTTP layer turns into a response, for example
 * (`WWW-Authenticate`, `Retry-After`).
 *
 * It is here, and not in a package of HTTP, so an exception that has nothing to
 * do with HTTP (a package that does not depend on one) can still carry them,
 * and a package of HTTP can read them from any exception without knowing it.
 */
interface HeadersAwareInterface
{
    /**
     * Sets the headers.
     *
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): static;

    /**
     * The headers, or none.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;
}
