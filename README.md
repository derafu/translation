# Derafu: Translation - Translation Library with Exception Support

[![CI Workflow](https://github.com/derafu/translation/actions/workflows/ci.yml/badge.svg?branch=main&event=push)](https://github.com/derafu/translation/actions/workflows/ci.yml?query=branch%3Amain)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://opensource.org/licenses/MIT)

A PHP library that solves one specific problem: **making exceptions translatable without compromising your existing code**, while providing powerful ICU message formatting support.

## Features

- 🔄 **Translatable Exceptions**: Make any exception translatable with minimal changes.
- 🌍 **ICU Support**: Built-in ICU message formatting for complex messages.
- 📦 **Multiple Formats**: Load translations from PHP, JSON, YAML files or custom provider.
- ⛓️ **Locale Fallback**: Configurable locale fallback chain.
- 🎯 **Framework Agnostic**: Works with any system implementing Symfony's TranslatorInterface.
- 🪶 **Lightweight**: Minimal dependencies (only requires `symfony/translation-contracts`).
- 🧩 **Extensible**: Easy to add custom message providers.

## Installation

```bash
composer require derafu/translation
```

## Basic Usage

### Making Exceptions Translatable

```php
// Before: Your existing exception.
class ValidationException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}

// After: Just change the parent class.
class ValidationException extends TranslatableException
{
    // No changes needed!
}
```

### Using Translatable Exceptions

```php
// 1. Simple string (works like before).
throw new ValidationException('Email is invalid');

// 2. With translation key and parameters.
throw new ValidationException([
    'validation.email.invalid',
    'email' => 'test@example.com'
]);

// 3. With ICU formatting.
throw new ValidationException(
    'The email {email} is not valid',
    ['email' => 'test@example.com']
);

// 4. With complex ICU patterns.
throw new ValidationException(
    '{gender, select, female{She} male{He} other{They}} sent {count, plural, one{# message} other{# messages}}',
    [
        'gender' => 'female',
        'count' => 5
    ]
);
```

### Translation Files

Support for multiple formats:

```php
// PHP arrays (fastest).
// translations/messages/en.php
return [
    'welcome' => 'Welcome {name}!',
    'messages' => '{count, plural, =0{No messages} one{# message} other{# messages}}'
];

// JSON.
// translations/messages/en.json
{
    "welcome": "Welcome {name}!",
    "messages": "{count, plural, =0{No messages} one{# message} other{# messages}}"
}

// YAML (requires symfony/yaml).
# translations/messages/en.yaml
welcome: 'Welcome {name}!'
messages: '{count, plural, =0{No messages} one{# message} other{# messages}}'
```

### Setting Up the Translator

```php
use Derafu\Translation\Translator;
use Derafu\Translation\Provider\PhpMessageProvider;

// Create provider.
$provider = new PhpMessageProvider(__DIR__ . '/translations');

// Create translator with fallback locales.
$translator = new Translator(
    $provider,
    'en', // Optional. Default `null` for Translator y 'en' for ICU.
    ['es_CL', 'es', 'en'] // Optional. Default are: 'en', 'en_US', 'es', 'es_CL'.
);

// Use in your exception handling.
try {
    // Your code.
} catch (TranslatableException $e) {
    // Get translation for current locale.
    echo $e->trans($translator);

    // Or specific locale.
    echo $e->trans($translator, 'es');
}
```

## Message Providers

The library includes three message providers:

- `PhpMessageProvider`: Uses PHP files returning arrays (fastest).
- `JsonMessageProvider`: Uses JSON files.
- `YamlMessageProvider`: Uses YAML files (requires `symfony/yaml`).

## Key Benefits

1. **Zero Compromise**: Keep your existing exception handling code.
2. **ICU Power**: Full ICU message formatting support.
3. **Flexible Loading**: Choose your preferred translation file format.
4. **Clean Separation**: Error logic stays separate from presentation.
5. **Type Safe**: Full PHP 8.3+ type safety.

## When to Use This Library

This library is perfect when you:

- Need to internationalize exceptions without refactoring.
- Want ICU message formatting support.
- Need flexible translation file formats.
- Want to keep error handling and translation separate.
- Use exceptions for business logic validation.

## License

This library is licensed under the MIT License. See the `LICENSE` file for more details.
