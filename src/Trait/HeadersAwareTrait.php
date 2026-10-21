<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Trait;

/**
 * Implements `HeadersAwareInterface`: keeps the headers, with none by default.
 */
trait HeadersAwareTrait
{
    /**
     * @var array<string, string|list<string>>
     */
    protected array $headers = [];

    /**
     * {@inheritDoc}
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
