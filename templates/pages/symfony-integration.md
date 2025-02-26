# Symfony Integration Guide

This guide explains how to integrate the Derafu Translation library with Symfony's translation system.

[TOC]

## Basic Integration

The simplest way to use Derafu Translation with Symfony is to use Symfony's translator directly:

```php
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\YamlFileLoader;

class ErrorHandler
{
    public function __construct(
        private readonly Translator $translator
    ) {
    }

    public function handle(TranslatableException $e): void
    {
        $message = $e->trans($this->translator);
        // Handle translated message.
    }
}
```

## Using Symfony's Translator

### Configuration

```yaml
# config/packages/translation.yaml
framework:
    default_locale: 'en'
    translator:
        default_path: '%kernel.project_dir%/translations'
        fallbacks:
            - 'en'
        paths:
            - '%kernel.project_dir%/vendor/your-vendor/your-package/translations'
```

### Translation Files

```yaml
# translations/errors.en.yaml
validation:
    required: 'The field {field} is required'
    email:
        invalid: 'The email {email} is not valid'
    min_length: 'The field {field} must be at least {min} characters'

# translations/errors.es.yaml
validation:
    required: 'El campo {field} es requerido'
    email:
        invalid: 'El email {email} no es válido'
    min_length: 'El campo {field} debe tener al menos {min} caracteres'
```

### Exception Handler

```php
namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Derafu\Translation\Exception\Core\TranslatableException;

class TranslatableExceptionListener
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof TranslatableException) {
            return;
        }

        $response = new JsonResponse([
            'error' => $throwable->trans(
                $this->translator,
                $this->translator->getLocale()
            ),
        ]);

        $event->setResponse($response);
    }
}
```

### Service Configuration

```yaml
# config/services.yaml
services:
    App\EventListener\TranslatableExceptionListener:
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
```

## Advanced Usage

### Custom Exception Response Format

```php
class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof TranslatableException) {
            return;
        }

        $response = new JsonResponse([
            'status' => 'error',
            'message' => [
                'text' => $throwable->trans($this->translator),
                'translation_key' => $throwable->getTranslatableMessage()->getId(),
                'parameters' => $throwable->getTranslatableMessage()->getParameters(),
            ],
            'code' => $throwable->getCode(),
        ]);

        $event->setResponse($response);
    }
}
```

### Locale Based on User Preferences

```php
class LocaleListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Get locale from user preferences.
        $locale = $request->getPreferredLanguage(['en', 'es']);

        // Set for current request.
        $request->setLocale($locale);

        // Set for translator.
        $this->translator->setLocale($locale);
    }
}
```

## Best Practices

1. **Organize Translation Files**
    ```
    translations/
    ├── errors/
    │   ├── validators.en.yaml
    │   ├── validators.es.yaml
    │   ├── forms.en.yaml
    │   └── forms.es.yaml
    └── messages/
        ├── app.en.yaml
        └── app.es.yaml
    ```

2. **Use Constants for Translation Keys**
    ```php
    final class TranslationKeys
    {
        public const VALIDATION_REQUIRED = 'validation.required';
        public const VALIDATION_EMAIL = 'validation.email.invalid';
        // ...
    }
    ```

3. **Create Exception Factory**
    ```php
    final class ValidationExceptionFactory
    {
        public static function required(string $field): ValidationException
        {
            return new ValidationException([
                TranslationKeys::VALIDATION_REQUIRED,
                'field' => $field
            ]);
        }
    }
    ```

4. **Log Missing Translations**
    ```php
    $this->translator->setFallbackLocales(['en']);
    $this->translator->addListener(
        TranslationEvents::MISSING_TRANSLATION,
        function(MissingTranslationEvent $event) {
            $this->logger->warning(
                'Missing translation: {key} for locale {locale}',
                [
                    'key' => $event->getMessageId(),
                    'locale' => $event->getLocale()
                ]
            );
        }
    );
    ```

---

Remember:

- Keep translation files organized by domain.
- Use constants for translation keys to avoid typos.
- Create factories for common exceptions.
- Log missing translations in development.
- Use fallback locales appropriately.
- Consider user preferences for locale selection.
