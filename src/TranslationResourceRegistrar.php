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
use InvalidArgumentException;
use Symfony\Component\Translation\Translator;

/**
 * Discovers translation files in directories and registers them as
 * resources of a Symfony `Translator`.
 *
 * This is the piece Symfony's standalone translation component does not
 * provide on its own (directory discovery is only wired inside
 * FrameworkBundle). Files must follow Symfony's naming convention:
 *
 *   {domain}(+intl-icu)?.{locale}.{format}
 *
 * e.g. `messages.en.yaml`, `messages+intl-icu.en.yaml`, `errors.es.php`.
 *
 * Registration order is precedence order: when two directories define the
 * same key for the same domain/locale, the last one registered wins.
 */
final class TranslationResourceRegistrar
{
    /**
     * Maps file extensions to the loader format registered on the
     * translator (see `TranslatorFactory`).
     */
    private const array EXTENSION_FORMATS = [
        'yaml' => 'yaml',
        'yml' => 'yaml',
        'json' => 'json',
        'php' => 'php',
        'xlf' => 'xliff',
        'xliff' => 'xliff',
        'po' => 'po',
        'mo' => 'mo',
        'csv' => 'csv',
        'ini' => 'ini',
    ];

    /**
     * @var array<string, true>
     */
    private array $locales = [];

    /**
     * @var array<string, true>
     */
    private array $domains = [];

    public function __construct(
        private readonly Translator $translator
    ) {
    }

    /**
     * Registers every translation file found directly inside a directory.
     *
     * @throws InvalidArgumentException If the directory does not exist, a
     * file does not follow the naming convention, or its extension is not
     * supported.
     */
    public function registerDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException(
                sprintf('Translation directory "%s" does not exist.', $directory)
            );
        }

        $files = glob(rtrim($directory, '/') . '/*.*') ?: [];

        foreach ($files as $file) {
            $this->registerFile($file);
        }
    }

    /**
     * Registers every translation file found in each of the directories.
     *
     * @param iterable<string> $directories
     */
    public function registerDirectories(iterable $directories): void
    {
        foreach ($directories as $directory) {
            $this->registerDirectory($directory);
        }
    }

    /**
     * Registers the directories declared by each resource provider.
     *
     * Meant to consume a DI tagged iterator of
     * `TranslationResourceProviderInterface` implementations.
     *
     * @param iterable<TranslationResourceProviderInterface> $providers
     */
    public function registerFromProviders(iterable $providers): void
    {
        foreach ($providers as $provider) {
            $this->registerDirectories($provider->getDirectories());
        }
    }

    /**
     * Returns every locale registered so far.
     *
     * @return array<string>
     */
    public function getRegisteredLocales(): array
    {
        return array_keys($this->locales);
    }

    /**
     * Returns every domain registered so far.
     *
     * @return array<string>
     */
    public function getRegisteredDomains(): array
    {
        return array_keys($this->domains);
    }

    /**
     * Parses a single file's name and registers it as a translator resource.
     */
    private function registerFile(string $file): void
    {
        $parts = explode('.', basename($file));

        if (count($parts) < 3) {
            throw new InvalidArgumentException(sprintf(
                'Translation file "%s" does not follow the "domain.locale.format" naming convention.',
                $file
            ));
        }

        $extension = strtolower(array_pop($parts));
        $locale = array_pop($parts);
        $domain = implode('.', $parts);

        if (!isset(self::EXTENSION_FORMATS[$extension])) {
            throw new InvalidArgumentException(sprintf(
                'Unrecognized translation file extension "%s" for file "%s". Supported extensions: %s.',
                $extension,
                $file,
                implode(', ', array_keys(self::EXTENSION_FORMATS))
            ));
        }

        $this->translator->addResource(
            self::EXTENSION_FORMATS[$extension],
            $file,
            $locale,
            $domain
        );

        $this->locales[$locale] = true;
        $this->domains[$domain] = true;
    }
}
