# Quick Start Guide

Get started with Derafu Translation library in minutes.

[TOC]

## Installation

Install via Composer:

```bash
composer require derafu/translation
```

Optional dependencies:

```bash
# If you want to use YAML files.
composer require symfony/yaml

# If you want to use Symfony's translator.
composer require symfony/translation
```

## Basic Setup

### 1. Create Translation Files

Choose your preferred format:

```php
// translations/messages/en.php
return [
    'welcome' => 'Welcome {name}!',
    'errors' => [
        'required' => 'The field {field} is required.',
        'email' => 'Invalid email: {email}'
    ]
];
```

```json
// translations/messages/en.json
{
    "welcome": "Welcome {name}!",
    "errors.required": "The field {field} is required.",
    "errors.email": "Invalid email: {email}"
}
```

```yaml
# translations/messages/en.yaml
welcome: 'Welcome {name}!'
errors:
  required: 'The field {field} is required.'
  email: 'Invalid email: {email}'
```

### 2. Create Translator

```php
use Derafu\Translation\Translator;
use Derafu\Translation\Provider\PhpMessageProvider;

// Create message provider.
$provider = new PhpMessageProvider(__DIR__ . '/translations');

// Create translator.
$translator = new Translator(
    provider: $provider,
    locale: 'en',
    fallbackLocales: ['en']
);
```

### 3. Make Exceptions Translatable

```php
use Derafu\Translation\Exception\Core\TranslatableException;

class ValidationException extends TranslatableException
{
    // No additional code needed!
}
```

## First Translation

### Basic Usage

```php
// 1. Simple string messages.
throw new ValidationException('Email is required.');

// 2. With translation key.
throw new ValidationException('errors.required', ['field' => 'email']);

// 3. With ICU formatting.
throw new ValidationException(
    'The value {value} must be between {min} and {max}.',
    [
        'value' => 42,
        'min' => 1,
        'max' => 10,
    ]
);
```

### Handling Exceptions

```php
try {
    // Your code.
} catch (ValidationException $e) {
    // Get default message.
    echo $e->getMessage();

    // Get translated message.
    echo $e->trans($translator);

    // Get message in specific locale.
    echo $e->trans($translator, 'es');
}
```

## Common Use Cases

### Form Validation

```php
class UserController
{
    public function create(Request $request): Response
    {
        $data = $request->toArray();

        if (empty($data['email'])) {
            throw new ValidationException([
                'errors.required',
                'field' => 'email',
            ]);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException([
                'errors.email',
                'email' => $data['email'],
            ]);
        }

        // Create user...
    }
}
```

### API Responses

```php
class ErrorHandler
{
    public function handle(Throwable $e): JsonResponse
    {
        if ($e instanceof TranslatableException) {
            return new JsonResponse([
                'error' => [
                    'message' => $e->trans($this->translator),
                    'code' => $e->getCode(),
                ]
            ]);
        }

        // Handle other errors...
    }
}
```

### Complex Messages

```php
// Pluralization.
throw new ValidationException(
    '{count, plural, =0{No files uploaded} one{1 file uploaded} other{# files uploaded}}',
    ['count' => $fileCount]
);

// Gender.
throw new ValidationException(
    '{gender, select, female{She} male{He} other{They}} uploaded {count} files',
    [
        'gender' => $user->getGender(),
        'count' => $fileCount
    ]
);
```

## Next Steps

1. Read about [ICU Message Format](icu-formatting) for powerful message formatting.
2. Learn about [Message Providers](custom-providers) for different storage options.
3. Check [Real World Examples](real-world) for common patterns.
4. Explore [Advanced Usage](advanced-usage) for complex scenarios.

## Common Pitfalls

1. Always include 'other' case in plural/select patterns:
    ```php
    // Wrong.
    '{gender, select, female{She} male{He}}'

    // Correct.
    '{gender, select, female{She} male{He} other{They}}'
    ```

2. Remember to provide all parameters:
    ```php
    // Will fail.
    throw new ValidationException(
        'The field {field} is required'
        // Missing parameters!
    );

    // Correct.
    throw new ValidationException(
        'The field {field} is required',
        ['field' => 'email']
    );
    ```

3. Use consistent translation keys:
    ```php
    // Recommended structure.
    validation.required
    validation.email.invalid
    validation.range.between
    ```

## Tips & Tricks

1. Use constants for translation keys:
    ```php
    final class Messages
    {
        public const REQUIRED = 'validation.required';
        public const EMAIL_INVALID = 'validation.email.invalid';
    }
    ```

2. Create factory methods for common exceptions:
    ```php
    class ValidationException extends TranslatableException
    {
        public static function required(string $field): self
        {
            return new self([
                Messages::REQUIRED,
                'field' => $field,
            ]);
        }
    }
    ```

3. Use type hints for better IDE support:
    ```php
    /**
     * @throws ValidationException When email is invalid
     */
    public function validateEmail(string $email): void
    {
        // Validation code.
    }
    ```
