<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Lint;

use Derafu\Translation\Contract\TranslationResourceProviderInterface;
use Derafu\Translation\TranslatorFactory;

/**
 * Checks everything that a package needs to be translated, in one call: the
 * messages that its code builds, the catalogues that translate them, and the
 * exceptions that it throws.
 *
 * It uses `MessageReferenceScanner` for the messages and `ThrowReferenceScanner`
 * for the exceptions, and compares the messages with the catalogues of the
 * package, in every domain. It reads the code (nothing runs). With this the test
 * of each package is a few lines and all the logic is here:
 *
 *     $report = (new TranslationAudit())->audit($srcDirectory, new MyProvider());
 *     $this->assertSame([], $report->describe($report->missingTranslations));
 *
 * It only finds facts, in a report, about the code that it reads: it does not say
 * what is a problem. An exception of another package that must be thrown as it
 * is can be allowed, class by class: it is an explicit decision of whoever uses
 * this, and it is not made here.
 *
 * Part of the lint tools: it is for tools and tests, never for the code that
 * runs the package. It needs `nikic/php-parser`.
 */
final class TranslationAudit
{
    /**
     * Audits a package.
     *
     * @param string $directory Directory with the code of the package.
     * @param TranslationResourceProviderInterface|iterable<TranslationResourceProviderInterface> $providers
     * The catalogues of the package.
     * @param string $locale The locale of the catalogues that is checked.
     * @param list<string> $allowedThrowables Classes of exceptions that are not
     * translatable and are allowed, by their full name.
     * @param list<MessageMethod> $messageMethods The methods of the package that
     * receive the id of a message: their calls are messages.
     * @throws \InvalidArgumentException If the directory does not exist.
     * @throws \PhpParser\Error If a file can not be parsed.
     */
    public function audit(
        string $directory,
        TranslationResourceProviderInterface|iterable $providers,
        string $locale = 'es',
        array $allowedThrowables = [],
        array $messageMethods = []
    ): TranslationAuditReport {
        $providers = $providers instanceof TranslationResourceProviderInterface
            ? [$providers]
            : $providers;

        $catalogue = TranslatorFactory::create($locale, [], $providers)->getCatalogue($locale);

        $references = (new MessageReferenceScanner($messageMethods))->scanDirectory($directory);

        $dynamic = [];
        $missing = [];
        $used = [];
        foreach ($references as $reference) {
            if ($reference->isDynamic()) {
                $dynamic[] = $reference;

                continue;
            }

            $used[(string) $reference->domain][] = (string) $reference->id;
            if (!$catalogue->has((string) $reference->id, (string) $reference->domain)) {
                $missing[] = $reference;
            }
        }

        $notUsed = [];
        $domains = $catalogue->getDomains();
        sort($domains);
        foreach ($domains as $domain) {
            foreach (array_diff(array_keys($catalogue->all($domain)), $used[$domain] ?? []) as $id) {
                $notUsed[] = ['domain' => $domain, 'id' => (string) $id];
            }
        }

        $notTranslatable = array_values(array_filter(
            (new ThrowReferenceScanner())->scanDirectory($directory),
            fn (ThrowReference $reference) => !in_array($reference->class, $allowedThrowables, true)
        ));

        return new TranslationAuditReport($directory, $dynamic, $missing, $notUsed, $notTranslatable, $references === []);
    }
}
