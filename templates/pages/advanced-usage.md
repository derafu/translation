# Advanced Usage Guide

This guide covers advanced patterns and usage scenarios for the Derafu Translation library.

[TOC]

## Message Composition

### Base Messages

```php
class MessageTemplates
{
    public const REQUIRED = 'validation.required';
    public const INVALID = 'validation.invalid';
    public const FORMAT = 'validation.format';

    public static function required(string $field): TranslatableInterface
    {
        return new TranslatableMessage(self::REQUIRED, ['field' => $field]);
    }

    public static function invalid(string $field, mixed $value): TranslatableInterface
    {
        return new TranslatableMessage(self::INVALID, [
            'field' => $field,
            'value' => (string) $value
        ]);
    }

    public static function format(string $field, string $format): TranslatableInterface
    {
        return new TranslatableMessage(self::FORMAT, [
            'field' => $field,
            'format' => $format
        ]);
    }
}
```

### Composite Messages

```php
class CompositeMessage implements TranslatableInterface
{
    /** @var TranslatableInterface[] */
    private array $messages;

    /**
     * @param TranslatableInterface[] $messages
     */
    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return implode(' ', array_map(
            fn(TranslatableInterface $message) => $message->trans($translator, $locale),
            $this->messages
        ));
    }

    public function __toString(): string
    {
        return implode(' ', array_map(
            fn(TranslatableInterface $message) => (string) $message,
            $this->messages
        ));
    }
}

// Usage
throw new ValidationException(new CompositeMessage([
    MessageTemplates::required('email'),
    MessageTemplates::format('email', 'user@example.com')
]));
```

## Dynamic Translation Loading

### Translation Strategy

```php
interface TranslationStrategy
{
    public function getMessages(string $locale): array;
    public function supports(string $domain): bool;
}

class ApiTranslationStrategy implements TranslationStrategy
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $apiUrl
    ) {
    }

    public function getMessages(string $locale): array
    {
        $response = $this->client->request('GET', sprintf(
            '%s/translations/%s',
            $this->apiUrl,
            $locale
        ));

        return json_decode($response->getBody()->getContents(), true);
    }

    public function supports(string $domain): bool
    {
        return $domain === 'remote';
    }
}

class DynamicProvider implements MessageProviderInterface
{
    /** @var TranslationStrategy[] */
    private array $strategies = [];

    public function addStrategy(TranslationStrategy $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    public function getMessages(string $locale, string $domain = 'messages'): array
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($domain)) {
                return $strategy->getMessages($locale);
            }
        }
        return [];
    }
}
```

## Exception Hierarchies

### Base Domain Exception

```php
abstract class DomainException extends TranslatableException
{
    protected function getDefaultDomain(): string
    {
        return 'domain';
    }

    protected function getMessageContext(): array
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'trace_id' => $this->getTraceId()
        ];
    }

    private function getTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

### Specific Exceptions

```php
class OrderException extends DomainException
{
    protected function getDefaultDomain(): string
    {
        return 'orders';
    }

    public static function insufficientStock(
        Product $product,
        int $requested,
        int $available
    ): self {
        return new self([
            'order.insufficient_stock',
            'product' => $product->getName(),
            'requested' => $requested,
            'available' => $available
        ]);
    }
}

class PaymentException extends DomainException
{
    protected function getDefaultDomain(): string
    {
        return 'payments';
    }

    public static function insufficientFunds(
        Money $amount,
        Money $balance
    ): self {
        return new self([
            'payment.insufficient_funds',
            'amount' => $amount->format(),
            'balance' => $balance->format(),
            'currency' => $amount->getCurrency()
        ]);
    }
}
```

## Context-Aware Translation

### Translation Context

```php
class TranslationContext
{
    public function __construct(
        private readonly array $data = []
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function merge(array $data): self
    {
        return new self(array_merge($this->data, $data));
    }
}

class ContextAwareTranslator implements TranslatorInterface
{
    private ?TranslationContext $context = null;

    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function withContext(TranslationContext $context): self
    {
        $clone = clone $this;
        $clone->context = $context;
        return $clone;
    }

    public function trans(
        ?string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null
    ): string {
        if ($this->context) {
            $parameters = array_merge(
                $parameters,
                $this->context->toArray()
            );
        }

        return $this->translator->trans($id, $parameters, $domain, $locale);
    }
}
```

## Translation Decorators

### Logging Decorator

```php
class LoggingTranslator implements TranslatorInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function trans(
        ?string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null
    ): string {
        $result = $this->translator->trans($id, $parameters, $domain, $locale);

        $this->logger->debug('Translation performed', [
            'id' => $id,
            'parameters' => $parameters,
            'domain' => $domain,
            'locale' => $locale,
            'result' => $result
        ]);

        return $result;
    }
}
```

### Fallback Chain Decorator

```php
class FallbackChainTranslator implements TranslatorInterface
{
    /** @var TranslatorInterface[] */
    private array $translators;

    public function __construct(TranslatorInterface ...$translators)
    {
        $this->translators = $translators;
    }

    public function trans(
        ?string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null
    ): string {
        $lastException = null;

        foreach ($this->translators as $translator) {
            try {
                return $translator->trans($id, $parameters, $domain, $locale);
            } catch (Exception $e) {
                $lastException = $e;
                continue;
            }
        }

        throw new RuntimeException(
            'No translator could handle the translation',
            0,
            $lastException
        );
    }
}
```

### Caching Decorator

```php
class CachingTranslator implements TranslatorInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600
    ) {
    }

    public function trans(
        ?string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null
    ): string {
        $key = $this->getCacheKey($id, $parameters, $domain, $locale);

        return $this->cache->get($key, function() use ($id, $parameters, $domain, $locale) {
            return $this->translator->trans($id, $parameters, $domain, $locale);
        }, $this->ttl);
    }

    private function getCacheKey(
        string $id,
        array $parameters,
        ?string $domain,
        ?string $locale
    ): string {
        return md5(serialize([
            'id' => $id,
            'parameters' => $parameters,
            'domain' => $domain,
            'locale' => $locale
        ]));
    }
}
```

---

Remember:

- Use composition to extend functionality.
- Keep single responsibility principle.
- Make exceptions domain-specific.
- Consider performance implications.
- Add proper logging and monitoring.
- Handle edge cases gracefully.
