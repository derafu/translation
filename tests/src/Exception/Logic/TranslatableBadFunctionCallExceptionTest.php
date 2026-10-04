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

use BadFunctionCallException;
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableBadFunctionCallException;
use Derafu\Translation\TranslatableMessage;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(TranslatableBadFunctionCallException::class)]
#[CoversClass(TranslatableMessage::class)]
final class TranslatableBadFunctionCallExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new TranslatableBadFunctionCallException('Test');

        $this->assertInstanceOf(BadFunctionCallException::class, $exception);
        $this->assertInstanceOf(LogicException::class, $exception);
        $this->assertInstanceOf(Throwable::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
    }

    public function testTheMessageCanBeGivenWithNamedParameters(): void
    {
        $exception = new TranslatableBadFunctionCallException(['Cannot call {name}.', 'name' => 'run']);

        $this->assertSame('Cannot call run.', $exception->getMessage());
    }
}
