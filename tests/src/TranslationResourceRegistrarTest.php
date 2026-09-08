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

use Derafu\Translation\SimpleTranslationResourceProvider;
use Derafu\Translation\TranslationResourceRegistrar;
use Derafu\Translation\TranslatorFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

#[CoversClass(TranslationResourceRegistrar::class)]
#[CoversClass(TranslatorFactory::class)]
#[CoversClass(SimpleTranslationResourceProvider::class)]
final class TranslationResourceRegistrarTest extends TestCase
{
    private Translator $translator;

    private TranslationResourceRegistrar $registrar;

    private string $fixturesDir;

    private string $overrideDir;

    private string $errorsDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../fixtures/translations';
        $this->overrideDir = __DIR__ . '/../fixtures/translations-override';
        $this->errorsDir = __DIR__ . '/../fixtures/registrar-errors';

        $this->translator = TranslatorFactory::create('en');
        $this->registrar = new TranslationResourceRegistrar($this->translator);
    }

    public function testRegisterDirectoryDiscoversDomainsAndLocales(): void
    {
        $this->registrar->registerDirectory($this->fixturesDir);

        $domains = $this->registrar->getRegisteredDomains();
        $locales = $this->registrar->getRegisteredLocales();

        sort($domains);
        sort($locales);

        $this->assertSame(
            ['errors+intl-icu', 'legacy', 'messages+intl-icu'],
            $domains
        );
        $this->assertSame(['en', 'es'], $locales);
    }

    public function testRegisteredResourcesAreUsableByTheTranslator(): void
    {
        $this->registrar->registerDirectory($this->fixturesDir);

        $this->assertSame(
            'Welcome John!',
            $this->translator->trans(
                'welcome',
                ['name' => 'John'],
                'messages+intl-icu'
            )
        );

        $this->assertSame(
            'El campo email es requerido',
            $this->translator->trans(
                'validation.required',
                ['field' => 'email'],
                'errors+intl-icu',
                'es'
            )
        );
    }

    public function testRegisterDirectoryThrowsWhenDirectoryDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Translation directory "/nonexistent/directory" does not exist.'
        );

        $this->registrar->registerDirectory('/nonexistent/directory');
    }

    public function testRegisterDirectoryThrowsOnUnrecognizedExtension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unrecognized translation file extension "txt"');

        $this->registrar->registerDirectory($this->errorsDir . '/unsupported-extension');
    }

    public function testRegisterDirectoryThrowsWhenFileDoesNotFollowNamingConvention(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'does not follow the "domain.locale.format" naming convention'
        );

        $this->registrar->registerDirectory($this->errorsDir . '/bad-name');
    }

    public function testLaterDirectoriesOverridePreviousOnesForTheSameKey(): void
    {
        // Register the base directory first and the "override" one after:
        // registration order defines precedence (the last one wins).
        $this->registrar->registerDirectories([
            $this->fixturesDir,
            $this->overrideDir,
        ]);

        // The "welcome" key is overridden by the override directory.
        $this->assertSame(
            'Yo, John!',
            $this->translator->trans(
                'welcome',
                ['name' => 'John'],
                'messages+intl-icu'
            )
        );

        // The rest of the domain's keys aren't lost by the merge.
        $this->assertSame(
            'Goodbye John!',
            $this->translator->trans(
                'goodbye',
                ['name' => 'John'],
                'messages+intl-icu'
            )
        );
    }

    public function testRegisterFromProvidersRegistersEveryProvidersDirectories(): void
    {
        $providers = [
            new SimpleTranslationResourceProvider([$this->fixturesDir]),
            new SimpleTranslationResourceProvider([$this->overrideDir]),
        ];

        $this->registrar->registerFromProviders($providers);

        $this->assertSame(
            'Yo, John!',
            $this->translator->trans(
                'welcome',
                ['name' => 'John'],
                'messages+intl-icu'
            )
        );
    }
}
