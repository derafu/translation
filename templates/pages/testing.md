# Testing Guide

This guide covers how to test applications using the Derafu Translation library, focusing on testing translatable exceptions and message handling.

[TOC]

## Testing Exceptions

### Basic Exception Testing

```php
use Derafu\Translation\TranslatableMessage;
use PHPUnit\Framework\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function testBasicException(): void
    {
        $exception = new ValidationException('Email is invalid.');

        // Without translation, should use original message.
        $this->assertEquals('Email is invalid.', $exception->getMessage());
    }

    public function testExceptionWithParameters(): void
    {
        $exception = new ValidationException([
            'validation.email',
            'email' => 'test@example',
        ]);

        // Test ICU formatting without translator.
        $this->assertEquals(
            'Invalid email: test@example',
            $exception->getMessage()
        );
    }

    public function testWithTranslatableMessage(): void
    {
        $message = new TranslatableMessage(
            'validation.required',
            ['field' => 'email'],
        );

        $exception = new ValidationException($message);
        $this->assertInstanceOf(
            TranslatableMessage::class,
            $exception->getTranslatableMessage()
        );
    }
}
```

### Using Mock Translator

```php
class OrderExceptionTest extends TestCase
{
    private MockObject&TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    public function testOrderValidation(): void
    {
        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with(
                'order.invalid_status',
                ['status' => 'pending'],
                'errors',
                'en'
            )
            ->willReturn('Invalid order status: pending');

        $exception = new OrderException([
            'order.invalid_status',
            'status' => 'pending'
        ]);

        $this->assertEquals(
            'Invalid order status: pending',
            $exception->trans($this->translator, 'en')
        );
    }
}
```

## Testing Translations

### Testing Message Files

```php
class TranslationFilesTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/../fixtures/translations';
    }

    #[DataProvider('dataProvider')]
    public function testMessageFiles(string $file): void
    {
        $this->assertFileExists($file);

        // Test file format.
        $messages = require $file;
        $this->assertIsArray($messages);

        // Test message format.
        foreach ($messages as $key => $value) {
            $this->assertIsString($key);
            $this->assertIsString($value);

            // Test ICU format.
            $formatter = new MessageFormatter('en', $value);
            $this->assertNotFalse(
                $formatter,
                "Invalid ICU format in message: $value"
            );
        }
    }

    public function provideMessageFiles(): array
    {
        return [
            'en messages' => [$this->fixturesDir . '/messages/en.php'],
            'es messages' => [$this->fixturesDir . '/messages/es.php'],
            'en errors' => [$this->fixturesDir . '/errors/en.php'],
            'es errors' => [$this->fixturesDir . '/errors/es.php'],
        ];
    }
}
```

### Testing Translation Consistency

```php
class TranslationConsistencyTest extends TestCase
{
    private array $locales = ['en', 'es'];

    private array $domains = ['messages', 'errors'];

    private MessageProviderInterface $provider;

    protected function setUp(): void
    {
        $this->provider = new PhpMessageProvider(
            __DIR__ . '/../fixtures/translations'
        );
    }

    public function testAllLocalesHaveSameKeys(): void
    {
        foreach ($this->domains as $domain) {
            $baseMessages = $this->provider->getMessages('en', $domain);
            $baseKeys = array_keys($baseMessages);

            foreach ($this->locales as $locale) {
                if ($locale === 'en') {
                    continue;
                }

                $messages = $this->provider->getMessages($locale, $domain);
                $keys = array_keys($messages);

                $this->assertEquals(
                    sort($baseKeys),
                    sort($keys),
                    "Missing translations in $locale for domain $domain"
                );
            }
        }
    }

    public function testIcuParametersConsistency(): void
    {
        foreach ($this->domains as $domain) {
            $baseMessages = $this->provider->getMessages('en', $domain);

            foreach ($this->locales as $locale) {
                if ($locale === 'en') {
                    continue;
                }

                $messages = $this->provider->getMessages($locale, $domain);

                foreach ($baseMessages as $key => $baseMessage) {
                    $message = $messages[$key];

                    // Extract parameters from ICU messages.
                    preg_match_all('/{(\w+)}/', $baseMessage, $baseParams);
                    preg_match_all('/{(\w+)}/', $message, $params);

                    $this->assertEquals(
                        sort($baseParams[1]),
                        sort($params[1]),
                        "Parameters mismatch in key '$key' for locale $locale"
                    );
                }
            }
        }
    }
}
```

## Testing Custom Providers

```php
class CustomProviderTest extends TestCase
{
    public function testProvider(): void
    {
        $provider = new CustomProvider();

        // Test basic functionality.
        $messages = $provider->getMessages('en');
        $this->assertIsArray($messages);

        // Test locales.
        $locales = $provider->getAvailableLocales();
        $this->assertNotEmpty($locales);

        // Test domains.
        $errors = $provider->getMessages('en', 'errors');
        $this->assertIsArray($errors);
    }

    public function testErrorHandling(): void
    {
        $provider = new CustomProvider();

        $this->expectException(RuntimeException::class);
        $provider->getMessages('invalid-locale');
    }
}
```

## Test Helpers

```php
trait TranslationTestTrait
{
    private function createTestTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            public function trans(
                ?string $id,
                array $parameters = [],
                string $domain = null,
                string $locale = null
            ): string {
                return strtr($id, $parameters);
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }

    private function assertTranslationEquals(
        string $expected,
        TranslatableException $exception,
        string $message = ''
    ): void {
        $translator = $this->createTestTranslator();
        $this->assertEquals(
            $expected,
            $exception->trans($translator),
            $message
        );
    }
}
```

## Common Patterns

1. **Test Exception Factory Methods**
    ```php
    public function testExceptionFactory(): void
    {
        $exception = ValidationExceptionFactory::required('email');

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals(
            'The field email is required.',
            $exception->getMessage()
        );
    }
    ```

2. **Test Translation Fallbacks**
    ```php
    public function testFallbackTranslation(): void
    {
        $translator = new Translator(
            $this->provider,
            'fr',
            ['es', 'en']
        );

        $exception = new ValidationException('test.message');

        // Should fallback to English.
        $this->assertEquals(
            'Test message',
            $exception->trans($translator)
        );
    }
    ```

3. **Test ICU Edge Cases**
    ```php
    public function testComplexIcuPattern(): void
    {
        $exception = new ValidationException(
            '{count, plural, =0{Empty} one{# item} other{# items}}',
            ['count' => 0]
        );

        $this->assertEquals('Empty', $exception->getMessage());
    }
    ```

---

Remember:

- Always test both translated and untranslated scenarios.
- Test parameter substitution.
- Verify ICU message formatting.
- Check translation consistency across locales.
- Test error handling.
- Use data providers for multiple test cases.
