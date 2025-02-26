# API Reference

Complete reference documentation for the Derafu Translation library.

[TOC]

## Interfaces

### TranslatableInterface

Extends Symfony's `TranslatorInterface` and adds ICU support.

```php
interface TranslatableInterface extends TranslatorInterface, Stringable
{
    /**
     * Converts the message to a string using ICU formatting.
     */
    public function __toString(): string;
}
```

### MessageProviderInterface

Defines how translation messages are loaded.

```php
interface MessageProviderInterface
{
    /**
     * Returns all messages for a given locale and domain.
     *
     * @param string $locale The locale to load messages for.
     * @param string $domain The translation domain.
     * @return array<string, string> Array of messages where key is the message id.
     */
    public function getMessages(string $locale, string $domain = 'messages'): array;

    /**
     * Returns all available locales for a given domain.
     *
     * @param string $domain The translation domain.
     * @return array<string> List of available locales.
     */
    public function getAvailableLocales(string $domain = 'messages'): array;
}
```

## Classes

### TranslatableMessage

Core message class that supports ICU formatting.

```php
final class TranslatableMessage implements TranslatableInterface
{
    /**
     * @param string $message The message used for translation.
     * @param array<string, mixed> $parameters Parameters for translation.
     * @param string|null $domain The translation domain.
     * @param string $defaultLocale The locale for ICU formatting.
     */
    public function __construct(
        string $message,
        array $parameters = [],
        ?string $domain = null,
        string $defaultLocale = 'en'
    );

    /**
     * @throws RuntimeException When ICU formatting fails.
     */
    public function __toString(): string;

    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null
    ): string;
}
```

### Translator

Main translator implementation, using Symfony's `TranslatorTrait`, with ICU and fallback support.

```php
final class Translator implements TranslatorInterface
{
    /**
     * @param MessageProviderInterface $provider Provider of messages.
     * @param string|null $locale Default locale.
     * @param array|null $fallbackLocales Fallback locales chain.
     */
    public function __construct(
        MessageProviderInterface $provider,
        ?string $locale = null,
        ?array $fallbackLocales = null
    );

    /**
     * @throws IntlException When ICU formatting fails
     */
    public function trans(
        ?string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null
    ): string;
}
```

## Exceptions

### TranslatableException

Base exception class that supports translation.

```php
abstract class TranslatableException extends Exception implements TranslatableInterface
{
    /**
     * @param string|array|TranslatableInterface $message The exception message:
     *   - string: Used as both message and translation key.
     *   - array: First element is message, rest are parameters.
     *   - TranslatableInterface: Used directly.
     * @param int $code The exception code.
     * @param Throwable|null $previous Previous exception.
     * @throws InvalidArgumentException When message array is invalid.
     */
    public function __construct(
        string|array|TranslatableInterface $message,
        int $code = 0,
        ?Throwable $previous = null
    );

    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null
    ): string;
}
```

## Message Providers

### AbstractMessageProvider

Base class for file-based message providers.

```php
abstract class AbstractMessageProvider implements MessageProviderInterface
{
    /**
     * @param string $directory Base directory for translation files.
     * @throws RuntimeException If directory doesn't exist.
     */
    public function __construct(
        protected readonly string $directory
    );

    /**
     * Returns the file extension without dot.
     */
    abstract protected function getFileExtension(): string;

    /**
     * Parses a file into messages array.
     *
     * @throws RuntimeException If file can't be parsed.
     */
    abstract protected function parseFile(string $file): array;
}
```

### PhpMessageProvider

Loads translations from PHP files.

```php
final class PhpMessageProvider extends AbstractMessageProvider
{
    /**
     * Expected file format:
     * <?php
     * return [
     *     'key' => 'value',
     * ];
     *
     * @throws RuntimeException If file doesn't return array.
     */
    protected function parseFile(string $file): array;
}
```

### JsonMessageProvider

Loads translations from JSON files.

```php
final class JsonMessageProvider extends AbstractMessageProvider
{
    /**
     * Expected file format:
     * {
     *     "key": "value"
     * }
     *
     * @throws RuntimeException If JSON is invalid.
     */
    protected function parseFile(string $file): array;
}
```

### YamlMessageProvider

Loads translations from YAML files (requires symfony/yaml).

```php
final class YamlMessageProvider extends AbstractMessageProvider
{
    /**
     * Expected file format:
     * key: value
     *
     * @throws RuntimeException If YAML is invalid.
     */
    protected function parseFile(string $file): array;
}
```

## Error Handling

All exceptions extend from base PHP exceptions.

Common exceptions:

- `TranslatableRuntimeException`: General runtime errors.
- `TranslatableInvalidArgumentException`: Invalid input parameters.

---

Can't extend Derafu Translation exceptions? Then use `TranslatableExceptionTrait` in your exceptions to get the translation capabilities.
