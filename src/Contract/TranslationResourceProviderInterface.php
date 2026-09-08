<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Contract;

/**
 * Declares directories containing translation resources.
 *
 * Implementations are meant to be collected (e.g. via a DI tagged iterator)
 * so that `TranslationResourceRegistrar` can register every directory
 * without a central place having to know about each package in advance.
 */
interface TranslationResourceProviderInterface
{
    /**
     * Returns the directories containing translation files.
     *
     * Files inside each directory must follow Symfony's naming convention:
     * `domain(+intl-icu)?.locale.format` (e.g. `messages.en.yaml` or
     * `messages+intl-icu.en.yaml`).
     *
     * @return iterable<string>
     */
    public function getDirectories(): iterable;
}
