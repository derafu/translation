# Working with Translatable Exceptions

This guide covers all aspects of working with exceptions in the Derafu Translation library.

[TOC]

## Available Exceptions

The library provides translatable versions of all standard PHP exceptions:

### Core Exceptions

- `TranslatableException`: Base exception.
- `TranslatableLogicException`: For logical errors.
- `TranslatableRuntimeException`: For runtime errors.

### Logic Exceptions

- `TranslatableDomainException`: Domain logic violations.
- `TranslatableInvalidArgumentException`: Invalid input.
- `TranslatableLengthException`: Invalid length.
- `TranslatableOutOfRangeException`: Value out of valid set.

### Runtime Exceptions

- `TranslatableOutOfBoundsException`: Invalid index or key.
- `TranslatableOverflowException`: Arithmetic overflow.
- `TranslatableRangeException`: Value not within range.
- `TranslatableUnderflowException`: Arithmetic underflow.
- `TranslatableUnexpectedValueException`: Unexpected value type.

## Using the Trait

### TranslatableExceptionTrait

If you want to add translation capabilities to your own exceptions, you can use the trait:

```php
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\TranslatableExceptionTrait;
use DomainException;

class MyCustomException extends DomainException implements TranslatableInterface
{
    use TranslatableExceptionTrait;
}
```

### When to Use the Trait vs Extending Base Exceptions

Use the trait when:

- You already extend another exception.
- You need to customize the exception behavior.
- You want to add additional functionality.

Use the base exceptions when:

- You don't need custom behavior.
- You want the simplest implementation.
- You're creating domain-specific exceptions.

Example with trait:

```php
class OrderException extends DomainException implements TranslatableInterface
{
    use TranslatableExceptionTrait;

    protected string $defaultDomain = 'orders';

    public static function insufficientStock(Product $product): self
    {
        return new self([
            'order.insufficient_stock',
            'product' => $product->getName(),
        ]);
    }
}
```

Example with inheritance:
```php
class ValidationException extends TranslatableDomainException
{
    // Inherits all translation functionality.
}
```

## When to Use Each Exception

### TranslatableException

Base exception, use when:

- General error conditions.
- No specific exception type fits.
- Creating custom exception hierarchies.

Real-world examples:

1. Generic API errors.
    ```php
    throw new TranslatableException([
        'api.error.generic',
        'code' => $errorCode,
    ]);
    ```

2. System configuration errors.
    ```php
    throw new TranslatableException([
        'system.config.missing',
        'key' => 'database.host',
    ]);
    ```

3. Third-party service errors.
    ```php
    throw new TranslatableException([
        'service.unavailable',
        'service' => 'payment',
        'reason' => $response->getError(),
    ]);
    ```

### TranslatableLogicException

For programming errors that can be detected during development:

1. Invalid business rule implementation.
    ```php
    throw new TranslatableLogicException([
        'logic.invalid_workflow',
        'state' => $currentState,
        'action' => $attemptedAction,
    ]);
    ```

2. Configuration errors.
    ```php
    throw new TranslatableLogicException([
        'config.invalid_combination',
        'option1' => $value1,
        'option2' => $value2,
    ]);
    ```

3. Invalid method usage.
    ```php
    throw new TranslatableLogicException([
        'method.invalid_order',
        'method' => 'process',
        'required_before' => 'validate',
    ]);
    ```

### TranslatableDomainException

For violations of domain rules:

1. Business rule violations.
    ```php
    throw new TranslatableDomainException([
        'order.invalid_status_transition',
        'from' => $currentStatus,
        'to' => $newStatus,
    ]);
    ```

2. Invalid entity state.
    ```php
    throw new TranslatableDomainException([
        'product.cannot_publish',
        'reason' => 'missing_price',
    ]);
    ```

3. Business constraint violations.
    ```php
    throw new TranslatableDomainException([
        'account.overdraft_limit_exceeded',
        'amount' => $amount,
        'limit' => $overdraftLimit,
    ]);
    ```

### TranslatableInvalidArgumentException

For invalid input values:

1. Invalid parameter types.
    ```php
    throw new TranslatableInvalidArgumentException([
        'argument.invalid_type',
        'argument' => 'date',
        'expected' => 'DateTime',
        'received' => get_debug_type($date),
    ]);
    ```

2. Invalid format.
    ```php
    throw new TranslatableInvalidArgumentException([
        'argument.invalid_format',
        'field' => 'phone',
        'format' => '+XX-XXX-XXXXXX',
    ]);
    ```

3. Invalid options.
    ```php
    throw new TranslatableInvalidArgumentException([
        'argument.invalid_option',
        'option' => 'sort',
        'valid' => implode(', ', $validOptions),
    ]);
    ```

### TranslatableLengthException

For invalid lengths:

1. String length violations.
    ```php
    throw new TranslatableLengthException([
        'length.string_too_long',
        'field' => 'title',
        'max' => 255,
        'current' => strlen($title),
    ]);
    ```

2. Collection size issues.
    ```php
    throw new TranslatableLengthException([
        'length.too_many_items',
        'max' => 10,
        'current' => count($items),
    ]);
    ```

3. Buffer size problems.
    ```php
    throw new TranslatableLengthException([
        'length.buffer_overflow',
        'size' => $bufferSize,
        'required' => $requiredSize,
    ]);
    ```

### TranslatableOutOfRangeException

For values outside valid set:

1. Invalid enum values.
    ```php
    throw new TranslatableOutOfRangeException([
        'value.invalid_status',
        'value' => $status,
        'valid' => implode(', ', Status::cases()),
    ]);
    ```

2. Invalid date ranges.
    ```php
    throw new TranslatableOutOfRangeException([
        'date.out_of_range',
        'date' => $date->format('Y-m-d'),
        'min' => $minDate->format('Y-m-d'),
        'max' => $maxDate->format('Y-m-d'),
    ]);
    ```

3. Invalid numerical ranges.
    ```php
    throw new TranslatableOutOfRangeException([
        'value.out_of_range',
        'value' => $percentage,
        'min' => 0,
        'max' => 100,
    ]);
    ```

### TranslatableOutOfBoundsException

For invalid array/collection access:

1. Invalid array index.
    ```php
    throw new TranslatableOutOfBoundsException([
        'index.invalid',
        'index' => $index,
        'max' => count($array) - 1,
    ]);
    ```

2. Invalid page number.
    ```php
    throw new TranslatableOutOfBoundsException([
        'page.invalid',
        'page' => $page,
        'total' => $totalPages,
    ]);
    ```

3. Invalid collection access.
    ```php
    throw new TranslatableOutOfBoundsException([
        'record.not_found',
        'id' => $id,
        'type' => 'user',
    ]);
    ```

### TranslatableRangeException

For values technically valid but not allowed:

1. Value scale errors.
    ```php
    throw new TranslatableRangeException([
        'number.too_many_decimals',
        'value' => $number,
        'max_decimals' => 2,
    ]);
    ```

2. Business range violations.
    ```php
    throw new TranslatableRangeException([
        'discount.exceeds_limit',
        'discount' => $discount,
        'max_allowed' => $maxDiscount,
    ]);
    ```

3. Technical limitations.
    ```php
    throw new TranslatableRangeException([
        'file.too_large',
        'size' => $fileSize,
        'max' => $maxUploadSize,
    ]);
    ```

### TranslatableUnexpectedValueException

For values of unexpected type:

1. Invalid return values.
    ```php
    throw new TranslatableUnexpectedValueException([
        'api.unexpected_response',
        'expected' => 'array',
        'received' => gettype($response),
    ]);
    ```

2. Invalid data format.
    ```php
    throw new TranslatableUnexpectedValueException([
        'data.invalid_format',
        'expected' => 'JSON',
        'received' => $detectedFormat,
    ]);
    ```

3. Invalid state values.
    ```php
    throw new TranslatableUnexpectedValueException([
        'state.unexpected',
        'state' => $currentState,
        'expected' => implode(', ', $validStates),
    ]);
    ```

## Best Practices

1. **Choose Specific Exceptions**
   - Use the most specific exception type available.
   - Consider the error's nature (logic vs runtime).
   - Think about error recovery possibilities.

2. **Consistent Domain Language**
   - Use consistent translation keys.
   - Group related messages.
   - Use clear, descriptive keys.

3. **Provide Context**
   - Include relevant parameters.
   - Add debugging information.
   - Consider logging needs.

4. **Exception Hierarchy**
   - Create domain-specific exceptions.
   - Extend appropriate base classes.
   - Use meaningful inheritance chains.

5. **Translation Keys**
   - Use consistent naming patterns.
   - Group by domain/module.
   - Include error type in key.

### Example hierarchy

```php
// Base exception for your domain.
abstract class OrderException extends TranslatableDomainException
{
    protected string $defaultDomain = 'orders';
}

// Specific exceptions.
class OrderNotFoundException extends OrderException {}
class OrderValidationException extends OrderException {}
class OrderStateException extends OrderException {}
```
