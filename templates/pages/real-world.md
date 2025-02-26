# Real World Examples

This guide provides practical examples of using the Derafu Translation library in real-world scenarios.

[TOC]

## REST API Error Handling

### Error Response Structure

```php
class ApiException extends TranslatableException
{
    public function toArray(): array
    {
        return [
            'error' => [
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
                'type' => $this->getType(),
            ],
        ];
    }

    protected function getType(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }
}

class ValidationApiException extends ApiException
{
    private array $errors;

    public function __construct(array $errors, int $code = 422)
    {
        $this->errors = $errors;
        parent::__construct('validation.failed', $code);
    }

    public function toArray(): array
    {
        return [
            'error' => [
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
                'type' => $this->getType(),
                'errors' => $this->errors,
            ],
        ];
    }
}
```

### API Error Handler

```php
class ApiErrorHandler
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function handle(Throwable $error): JsonResponse
    {
        if ($error instanceof ApiException) {
            $data = $error->toArray();
            if ($error instanceof TranslatableException) {
                $data['error']['message'] = $error->trans(
                    $this->translator,
                    $this->getLocaleFromRequest()
                );
            }
            return new JsonResponse($data, $error->getCode());
        }

        // Handle other errors...
    }
}
```

### Usage in Controllers

```php
class UserController
{
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (empty($data['email'])) {
            throw new ValidationApiException([
                'email' => new TranslatableMessage(
                    'validation.required',
                    ['field' => 'email']
                ),
            ]);
        }

        try {
            // Create user...
        } catch (DuplicateEmailException $e) {
            throw new ValidationApiException([
                'email' => new TranslatableMessage(
                    'validation.email.duplicate',
                    ['email' => $data['email']]
                ),
            ]);
        }
    }
}
```

## Form Validation

### Form Type

```php
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank([
                        'message' => new TranslatableMessage(
                            'validation.required',
                            ['field' => 'email']
                        ),
                    ]),
                    new Email([
                        'message' => new TranslatableMessage(
                            'validation.email.invalid',
                            ['email' => '{{ value }}']
                        ),
                    ])
                ]
            ])
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new Length([
                        'min' => 8,
                        'minMessage' => new TranslatableMessage(
                            'validation.password.min_length',
                            ['min' => 8]
                        ),
                    ])
                ]
            ]);
    }
}
```

### Form Handler

```php
class RegistrationFormHandler
{
    public function handle(FormInterface $form): User
    {
        if (!$form->isSubmitted()) {
            throw new FormException('form.not_submitted');
        }

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[$error->getOrigin()->getName()] = $error->getMessage();
            }
            throw new ValidationApiException($errors);
        }

        return $this->createUser($form->getData());
    }
}
```

## Domain-Specific Validation

### Order Processing

```php
class OrderProcessor
{
    public function process(Order $order): void
    {
        // Check stock.
        if (!$this->hasStock($order)) {
            throw new OrderException([
                'order.insufficient_stock',
                'product' => $order->getProduct()->getName(),
                'requested' => $order->getQuantity(),
                'available' => $this->getAvailableStock($order->getProduct()),
            ]);
        }

        // Check status transitions.
        if (!$this->canTransition($order, $status)) {
            throw new OrderException([
                'order.invalid_transition',
                'from' => $order->getStatus(),
                'to' => $status,
                'allowed' => implode(', ', $this->getAllowedTransitions($order)),
            ]);
        }
    }
}
```

### Financial Validation

```php
class PaymentValidator
{
    public function validate(Payment $payment): void
    {
        // Balance check.
        if ($payment->getAmount() > $this->getBalance()) {
            throw new PaymentException([
                'payment.insufficient_funds',
                'amount' => $payment->getAmount(),
                'balance' => $this->getBalance(),
                'currency' => $payment->getCurrency(),
            ]);
        }

        // Limit check.
        if ($payment->getAmount() > $this->getDailyLimit()) {
            throw new PaymentException([
                'payment.limit_exceeded',
                'amount' => $payment->getAmount(),
                'limit' => $this->getDailyLimit(),
                'period' => 'daily',
            ]);
        }
    }
}
```

## Complex Business Rules

### Document Workflow

```php
class DocumentWorkflow
{
    public function validate(Document $document): void
    {
        // Check permissions.
        if (!$this->canUserAccess($document)) {
            throw new DocumentException([
                'document.access_denied',
                'document' => $document->getTitle(),
                'user' => $this->getCurrentUser()->getName(),
                'required_role' => $document->getRequiredRole(),
            ]);
        }

        // Check workflow state.
        if (!$this->canTransition($document, $action)) {
            throw new DocumentException([
                'document.invalid_workflow_transition',
                'document' => $document->getTitle(),
                'current' => $document->getState(),
                'action' => $action,
                'required_approvals' => $document->getRequiredApprovals(),
                'current_approvals' => $document->getCurrentApprovals(),
            ]);
        }
    }
}
```

## Multiple Translation Sources

### Combining Providers

```php
class CompositeProvider implements MessageProviderInterface
{
    /** @var MessageProviderInterface[] */
    private array $providers;

    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    public function getMessages(string $locale, string $domain = 'messages'): array
    {
        $messages = [];
        foreach ($this->providers as $provider) {
            $messages = array_merge(
                $messages,
                $provider->getMessages($locale, $domain)
            );
        }
        return $messages;
    }
}

// Usage.
$provider = new CompositeProvider([
    new PhpMessageProvider(__DIR__ . '/translations'),
    new DatabaseMessageProvider($db),
    new RedisMessageProvider($redis),
]);
```

### Cache Layer

```php
class CachedProvider implements MessageProviderInterface
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

        return $this->cache->get($key, function() use ($locale, $domain) {
            return $this->provider->getMessages($locale, $domain);
        }, $this->ttl);
    }
}
```

---

Remember:

- Keep error messages user-friendly but informative.
- Include relevant context in error messages.
- Use consistent message structure.
- Consider performance with caching.
- Handle nested validations properly.
- Plan for internationalization from the start.
