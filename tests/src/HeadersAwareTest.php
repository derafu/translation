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

use Derafu\Translation\Contract\HeadersAwareInterface;
use Derafu\Translation\Trait\HeadersAwareTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An object with the trait carries headers, with none by default, so a package
 * that reads them (one of HTTP) does not need to check that they exist first.
 */
#[CoversTrait(HeadersAwareTrait::class)]
final class HeadersAwareTest extends TestCase
{
    private function subject(): HeadersAwareInterface
    {
        return new class () implements HeadersAwareInterface {
            use HeadersAwareTrait;
        };
    }

    #[Test]
    public function withNoHeadersSetThereAreNone(): void
    {
        $this->assertSame([], $this->subject()->getHeaders());
    }

    #[Test]
    public function theHeadersThatWereSetAreTheOnesThatAreGiven(): void
    {
        $subject = $this->subject();

        $subject->setHeaders(['WWW-Authenticate' => 'Bearer realm="API"']);

        $this->assertSame(['WWW-Authenticate' => 'Bearer realm="API"'], $subject->getHeaders());
    }

    #[Test]
    public function settingTheHeadersAgainReplacesTheOnesThatWereThereBefore(): void
    {
        $subject = $this->subject();

        $subject->setHeaders(['Retry-After' => '60']);
        $subject->setHeaders(['WWW-Authenticate' => 'Basic realm="API"']);

        $this->assertSame(['WWW-Authenticate' => 'Basic realm="API"'], $subject->getHeaders());
    }

    #[Test]
    public function settingTheHeadersGivesTheSameObjectBack(): void
    {
        $subject = $this->subject();

        $this->assertSame($subject, $subject->setHeaders(['Retry-After' => '60']));
    }

    #[Test]
    public function aHeaderCanHaveSeveralValues(): void
    {
        $subject = $this->subject();

        $subject->setHeaders(['Set-Cookie' => ['a=1', 'b=2'], 'Retry-After' => '60']);

        $this->assertSame(['Set-Cookie' => ['a=1', 'b=2'], 'Retry-After' => '60'], $subject->getHeaders());
    }
}
