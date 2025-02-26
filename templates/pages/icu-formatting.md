# ICU Message Formatting Guide

This guide covers ICU (International Components for Unicode) message formatting in the context of the Derafu Translation library. ICU provides powerful tools for handling pluralization, gender, and complex message patterns.

[TOC]

## Basic Placeholders

The simplest form of ICU formatting uses named placeholders:

```php
throw new ValidationException(
    'The field {field} is required.',
    ['field' => 'email'],
);
// Output: "The field email is required.".
```

Multiple placeholders are supported:

```php
throw new ValidationException(
    'Value {value} for field {field} is invalid.',
    [
        'value' => 'test@',
        'field' => 'email',
    ],
);
// Output: "Value test@ for field email is invalid."
```

## Pluralization

ICU provides robust pluralization support with multiple forms:

```php
// Basic pluralization.
$message = '{count, plural, =0{No messages} one{# message} other{# messages}}';
$translator->trans($message, ['count' => 2]);  // "2 messages"
$translator->trans($message, ['count' => 1]);  // "1 message"
$translator->trans($message, ['count' => 0]);  // "No messages"

// With exact matches and ranges.
$message = '{count, plural,
    =0 {No items}
    =1 {One item}
    =2 {A couple of items}
    other {# items}
}';
```

Available plural categories:

- `zero`: For languages with special zero forms.
- `one`: Singular form.
- `two`: Dual form (for languages that have it).
- `few`: For languages with special handling of small numbers.
- `many`: For languages with special handling of large numbers.
- `other`: Default form (required).
- `=n`: Exact number matches.

## Gender Selection

Gender-based message formatting:

```php
$message = '{gender, select,
    female {She liked your post}
    male {He liked your post}
    other {They liked your post}
}';

$translator->trans($message, ['gender' => 'female']);
// Output: "She liked your post".
```

Complex gender example with variables:

```php
$message = '{gender, select,
    female {{name} added her comment}
    male {{name} added his comment}
    other {{name} added their comment}
}';

$translator->trans($message, [
    'gender' => 'female',
    'name' => 'Alice'
]);
// Output: "Alice added her comment".
```

## Number Formatting

ICU supports various number formats:

```php
// Basic number.
'{value, number}'

// With minimum decimals.
'{value, number, .00}'

// Percentage.
'{value, number, percent}'

// Currency.
'{value, number, currency}'
```

Example in context:
```php
throw new ValidationException(
    'Balance must be greater than {min, number, currency}.',
    ['min' => 100],
);
// Output: "Balance must be greater than $100.00".
```

## Nested Formatting

ICU patterns can be nested for complex scenarios:

```php
$message = '{gender, select,
    female {
        {count, plural,
            =0 {She has no messages}
            one {She has # message}
            other {She has # messages}
        }
    }
    male {
        {count, plural,
            =0 {He has no messages}
            one {He has # message}
            other {He has # messages}
        }
    }
    other {
        {count, plural,
            =0 {They have no messages}
            one {They have # message}
            other {They have # messages}
        }
    }
}';

$translator->trans($message, [
    'gender' => 'female',
    'count' => 5,
]);
// Output: "She has 5 messages".
```

## Common Patterns

Here are some common patterns used in validation messages:

```php
// Range validation.
'Value must be between {min} and {max}.'

// List validation.
'{count, plural,
    =0 {List cannot be empty}
    one {At least one item is required}
    other {At least # items are required}
}'

// Status messages.
'{status, select,
    pending {Waiting for approval}
    approved {Approved on {date}}
    rejected {Rejected: {reason}}
    other {Unknown status}
}'

// File validation.
'{type, select,
    image {Only images are allowed}
    document {Only documents are allowed}
    other {Invalid file type}
}, max size: {size}'
```

## Troubleshooting

Common issues and solutions:

1. **Missing 'other' category**
    ```php
    // Wrong.
    '{gender, select, male{He} female{She}}'

    // Correct.
    '{gender, select, male{He} female{She} other{They}}'
    ```

2. **Invalid nesting**
    ```php
    // Wrong.
    '{outer, select, a{{inner}}'

    // Correct.
    '{outer, select, a {{inner}} other {default}}'
    ```

3. **Unmatched braces**
    ```php
    // Wrong.
    'Hello {name'

    // Correct.
    'Hello {name}'
    ```

4. **Missing parameters**
    ```php
    // Will fail if 'count' is not provided.
    '{count} items'
    ```

---

Remember:

- Always include the 'other' case in select/plural patterns.
- Check braces are properly matched.
- Ensure all used parameters are provided.
- Test with different values and locales.

---

For more information about ICU message format:

- [ICU User Guide](https://unicode-org.github.io/icu/userguide/format_parse/messages/)
- [MessageFormat Guide](https://messageformat.github.io/messageformat/)
