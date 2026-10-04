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
use Derafu\Translation\TranslatableMessage;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use Throwable;

/**
 * Collects the messages that a file builds to be translated.
 *
 * It must run after the names are resolved (`NameResolver`).
 *
 * @internal Used by `MessageReferenceScanner`.
 */
final class MessageReferenceVisitor extends NodeVisitorAbstract
{
    /**
     * Domain of the messages when it is not given (`TranslatableMessage`).
     */
    private const DEFAULT_DOMAIN = 'messages';

    /**
     * @var list<MessageReference>
     */
    private array $references = [];

    /**
     * Classes being read: the class that `self` and `static` mean, or `null`
     * when it is not known.
     *
     * @var list<class-string|null>
     */
    private array $classes = [];

    /**
     * Names of the parameters of the constructors being read.
     *
     * @var list<list<string>>
     */
    private array $constructorParameters = [];

    /**
     * @var array<class-string, bool>
     */
    private array $translatable = [];

    public function __construct(private readonly string $file)
    {
    }

    /**
     * @return list<MessageReference>
     */
    public function references(): array
    {
        return $this->references;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Class_ || $node instanceof Trait_) {
            $this->classes[] = $this->classNameOf($node);
        } elseif ($node instanceof ClassMethod && $node->name->toLowerString() === '__construct') {
            $this->constructorParameters[] = array_values(array_filter(array_map(
                fn (Node\Param $param) => $param->var instanceof Variable && is_string($param->var->name)
                    ? $param->var->name
                    : null,
                $node->params
            )));
            $this->collectDefaultMessage($node);
        } elseif ($node instanceof New_ && !$node->class instanceof Class_) {
            $this->collectNew($node);
        } elseif ($node instanceof StaticCall) {
            $this->collectParentConstructor($node);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Class_ || $node instanceof Trait_) {
            array_pop($this->classes);
        } elseif ($node instanceof ClassMethod && $node->name->toLowerString() === '__construct') {
            array_pop($this->constructorParameters);
        } elseif ($node instanceof New_ && $node->class instanceof Class_) {
            // The parent of an anonymous class is resolved when the class is
            // entered, which is after the `new` that contains it.
            $this->collectNew($node);
        }

        return null;
    }

    /**
     * The class that a class declaration is for the references inside it: its
     * own name, or its parent when it is anonymous (what it is, for the checks).
     *
     * @return class-string|null
     */
    private function classNameOf(Class_|Trait_ $node): ?string
    {
        if ($node instanceof Class_ && $node->name === null) {
            return $node->extends !== null ? $this->className($node->extends->name ?? $node->extends->toString()) : null;
        }

        $name = $node->namespacedName ?? null;

        return $name instanceof Name ? $this->className($name->toString()) : null;
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
            // that is not installed) is not known to be translatable.
            return null;
        }
    }

    private function collectNew(New_ $node): void
    {
        $class = $this->classOfNew($node);
        if ($class === null) {
            return;
        }

        if ($class === TranslatableMessage::class) {
            $this->collectTranslatableMessage($node, $class);

            return;
        }

        if (!$this->isTranslatable($class)) {
            return;
        }

        $argument = $this->argument($class, $node->args, 'message');
        if ($argument !== null) {
            $this->collectMessage($node, $argument, $class, $this->domainOf($class));
        }
    }

    /**
     * @return class-string|null
     */
    private function classOfNew(New_ $node): ?string
    {
        if ($node->class instanceof Class_) {
            return $node->class->extends !== null
                ? $this->className($node->class->extends->toString())
                : null;
        }

        if (!$node->class instanceof Name) {
            return null;
        }

        return $this->resolve($node->class);
    }

    /**
     * @return class-string|null
     */
    private function resolve(Name $name): ?string
    {
        if ($name->isSpecialClassName()) {
            $current = $this->classes === [] ? null : $this->classes[array_key_last($this->classes)];

            if ($current === null) {
                return null;
            }

            return match ($name->toLowerString()) {
                'parent' => ($parent = get_parent_class($current)) !== false ? $parent : null,
                default => $current,
            };
        }

        return $this->className($name->toString());
    }

    /**
     * @param class-string $class
     */
    private function collectTranslatableMessage(New_ $node, string $class): void
    {
        $domainArgument = $this->argument($class, $node->args, 'domain');
        $domain = $domainArgument === null ? self::DEFAULT_DOMAIN : $this->literalDomain($domainArgument);

        $messageArgument = $this->argument($class, $node->args, 'message');
        if ($messageArgument !== null) {
            $this->collectMessage($node, $messageArgument, $class, $domain);
        }
    }

    private function literalDomain(Arg $argument): ?string
    {
        $value = $argument->value;

        if ($value instanceof String_) {
            return $value->value;
        }

        if ($value instanceof ConstFetch && $value->name->toLowerString() === 'null') {
            return self::DEFAULT_DOMAIN;
        }

        return null;
    }

    /**
     * `parent::__construct(...)` in a translatable class: what it is given is
     * the message of that class, unless it is a parameter of the constructor
     * that is passed on (the message is then the one of whoever calls it).
     */
    private function collectParentConstructor(StaticCall $node): void
    {
        if (
            !$node->class instanceof Name
            || $node->class->toLowerString() !== 'parent'
            || !$node->name instanceof Identifier
            || $node->name->toLowerString() !== '__construct'
        ) {
            return;
        }

        $class = $this->classes === [] ? null : $this->classes[array_key_last($this->classes)];
        if ($class === null || !$this->isTranslatable($class)) {
            return;
        }

        $parent = get_parent_class($class);
        if ($parent === false) {
            return;
        }

        $argument = $this->argument($parent, $node->args, 'message');
        if ($argument === null || $this->isPassedOn($argument)) {
            return;
        }

        $this->collectMessage($node, $argument, $class, $this->domainOf($class));
    }

    private function isPassedOn(Arg $argument): bool
    {
        $parameters = $this->constructorParameters === [] ? [] : $this->constructorParameters[array_key_last($this->constructorParameters)];

        return !$argument->unpack
            && $argument->value instanceof Variable
            && is_string($argument->value->name)
            && in_array($argument->value->name, $parameters, true);
    }

    /**
     * The default of the `$message` parameter of the constructor of a
     * translatable class is a message of that class.
     */
    private function collectDefaultMessage(ClassMethod $node): void
    {
        $class = $this->classes === [] ? null : $this->classes[array_key_last($this->classes)];
        if ($class === null || !$this->isTranslatable($class)) {
            return;
        }

        foreach ($node->params as $param) {
            if (
                $param->var instanceof Variable
                && $param->var->name === 'message'
                && $param->default instanceof String_
            ) {
                $this->references[] = new MessageReference(
                    $param->default->value,
                    $this->domainOf($class),
                    $class,
                    $this->file,
                    $param->getStartLine()
                );
            }
        }
    }

    /**
     * Adds the reference for the message given in an argument.
     *
     * @param class-string $class
     */
    private function collectMessage(Node $at, Arg $argument, string $class, ?string $domain): void
    {
        $value = $argument->value;

        // A message that is already a translatable message is found on its
        // own, where it is built.
        if (!$argument->unpack && $value instanceof New_) {
            return;
        }

        $id = null;
        if (!$argument->unpack) {
            if ($value instanceof String_) {
                $id = $value->value;
            } elseif ($value instanceof Array_ && $value->items !== []) {
                $first = $value->items[0];
                if ($first->key === null && !$first->unpack && $first->value instanceof String_) {
                    $id = $first->value->value;
                }
            }
        }

        $this->references[] = new MessageReference($id, $domain, $class, $this->file, $at->getStartLine());
    }

    /**
     * Finds the argument that a constructor receives for one of its parameters,
     * by the name of the parameter: the argument with that name or the one in
     * the position of the parameter. A constructor without that parameter does
     * not take that argument, whatever it is given.
     *
     * @param class-string $class Class whose constructor is called.
     * @param array<Arg|Node\VariadicPlaceholder> $args
     */
    private function argument(string $class, array $args, string $parameter): ?Arg
    {
        $position = $this->positionOf($class, $parameter);
        if ($position === null) {
            return null;
        }

        foreach ($args as $index => $arg) {
            if (!$arg instanceof Arg) {
                continue;
            }
            if ($arg->name !== null) {
                if ($arg->name->toString() === $parameter) {
                    return $arg;
                }
                continue;
            }
            if ($index === $position || $arg->unpack) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * Position of a parameter in the constructor of a class, `null` if it has
     * no constructor or no such parameter.
     *
     * @param class-string $class
     */
    private function positionOf(string $class, string $parameter): ?int
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $candidate) {
            if ($candidate->getName() === $parameter) {
                return $candidate->getPosition();
            }
        }

        return null;
    }

    /**
     * Whether a class is a throwable that can be translated.
     *
     * @param class-string $class
     */
    private function isTranslatable(string $class): bool
    {
        return $this->translatable[$class] ??= $this->checkTranslatable($class);
    }

    /**
     * @param class-string $class
     */
    private function checkTranslatable(string $class): bool
    {
        try {
            $reflection = new ReflectionClass($class);

            return $reflection->implementsInterface(Throwable::class)
                && $reflection->implementsInterface(TranslatableInterface::class);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Domain of the messages of a translatable class: the default of its
     * `$defaultDomain` property, `null` if it has none.
     *
     * @param class-string $class
     */
    private function domainOf(string $class): ?string
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->hasProperty('defaultDomain')) {
            return null;
        }

        $domain = $reflection->getProperty('defaultDomain')->getDefaultValue();

        return is_string($domain) ? $domain : null;
    }
}
