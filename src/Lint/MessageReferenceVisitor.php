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
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;
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

    /**
     * Names of the classes and traits being read, as they are declared.
     *
     * @var list<string>
     */
    private array $scopes = [];

    /**
     * Names of the functions being read: a method (`Class::method`), a function
     * or a closure.
     *
     * @var list<string>
     */
    private array $functions = [];

    /**
     * For each method being read, the name of the parameter that has the id if
     * it is one of the methods that receive the id of a message, `null` if not.
     *
     * @var list<string|null>
     */
    private array $idParameters = [];

    /**
     * @param list<array{class: string, method: string, domain: string|null, idPosition: int, idName: string|null, domainPosition: int|null, domainName: string|null}> $methods
     * The methods that receive the id of a message.
     */
    public function __construct(
        private readonly string $file,
        private readonly array $methods = []
    ) {
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
            $this->scopes[] = $node->namespacedName?->toString() ?? 'class@anonymous';
        } elseif ($node instanceof Function_) {
            $this->functions[] = $node->namespacedName?->toString() ?? $node->name->toString();
        } elseif ($node instanceof Closure || $node instanceof ArrowFunction) {
            $this->functions[] = ($this->currentFunction() ?? '') === ''
                ? '{closure}'
                : $this->currentFunction() . '::{closure}';
        } elseif ($node instanceof ClassMethod) {
            $this->functions[] = ($this->scopes === [] ? '' : $this->scopes[array_key_last($this->scopes)] . '::')
                . $node->name->toString();
            $this->idParameters[] = $this->idParameterOf($node);
            if ($node->name->toLowerString() === '__construct') {
                $this->constructorParameters[] = array_values(array_filter(array_map(
                    fn (Node\Param $param) => $param->var instanceof Variable && is_string($param->var->name)
                        ? $param->var->name
                        : null,
                    $node->params
                )));
                $this->collectDefaultMessage($node);
            }
        } elseif ($node instanceof New_ && !$node->class instanceof Class_) {
            $this->collectNew($node);
        } elseif ($node instanceof StaticCall) {
            $this->collectParentConstructor($node);
            $this->collectMethodCall($node);
        } elseif ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            $this->collectMethodCall($node);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Class_ || $node instanceof Trait_) {
            array_pop($this->classes);
            array_pop($this->scopes);
        } elseif ($node instanceof Function_ || $node instanceof Closure || $node instanceof ArrowFunction) {
            array_pop($this->functions);
        } elseif ($node instanceof ClassMethod) {
            array_pop($this->functions);
            array_pop($this->idParameters);
            if ($node->name->toLowerString() === '__construct') {
                array_pop($this->constructorParameters);
            }
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
                && $param->default->value !== ''
            ) {
                $this->references[] = new MessageReference(
                    $param->default->value,
                    $this->domainOf($class),
                    $class,
                    $this->file,
                    $param->getStartLine(),
                    $this->currentFunction()
                );
            }
        }
    }

    /**
     * Adds the references for the message given in an argument.
     *
     * @param class-string $class
     */
    private function collectMessage(Node $at, Arg $argument, string $class, ?string $domain): void
    {
        if ($argument->unpack) {
            $this->references[] = new MessageReference(
                null,
                $domain,
                $class,
                $this->file,
                $at->getStartLine(),
                $this->currentFunction(),
                $this->printCall($at)
            );

            return;
        }

        $this->collectValue($at, $argument->value, $class, $domain);
    }

    /**
     * Adds the references for an expression that is a message.
     *
     * A ternary chooses between messages, so each branch is one: the condition
     * of a full ternary is not, but in a short one (`$a ?: 'b'`) the first part
     * is thrown when it is not empty. An empty message has nothing to translate:
     * it is a message that was not given.
     *
     * @param class-string $class
     */
    private function collectValue(Node $at, Node\Expr $value, string $class, ?string $domain): void
    {
        // A message that is already a translatable message is found on its
        // own, where it is built.
        if ($value instanceof New_) {
            return;
        }

        // The id that a method that receives it passes on is the message of
        // whoever calls the method, and it is found in the call.
        if (
            $value instanceof Variable
            && $value->name !== null
            && $this->idParameters !== []
            && $value->name === $this->idParameters[array_key_last($this->idParameters)]
        ) {
            return;
        }

        if ($value instanceof Node\Expr\Ternary) {
            $this->collectValue($at, $value->if ?? $value->cond, $class, $domain);
            $this->collectValue($at, $value->else, $class, $domain);

            return;
        }

        $id = null;
        if ($value instanceof String_) {
            $id = $value->value;
        } elseif ($value instanceof Array_ && $value->items !== []) {
            $first = $value->items[0];
            if ($first->key === null && !$first->unpack && $first->value instanceof String_) {
                $id = $first->value->value;
            }
        }

        if ($id === '') {
            return;
        }

        $this->references[] = new MessageReference(
            $id,
            $domain,
            $class,
            $this->file,
            $at->getStartLine(),
            $this->currentFunction(),
            $id === null || $domain === null ? $this->printCall($at) : null
        );
    }

    /**
     * The name of the parameter that has the id, if the method that is being
     * read is one of those that receive the id of a message.
     */
    private function idParameterOf(ClassMethod $method): ?string
    {
        $class = $this->currentClass();
        if ($class === null) {
            return null;
        }

        foreach ($this->methods as $configured) {
            if (
                strtolower($configured['method']) === $method->name->toLowerString()
                && is_a($class, $configured['class'], true)
            ) {
                $parameter = $method->params[$configured['idPosition']] ?? null;

                return $parameter !== null && $parameter->var instanceof Variable && is_string($parameter->var->name)
                    ? $parameter->var->name
                    : null;
            }
        }

        return null;
    }

    /**
     * Adds the reference for a call of a method that receives the id of a
     * message. If the class of what is called is not known, the message can not
     * be checked, and it is reported as such.
     */
    private function collectMethodCall(MethodCall|NullsafeMethodCall|StaticCall $call): void
    {
        if ($this->methods === [] || !$call->name instanceof Identifier) {
            return;
        }

        $name = $call->name->toLowerString();
        $candidates = array_values(array_filter(
            $this->methods,
            fn (array $configured) => strtolower($configured['method']) === $name
        ));
        if ($candidates === []) {
            return;
        }

        [$class, $known] = $this->receiverOf($call);

        if (!$known) {
            $this->references[] = new MessageReference(
                null,
                null,
                $candidates[0]['class'],
                $this->file,
                $call->getStartLine(),
                $this->currentFunction(),
                $this->printCall($call)
            );

            return;
        }

        if ($class === null) {
            return;
        }

        foreach ($candidates as $configured) {
            if (!is_a($class, $configured['class'], true)) {
                continue;
            }

            $args = array_values(array_filter($call->args, fn ($arg) => $arg instanceof Arg));
            $id = $this->argumentAt($args, $configured['idPosition'], $configured['idName']);
            if ($id === null) {
                return;
            }

            $domain = $configured['domain'] ?? self::DEFAULT_DOMAIN;
            if ($configured['domainPosition'] !== null) {
                $given = $this->argumentAt($args, $configured['domainPosition'], $configured['domainName']);
                if ($given !== null) {
                    $domain = $this->literalDomain($given);
                }
            }

            $this->collectMessage($call, $id, $configured['class'], $domain);

            return;
        }
    }

    /**
     * The class of what a call is made on, and whether it is known by reading:
     * `$this`, `self`, `static`, `parent` and the name of a class. Anything else
     * (a variable, a property, the result of a call) is not known.
     *
     * @return array{class-string|null, bool}
     */
    private function receiverOf(MethodCall|NullsafeMethodCall|StaticCall $call): array
    {
        if ($call instanceof StaticCall) {
            if (!$call->class instanceof Name) {
                return [null, false];
            }

            return [$this->resolve($call->class), true];
        }

        if ($call->var instanceof Variable && $call->var->name === 'this') {
            $class = $this->currentClass();

            return [$class, $class !== null];
        }

        return [null, false];
    }

    /**
     * The function that is being read, as it is named in a reference.
     */
    private function currentFunction(): ?string
    {
        return $this->functions === [] ? null : $this->functions[array_key_last($this->functions)];
    }

    /**
     * The source of the call that has a message that can not be read: the whole
     * call, so it only changes when that code does. An anonymous class is printed
     * without its body.
     */
    private function printCall(Node $call): string
    {
        if (!$call instanceof Node\Expr) {
            return '';
        }

        if ($call instanceof New_ && $call->class instanceof Class_) {
            $call = new New_(new Name('class@anonymous'), $call->args);
        }

        return (new Standard())->prettyPrintExpr($call);
    }

    /**
     * @return class-string|null
     */
    private function currentClass(): ?string
    {
        return $this->classes === [] ? null : $this->classes[array_key_last($this->classes)];
    }

    /**
     * Finds an argument of a call by its position or, if it is named, by its
     * name.
     *
     * @param list<Arg> $args
     */
    private function argumentAt(array $args, int $position, ?string $name): ?Arg
    {
        foreach ($args as $index => $arg) {
            if ($arg->name !== null) {
                if ($name !== null && $arg->name->toString() === $name) {
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
