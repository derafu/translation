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

use Derafu\Translation\SimpleTranslationResourceProvider;
use Derafu\Translation\Translation\TranslationResourceProvider;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Exercises the shipped YAML in a real container. The consumer provides its
 * translator and locale, as documented by translation-services.yaml.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/resources/config')))
            ->load('translation-services.yaml');

        return $container;
    }

    private function registerConsumerTranslator(ContainerBuilder $container): void
    {
        $container->register(Translator::class, Translator::class)
            ->setFactory([TranslatorFactory::class, 'create'])
            ->setArguments(['es', ['en'], new TaggedIteratorArgument('derafu_translation.resource_provider')]);
    }

    private function compile(ContainerBuilder $container): void
    {
        // Keep all declared services reachable: private-service removal must
        // not hide invalid definitions from the construction checks.
        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }
        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }
        $container->compile();
    }

    public function testTheConfigurationLeavesTranslatorCreationToTheConsumer(): void
    {
        $container = $this->loadContainer();

        self::assertTrue($container->hasDefinition(TranslationResourceProvider::class));
        self::assertTrue($container->hasAlias(TranslatorInterface::class));
        self::assertSame(Translator::class, (string) $container->getAlias(TranslatorInterface::class));
        self::assertFalse($container->hasDefinition(Translator::class));
        self::assertFalse($container->has('translator'));
    }

    public function testEveryConfiguredServiceAndAliasCanBeConstructed(): void
    {
        $container = $this->loadContainer();
        $this->registerConsumerTranslator($container);
        $this->compile($container);

        self::assertInstanceOf(TranslationResourceProvider::class, $container->get(TranslationResourceProvider::class));
        self::assertInstanceOf(Translator::class, $container->get(Translator::class));
        self::assertInstanceOf(TranslatorInterface::class, $container->get(TranslatorInterface::class));
    }

    public function testTheContractResolvesToTheConsumersTranslatorAndLocale(): void
    {
        $container = $this->loadContainer();
        $this->registerConsumerTranslator($container);
        $this->compile($container);
        $translator = $container->get(Translator::class);
        self::assertInstanceOf(Translator::class, $translator);
        self::assertSame($translator, $container->get(TranslatorInterface::class));
        self::assertSame('es', $translator->getLocale());
        self::assertSame(['en'], $translator->getFallbackLocales());
    }

    public function testTheTaggedProviderExposesThePackageCatalogue(): void
    {
        $container = $this->loadContainer();
        self::assertArrayHasKey(
            TranslationResourceProvider::class,
            $container->findTaggedServiceIds('derafu_translation.resource_provider')
        );
        $this->registerConsumerTranslator($container);
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
        $container = $this->loadContainer();
        $this->registerConsumerTranslator($container);
        $this->compile($container);
        $translator = $container->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        self::assertSame('El arreglo del mensaje no puede estar vacío.', $translator->trans('Message array cannot be empty.', [], 'errors'));
        self::assertSame(
            'El directorio de traducciones "/missing" no existe.',
            $translator->trans('Translation directory "{directory}" does not exist.', ['directory' => '/missing'], 'errors')
        );
    }

    public function testTheConsumerCanAddResourcesWithoutReplacingThePackageProvider(): void
    {
        $container = $this->loadContainer();
        $container->register('app.translation_resources', SimpleTranslationResourceProvider::class)
            ->setArguments([[__DIR__ . '/../fixtures/translations']])
            ->addTag('derafu_translation.resource_provider');
        $this->registerConsumerTranslator($container);
        $this->compile($container);
        $translator = $container->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        self::assertSame('¡Bienvenido Ada!', $translator->trans('welcome', ['name' => 'Ada']));
        self::assertSame('El arreglo del mensaje no puede estar vacío.', $translator->trans('Message array cannot be empty.', [], 'errors'));
        self::assertSame('Welcome Ada!', $translator->trans('welcome', ['%name%' => 'Ada'], 'legacy', 'fr'));
    }
}
