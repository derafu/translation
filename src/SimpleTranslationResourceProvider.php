<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation;

use Derafu\Translation\Contract\TranslationResourceProviderInterface;

/**
 * Default provider that wraps a fixed list of directories.
 *
 * Useful when there is no need for one provider per package, e.g. a small
 * application that just wants to register N directories directly.
 */
final class SimpleTranslationResourceProvider implements TranslationResourceProviderInterface
{
    /**
     * @param iterable<string> $directories
     */
    public function __construct(
        private readonly iterable $directories
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getDirectories(): iterable
    {
        return $this->directories;
    }
}
