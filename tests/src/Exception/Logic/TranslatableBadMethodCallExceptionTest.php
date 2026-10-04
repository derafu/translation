<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Exception\Logic;

use BadMethodCallException;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableBadMethodCallException;
use Derafu\Translation\TranslatableMessage;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(TranslatableBadMethodCallException::class)]
#[CoversClass(TranslatableMessage::class)]
final class TranslatableBadMethodCallExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new TranslatableBadMethodCallException('Test');

        $this->assertInstanceOf(BadMethodCallException::class, $exception);
        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(Throwable::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
    }

    public function testTheMessageCanBeGivenWithNamedParameters(): void
    {
        $exception = new TranslatableBadMethodCallException(['Cannot call {name}.', 'name' => 'run']);

        $this->assertSame('Cannot call run.', $exception->getMessage());
    }
}
