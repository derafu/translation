<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation;

use Derafu\Translation\Translation\TranslationResourceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Exercises the standalone and resources-only production YAML. Application
 * locale settings, providers and translator ownership are supplied by YAML.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function loadContainer(string $file = 'translation-services.yaml'): ContainerBuilder
    {
        $root = dirname(__DIR__, 2);
        $container = new ContainerBuilder();
        $container->setParameter('test.project_dir', $root);
        (new YamlFileLoader($container, new FileLocator([
            $root . '/resources/config',
            $root . '/tests/fixtures/config',
        ])))->load($file);

        return $container;
    }

    private function compile(ContainerBuilder $container): void
    {
        foreach ([TranslatorInterface::class, TranslationResourceProvider::class] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            }
        }
        $container->compile();
    }

    private function translator(ContainerBuilder $container): Translator
    {
        $this->compile($container);
        $translator = $container->get(TranslatorInterface::class);
        self::assertInstanceOf(Translator::class, $translator);

        return $translator;
    }

    public function testTheConfigurationProvidesOneSharedTranslatorWithEnglishDefaults(): void
    {
        $container = $this->loadContainer();
        self::assertTrue($container->hasDefinition(TranslatorInterface::class));
        self::assertFalse($container->hasAlias(TranslatorInterface::class));
        self::assertFalse($container->has(Translator::class));
        self::assertFalse($container->has('translator'));
        $translator = $this->translator($container);

        self::assertSame($translator, $container->get(TranslatorInterface::class));
        self::assertSame('en', $translator->getLocale());
        self::assertSame([], $translator->getFallbackLocales());
        self::assertInstanceOf(TranslationResourceProvider::class, $container->get(TranslationResourceProvider::class));
    }

    public function testTheContractUsesTheLocaleAndFallbacksConfiguredByTheConsumer(): void
    {
        $translator = $this->translator($this->loadContainer('spanish.yaml'));

        self::assertSame('es', $translator->getLocale());
        self::assertSame(['en'], $translator->getFallbackLocales());
        self::assertSame('El arreglo del mensaje no puede estar vacío.', $translator->trans('Message array cannot be empty.', [], 'errors'));
    }

    public function testTheResourcesOnlyConfigurationDoesNotCreateOrRequireATranslator(): void
    {
        $container = $this->loadContainer('translation-resources-services.yaml');
        self::assertFalse($container->has(TranslatorInterface::class));
        self::assertFalse($container->has(Translator::class));
        self::assertFalse($container->has('translator'));
        self::assertFalse($container->hasParameter('derafu.translation.default_locale'));
        $this->compile($container);

        self::assertInstanceOf(TranslationResourceProvider::class, $container->get(TranslationResourceProvider::class));
    }

    public function testTheTaggedProviderExposesThePackageCatalogue(): void
    {
        $container = $this->loadContainer('translation-resources-services.yaml');
        self::assertArrayHasKey(
            TranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $this->compile($container);
        $provider = $container->get(TranslationResourceProvider::class);
        self::assertInstanceOf(TranslationResourceProvider::class, $provider);
        $directories = [...$provider->getDirectories()];
        self::assertCount(1, $directories);
        self::assertSame(realpath(dirname(__DIR__, 2) . '/resources/translations'), realpath($directories[0]));
        self::assertFileExists($directories[0] . '/errors+intl-icu.es.php');
    }

    public function testTheTranslatorConsumesTheTaggedPackageResources(): void
    {
        $translator = $this->translator($this->loadContainer('spanish.yaml'));

        self::assertSame('El arreglo del mensaje no puede estar vacío.', $translator->trans('Message array cannot be empty.', [], 'errors'));
        self::assertSame(
            'El directorio de traducciones "/missing" no existe.',
            $translator->trans('Translation directory "{directory}" does not exist.', ['directory' => '/missing'], 'errors')
        );
    }

    public function testTheConsumerCanAddResourcesWithoutReplacingThePackageProvider(): void
    {
        $translator = $this->translator($this->loadContainer('application.yaml'));

        self::assertSame('¡Bienvenido Ada!', $translator->trans('welcome', ['name' => 'Ada']));
        self::assertSame('El arreglo del mensaje no puede estar vacío.', $translator->trans('Message array cannot be empty.', [], 'errors'));
        self::assertSame('Welcome Ada!', $translator->trans('welcome', ['%name%' => 'Ada'], 'legacy', 'fr'));
    }

    public function testTheResourcesOnlyConfigurationPreservesTheConsumersTranslator(): void
    {
        $container = $this->loadContainer('consumer-translator.yaml');
        self::assertFalse($container->hasParameter('derafu.translation.default_locale'));
        $translator = $this->translator($container);

        self::assertSame($translator, $container->get(TranslatorInterface::class));
        self::assertSame('fr', $translator->getLocale());
        self::assertSame(['es', 'en'], $translator->getFallbackLocales());
        self::assertSame('El arreglo del mensaje no puede estar vacío.', $translator->trans('Message array cannot be empty.', [], 'errors'));
    }

    public function testTheConfiguredFallbackIsUsedForActualTranslations(): void
    {
        $translator = $this->translator($this->loadContainer('english-fallback.yaml'));

        self::assertSame('fr', $translator->getLocale());
        self::assertSame(['en'], $translator->getFallbackLocales());
        self::assertSame('Welcome Ada!', $translator->trans('welcome', ['name' => 'Ada']));
        self::assertSame('¡Bienvenido Ada!', $translator->trans('welcome', ['name' => 'Ada'], locale: 'es'));
    }
}
