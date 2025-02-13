<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Exception\Runtime;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Runtime\TranslatableUnexpectedValueException;
use Derafu\Translation\TranslatableMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;
use UnexpectedValueException;

#[CoversClass(TranslatableUnexpectedValueException::class)]
#[CoversClass(TranslatableMessage::class)]
final class TranslatableUnexpectedValueExceptionTest extends TestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new TranslatableUnexpectedValueException('Test');

        $this->assertInstanceOf(UnexpectedValueException::class, $exception);
        $this->assertInstanceOf(Throwable::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
    }
}
