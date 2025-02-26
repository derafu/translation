# Creating Custom Message Providers

This guide explains how to create custom message providers for different translation storage formats and sources.

[TOC]

## Provider Interface

All message providers must implement the `MessageProviderInterface`:

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

## Basic Implementation

Here's a simple example of a custom provider that loads messages from a database:

```php
use Derafu\Translation\Contract\MessageProviderInterface;
use PDO;

final class DatabaseMessageProvider implements MessageProviderInterface
{
    public function __construct(
        private readonly PDO $db,
        private readonly string $table = 'translations'
    ) {
    }

    public function getMessages(string $locale, string $domain = 'messages'): array
    {
        $stmt = $this->db->prepare(
            "SELECT message_key, message_text
             FROM {$this->table}
             WHERE locale = ? AND domain = ?"
        );
        $stmt->execute([$locale, $domain]);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getAvailableLocales(string $domain = 'messages'): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT locale
             FROM {$this->table}
             WHERE domain = ?"
        );
        $stmt->execute([$domain]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
```

## Using the Abstract Provider

For file-based providers, you can extend the `AbstractMessageProvider`:

```php
use Derafu\Translation\Abstract\AbstractMessageProvider;

final class IniMessageProvider extends AbstractMessageProvider
{
    protected function getFileExtension(): string
    {
        return 'ini';
    }

    protected function parseFile(string $file): array
    {
        $messages = parse_ini_file($file, false);
        if ($messages === false) {
            throw new RuntimeException(
                sprintf('Could not parse INI file "%s".', $file)
            );
        }

        return $messages;
    }
}
```

The abstract provider handles:

- Directory structure validation.
- File path generation.
- Available locales discovery.

You only need to implement:

- `getFileExtension()`: Returns the file extension.
- `parseFile()`: Parses the file content into messages array.

## Other Examples

### Redis Provider

```php
use Redis;
use Derafu\Translation\Contract\MessageProviderInterface;

final class RedisMessageProvider implements MessageProviderInterface
{
    public function __construct(
        private readonly Redis $redis,
        private readonly string $prefix = 'translations:'
    ) {
    }

    public function getMessages(string $locale, string $domain = 'messages'): array
    {
        $key = "{$this->prefix}{$domain}:{$locale}";
        $messages = $this->redis->hGetAll($key);

        return $messages ?: [];
    }

    public function getAvailableLocales(string $domain = 'messages'): array
    {
        $pattern = "{$this->prefix}{$domain}:*";
        $keys = $this->redis->keys($pattern);

        return array_map(
            fn($key) => substr($key, strrpos($key, ':') + 1),
            $keys
        );
    }
}
```

### API Provider

```php
use GuzzleHttp\Client;
use Derafu\Translation\Contract\MessageProviderInterface;

final class ApiMessageProvider implements MessageProviderInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $baseUrl
    ) {
    }

    public function getMessages(string $locale, string $domain = 'messages'): array
    {
        $response = $this->client->get(
            "{$this->baseUrl}/translations/{$domain}/{$locale}"
        );

        return json_decode(
            $response->getBody()->getContents(),
            true
        );
    }

    public function getAvailableLocales(string $domain = 'messages'): array
    {
        $response = $this->client->get(
            "{$this->baseUrl}/translations/{$domain}/locales"
        );

        return json_decode(
            $response->getBody()->getContents(),
            true
        );
    }
}
```

## Best Practices

1. **Error Handling**
    ```php
    protected function parseFile(string $file): array
    {
        try {
            // Parse file.
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Error parsing file "%s": %s', $file, $e->getMessage())
            );
        }
    }
    ```

2. **Caching Support**
    ```php
    final class CachedProvider implements MessageProviderInterface
    {
        public function __construct(
            private readonly MessageProviderInterface $provider,
            private readonly CacheInterface $cache,
            private readonly int $ttl = 3600
        ) {
        }

        public function getMessages(string $locale, string $domain = 'messages'): array
        {
            $key = "translations:{$domain}:{$locale}";

            return $this->cache->remember($key, $this->ttl, function() use ($locale, $domain) {
                return $this->provider->getMessages($locale, $domain);
            });
        }
    }
    ```

3. **Validation**
    ```php
    private function validateMessages(array $messages): void
    {
        foreach ($messages as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('Message keys must be strings.');
            }
            if (!is_string($value)) {
                throw new RuntimeException('Message values must be strings.');
            }
        }
    }
    ```

4. **Logging and Debugging**
    ```php
    public function getMessages(string $locale, string $domain = 'messages'): array
    {
        $messages = $this->loadMessages($locale, $domain);

        if (empty($messages)) {
            $this->logger->warning(
                'No messages found for locale {locale} and domain {domain}.',
                ['locale' => $locale, 'domain' => $domain]
            );
        }

        return $messages;
    }
    ```

---

Remember:

- Always validate input and output.
- Handle errors gracefully.
- Consider implementing caching for performance.
- Add logging for debugging.
- Keep providers focused and single-purpose.
- Use dependency injection for external services.
