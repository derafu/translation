<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation;

use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(TranslatorFactory::class)]
final class TranslatorFactoryTest extends TestCase
{
    public function testCreatesTranslatorWithDefaultLocale(): void
    {
        $translator = TranslatorFactory::create('en');

        $this->assertSame('en', $translator->getLocale());
        $this->assertSame([], $translator->getFallbackLocales());
    }

    public function testAppliesFallbackLocales(): void
    {
        $translator = TranslatorFactory::create('es', ['es', 'en']);

        $this->assertSame(['es', 'en'], $translator->getFallbackLocales());
    }

    public function testRegistersLoaderForEveryFormatSupportedByTheRegistrar(): void
    {
        $translator = TranslatorFactory::create('en');

        $getLoaders = new ReflectionMethod($translator, 'getLoaders');
        $loaders = $getLoaders->invoke($translator);

        $expectedFormats = [
            'yaml', 'json', 'php', 'xliff', 'po', 'mo', 'csv', 'ini',
        ];

        foreach ($expectedFormats as $format) {
            $this->assertArrayHasKey(
                $format,
                $loaders,
                sprintf('Expected a loader registered for the "%s" format.', $format)
            );
        }
    }
}
