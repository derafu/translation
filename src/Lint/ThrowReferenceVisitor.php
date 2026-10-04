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

use Derafu\Translation\Contract\TranslatableInterface;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use Throwable;

/**
 * Collects the exceptions that a file throws, or declares, that are not
 * translatable.
 *
 * It must run after the names are resolved (`NameResolver`).
 *
 * @internal Used by `ThrowReferenceScanner`.
 */
final class ThrowReferenceVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<ThrowReference>
     */
    private array $references = [];

    /**
     * Classes being read: the class that `self` and `static` mean, or `null`
     * when it is not known.
     *
     * @var list<class-string|null>
     */
    private array $classes = [];

    public function __construct(private readonly string $file)
    {
    }

    /**
     * @return list<ThrowReference>
     */
    public function references(): array
    {
        return $this->references;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Class_) {
            $this->classes[] = $this->enterClass($node);
        }

        return null;
    }

    /**
     * What is made is read when it is left, so the names inside it are already
     * resolved, and the parent of an anonymous class (resolved when the class is
     * entered, which is after the `new` that contains it) is known.
     */
    public function leaveNode(Node $node): null
    {
        if ($node instanceof Class_) {
            array_pop($this->classes);
        } elseif ($node instanceof New_) {
            $this->collectNew($node);
        } elseif ($node instanceof Throw_ && $node->expr instanceof New_) {
            $this->collectUnknownThrow($node, $node->expr);
        }

        return null;
    }

    /**
     * Reports a class that extends an exception that is not translatable, and
     * returns the class for the references inside it.
     *
     * @return class-string|null
     */
    private function enterClass(Class_ $node): ?string
    {
        if ($node->name === null) {
            return $node->extends !== null ? $this->className($node->extends->toString()) : null;
        }

        $class = $node->namespacedName ?? null;
        if ($class === null) {
            return null;
        }

        $declared = $class->toString();
        $loaded = $this->className($declared);

        if ($node->extends !== null) {
            // What counts is the class that is declared: it can extend a native
            // exception and be translatable itself. If it can not be loaded, its
            // parent is what is known, and a parent that can not be loaded may
            // not be an exception at all.
            $parent = $this->className($node->extends->toString());
            $checked = $loaded ?? $parent;

            if ($checked !== null && $this->isThrowable($checked) && !$this->isTranslatable($checked)) {
                $this->references[] = new ThrowReference(
                    $declared,
                    $node->extends->toString(),
                    $this->file,
                    $node->getStartLine(),
                    true
                );
            }
        }

        return $loaded;
    }

    /**
     * Reports an exception that is made and is not translatable, wherever it is:
     * thrown there, thrown later or given back by a factory.
     */
    private function collectNew(New_ $new): void
    {
        $name = $this->nameOfNew($new);
        $class = $name === null ? null : $this->className($name);

        if ($class !== null && $this->isThrowable($class) && !$this->isTranslatable($class)) {
            $this->references[] = new ThrowReference($class, null, $this->file, $new->getStartLine(), true);
        }
    }

    /**
     * Reports a `throw new` of a class that is not known: what is thrown is an
     * exception, so a class that can not be loaded, or that is a variable, is an
     * exception that is not known.
     */
    private function collectUnknownThrow(Throw_ $throw, New_ $new): void
    {
        if (!$new->class instanceof Name && !$new->class instanceof Class_) {
            $this->references[] = new ThrowReference(
                (new Standard())->prettyPrintExpr($new->class),
                null,
                $this->file,
                $throw->getStartLine(),
                false
            );

            return;
        }

        $name = $this->nameOfNew($new);
        if ($name !== null && $this->className($name) === null) {
            $this->references[] = new ThrowReference($name, null, $this->file, $throw->getStartLine(), false);
        }
    }

    /**
     * The name of the class that is made by a `new`: `null` when it is not
     * known by reading (`new $class`, or `self` where there is no class).
     */
    private function nameOfNew(New_ $new): ?string
    {
        if ($new->class instanceof Class_) {
            return $new->class->extends?->toString();
        }

        if (!$new->class instanceof Name) {
            return null;
        }

        if (!$new->class->isSpecialClassName()) {
            return $new->class->toString();
        }

        $current = $this->classes === [] ? null : $this->classes[array_key_last($this->classes)];
        if ($current === null) {
            return null;
        }

        return $new->class->toLowerString() === 'parent'
            ? (get_parent_class($current) ?: null)
            : $current;
    }

    /**
     * @return class-string|null
     */
    private function className(string $name): ?string
    {
        try {
            return class_exists($name) ? $name : null;
        } catch (Throwable) {
            // A class that can not be loaded (for example, it needs a package
            // that is not installed) is not known.
            return null;
        }
    }

    /**
     * @param class-string $class
     */
    private function isThrowable(string $class): bool
    {
        return (new ReflectionClass($class))->implementsInterface(Throwable::class);
    }

    /**
     * @param class-string $class
     */
    private function isTranslatable(string $class): bool
    {
        return (new ReflectionClass($class))->implementsInterface(TranslatableInterface::class);
    }
}
