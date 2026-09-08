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

use Derafu\Translation\TranslationResourceRegistrar;
use Derafu\Translation\TranslatorFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

/**
 * End-to-end tests wiring `TranslatorFactory` + `TranslationResourceRegistrar`
 * with a real `Symfony\Component\Translation\Translator`, to prove the
 * behavior the redesign relies on: ICU formatting only activates for
 * `+intl-icu` domains, and locale fallback works as configured.
 */
#[CoversClass(TranslatorFactory::class)]
#[CoversClass(TranslationResourceRegistrar::class)]
final class TranslationIntegrationTest extends TestCase
{
    private Translator $translator;

    protected function setUp(): void
    {
        $this->translator = TranslatorFactory::create('en', ['en', 'es']);

        $registrar = new TranslationResourceRegistrar($this->translator);
        $registrar->registerDirectory(__DIR__ . '/../fixtures/translations');
    }

    public function testIcuPluralizationOnlyWorksOnIntlIcuDomain(): void
    {
        $this->assertSame(
            'No messages',
            $this->translator->trans('messages', ['count' => 0], 'messages+intl-icu')
        );

        $this->assertSame(
            'One message',
            $this->translator->trans('messages', ['count' => 1], 'messages+intl-icu')
        );

        $this->assertSame(
            '5 messages',
            $this->translator->trans('messages', ['count' => 5], 'messages+intl-icu')
        );
    }

    public function testIcuSelectAndNestedPlaceholders(): void
    {
        $this->assertSame(
            'She works 2 times',
            $this->translator->trans('template', [
                'gender' => 'female',
                'action' => 'works',
                'count' => 2,
            ], 'messages+intl-icu')
        );

        $this->assertSame(
            'Él trabaja 1 vez',
            $this->translator->trans('template', [
                'gender' => 'male',
                'action' => 'trabaja',
                'count' => 1,
            ], 'messages+intl-icu', 'es')
        );
    }

    public function testDifferentIntlIcuDomain(): void
    {
        $this->assertSame(
            'The field email is required',
            $this->translator->trans(
                'validation.required',
                ['field' => 'email'],
                'errors+intl-icu'
            )
        );
    }

    public function testPlainDomainAutomaticallyPicksUpIntlIcuRegisteredMessages(): void
    {
        // `MessageCatalogue::get()`/`defines()` check the "{domain}+intl-icu"
        // bucket first and only fall back to the plain "{domain}" if it's not
        // defined there. So callers of trans() don't need to know or pass the
        // "+intl-icu" suffix themselves: it's enough that the file was
        // registered with that suffix in its name (which is what the
        // registrar does, based on the file name).
        $this->assertSame(
            'Welcome John!',
            $this->translator->trans('welcome', ['name' => 'John'], 'messages')
        );
    }

    public function testNonIcuDomainUsesPlainPlaceholderSubstitution(): void
    {
        // Without the "+intl-icu" suffix, Symfony uses the legacy formatter
        // (`strtr()` via `IdentityTranslator`), which expects parameter keys
        // already delimited with "%...%", unlike ICU which uses the bare
        // parameter name (see the other tests using "+intl-icu" domains).
        $this->assertSame(
            'Welcome John!',
            $this->translator->trans('welcome', ['%name%' => 'John'], 'legacy')
        );
    }

    public function testNonIcuDomainDoesNotExpandIcuStylePlaceholders(): void
    {
        // The "legacy" domain has no "+intl-icu" suffix, so Symfony uses
        // %param% substitution instead of MessageFormatter. A message
        // written in ICU syntax is returned as-is, unexpanded.
        $this->assertSame(
            '{count, plural, one{# item} other{# items}}',
            $this->translator->trans('raw_icu_placeholder', [], 'legacy')
        );
    }

    public function testLocaleFallbackToConfiguredFallbackChain(): void
    {
        $this->translator->setLocale('fr');

        $this->assertSame(
            'Welcome John!',
            $this->translator->trans('welcome', ['%name%' => 'John'], 'legacy')
        );
    }

    public function testMissingTranslationReturnsTheMessageId(): void
    {
        $this->assertSame(
            'missing.key',
            $this->translator->trans('missing.key', [], 'messages+intl-icu')
        );
    }
}
