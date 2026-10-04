<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Translation;

use Derafu\Translation\Lint\MessageReference;
use Derafu\Translation\Lint\TranslationAudit;
use Derafu\Translation\Translation\TranslationResourceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * This package is translated like the ones that use it: every message has its
 * Spanish translation, the catalogue has nothing the code does not use, and
 * every exception that it throws is translatable.
 *
 * The audit is the same one the other packages use. The only thing that is
 * different here: the trait of the translatable exceptions wraps in a
 * `TranslatableMessage` the message that whoever throws the exception gives it,
 * so those two messages are not literals, by nature. They are fixed here by the
 * function and the whole call, so any other message that is not a literal makes
 * this test fail, in this file or in any other, and one that is fixed has to be
 * taken out of the list.
 */
#[CoversNothing]
final class TranslationMessagesTest extends TestCase
{
    public function testThePackageIsTranslated(): void
    {
        $report = (new TranslationAudit())->audit(dirname(__DIR__, 3) . '/src', new TranslationResourceProvider());

        $this->assertFalse($report->nothingFound);
        $this->assertSame([], $report->describe($report->missingTranslations));
        $this->assertSame([], $report->describe($report->notUsedBySources));
        $this->assertSame([], $report->describe($report->notTranslatable));

        $this->assertSame(
            [
                'Derafu\\Translation\\Trait\\TranslatableExceptionTrait::normalizeMessage: '
                    . 'new \\Derafu\\Translation\\TranslatableMessage($msg, $message, $this->defaultDomain, $this->defaultLocale)',
                'Derafu\\Translation\\Trait\\TranslatableExceptionTrait::normalizeMessage: '
                    . 'new \\Derafu\\Translation\\TranslatableMessage($message, [], $this->defaultDomain, $this->defaultLocale)',
            ],
            array_map(fn (MessageReference $reference) => $reference->identity(), $report->dynamicMessages)
        );
    }
}
