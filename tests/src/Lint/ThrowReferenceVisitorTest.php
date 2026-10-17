<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Lint;

use Derafu\Translation\Lint\ThrowReference;
use Derafu\Translation\Lint\ThrowReferenceVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The visitor reads the classes by their full name, that the resolver of names
 * gives to each declaration. A class that comes without it is a class that is
 * not known: it is not read, and the references inside it do not depend on a
 * class that was not identified.
 *
 * The scanner always uses the resolver, so this is tested with the visitor
 * alone.
 */
#[CoversClass(ThrowReferenceVisitor::class)]
#[UsesClass(ThrowReference::class)]
final class ThrowReferenceVisitorTest extends TestCase
{
    private const CODE = <<<'PHP'
        <?php

        namespace Scanned;

        final class Declared extends \RuntimeException
        {
        }
        PHP;

    /**
     * @return list<ThrowReference>
     */
    private function references(bool $withTheResolver): array
    {
        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse(self::CODE);
        $this->assertNotNull($ast);

        $visitor = new ThrowReferenceVisitor('code.php');
        $traverser = $withTheResolver
            ? new NodeTraverser(new NameResolver(), $visitor)
            : new NodeTraverser($visitor)
        ;
        $traverser->traverse($ast);

        return $visitor->references();
    }

    public function testAClassWithoutItsFullNameIsNotRead(): void
    {
        $this->assertSame([], $this->references(false));
    }

    public function testTheSameClassWithItsFullNameIsRead(): void
    {
        // Control: the class that is declared is the one that is read, and it
        // extends an exception that is not translatable.
        $references = $this->references(true);

        $this->assertCount(1, $references);
        $this->assertSame('Scanned\\Declared', $references[0]->class);
    }
}
