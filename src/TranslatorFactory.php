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
use Symfony\Component\Translation\Loader\CsvFileLoader;
use Symfony\Component\Translation\Loader\IniFileLoader;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

/**
 * Builds a Symfony `Translator` with every file loader supported by
 * `TranslationResourceRegistrar` already registered, so callers don't have
 * to remember to wire each one by hand.
 */
final class TranslatorFactory
{
    /**
     * @param array<string> $fallbackLocales
     * @param iterable<TranslationResourceProviderInterface> $resourceProviders
     * Resource providers to register immediately, as part of building the
     * translator. Registration happens here — not via a separately fetched
     * `TranslationResourceRegistrar` — specifically so it always runs
     * whenever a `Translator` is built, regardless of whether anything else
     * in the container happens to reference the registrar directly. A
     * registrar with nothing depending on it is never instantiated by a DI
     * container, so its resources would silently never load.
     */
    public static function create(
        string $defaultLocale,
        array $fallbackLocales = [],
        iterable $resourceProviders = []
    ): Translator {
        $translator = new Translator($defaultLocale);

        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addLoader('json', new JsonFileLoader());
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addLoader('xliff', new XliffFileLoader());
        $translator->addLoader('po', new PoFileLoader());
        $translator->addLoader('mo', new MoFileLoader());
        $translator->addLoader('csv', new CsvFileLoader());
        $translator->addLoader('ini', new IniFileLoader());

        if ($fallbackLocales !== []) {
            $translator->setFallbackLocales($fallbackLocales);
        }

        $providers = is_array($resourceProviders)
            ? $resourceProviders
            : iterator_to_array($resourceProviders, false);

        if ($providers !== []) {
            (new TranslationResourceRegistrar($translator))
                ->registerFromProviders($providers)
            ;
        }

        return $translator;
    }
}
