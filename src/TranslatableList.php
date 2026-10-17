<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use IntlListFormatter;
use JsonSerializable;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translatable value that has several translatable values inside, joined in
 * one text.
 *
 * It is for what is not known when the code is written: how many items there
 * are. They can not be the parameters of a message, one by one, and they can
 * not be joined before they are translated, because then they would be joined
 * in the language that they were written in, and not translated. A list keeps
 * them as they are, and translates each one and joins them when the translator
 * and the language are known (`trans()`), like a nested message is.
 *
 * There are two ways to join the items, because they are two different cases:
 *
 *   - `enumeration()` is for short items: "red, green and blue". The separator
 *     and the last conjunction are the ones of the language ("a, b y c" in
 *     Spanish, "a, b, and c" in English).
 *   - `separated()` is for items that are sentences, like errors: they are
 *     joined as they are, with a separator. They have their own commas,
 *     conjunctions and full stops, so an enumeration of them would be
 *     ambiguous ("it could not be read., the password is wrong. and ...").
 *     The text of the items is not changed: no full stop is added or removed.
 *
 * Its use as a parameter of a message needs nothing else: the translator
 * translates a parameter that is translatable, before it formats the message.
 */
final readonly class TranslatableList implements TranslatableInterface, JsonSerializable
{
    /**
     * The language that is used when there is no translator to tell it.
     */
    private const string DEFAULT_LOCALE = 'en';

    /**
     * @param list<TranslatableInterface|string> $items The items.
     * @param string|null $separator What joins the items, or `null` if they
     * are an enumeration.
     * @param int $type Type of the enumeration (`IntlListFormatter::TYPE_*`).
     * @param int $width Width of the enumeration (`IntlListFormatter::WIDTH_*`).
     */
    private function __construct(
        private array $items,
        private ?string $separator,
        private int $type,
        private int $width
    ) {
    }

    /**
     * Creates a list that is an enumeration of short items: "a, b and c".
     *
     * @param iterable<TranslatableInterface|string> $items The items. A string
     * is not translated, it is used as it is.
     * @param int $type What the enumeration says: `IntlListFormatter::TYPE_AND`
     * ("a, b and c"), `TYPE_OR` ("a, b or c") or `TYPE_UNITS` ("5 ft, 6 in").
     * @param int $width Width of the enumeration: `IntlListFormatter::WIDTH_WIDE`,
     * `WIDTH_SHORT` or `WIDTH_NARROW`.
     * @return self
     * @throws InvalidArgumentException If the type or the width is not one of
     * the formatter.
     */
    public static function enumeration(
        iterable $items,
        int $type = IntlListFormatter::TYPE_AND,
        int $width = IntlListFormatter::WIDTH_WIDE
    ): self {
        if (!in_array($type, [IntlListFormatter::TYPE_AND, IntlListFormatter::TYPE_OR, IntlListFormatter::TYPE_UNITS], true)) {
            throw new InvalidArgumentException([
                'The type of the list is not valid: {type}.',
                'type' => $type,
            ]);
        }

        if (!in_array($width, [IntlListFormatter::WIDTH_WIDE, IntlListFormatter::WIDTH_SHORT, IntlListFormatter::WIDTH_NARROW], true)) {
            throw new InvalidArgumentException([
                'The width of the list is not valid: {width}.',
                'width' => $width,
            ]);
        }

        return new self(self::normalize($items), null, $type, $width);
    }

    /**
     * Creates a list of items that are sentences: they are joined as they are.
     *
     * @param iterable<TranslatableInterface|string> $items The items. A string
     * is not translated, it is used as it is.
     * @param string $separator What is put between an item and the next.
     * @return self
     */
    public static function separated(iterable $items, string $separator = ' '): self
    {
        return new self(
            self::normalize($items),
            $separator,
            IntlListFormatter::TYPE_AND,
            IntlListFormatter::WIDTH_WIDE
        );
    }

    /**
     * Translates each item and joins them.
     *
     * The language of an enumeration is the one that is given and, if it is not
     * given, the one of the translator (if it has one).
     *
     * @param TranslatorInterface $translator The translator to use.
     * @param string|null $locale The locale to translate to or `null` for
     * default.
     * @return string The items, translated and joined.
     */
    public function trans(
        TranslatorInterface $translator,
        ?string $locale = null
    ): string {
        $texts = array_map(
            fn (TranslatableInterface|string $item): string => $item instanceof TranslatableInterface
                ? $item->trans($translator, $locale)
                : $item,
            $this->items
        );

        $locale ??= $translator instanceof LocaleAwareInterface
            ? $translator->getLocale()
            : self::DEFAULT_LOCALE
        ;

        return $this->join($texts, $locale);
    }

    /**
     * Joins the items without a translator: each one as its own `__toString()`
     * says it, and an enumeration in English.
     *
     * @return string The items, joined.
     */
    public function __toString(): string
    {
        return $this->join(
            array_map(fn (TranslatableInterface|string $item): string => (string) $item, $this->items),
            self::DEFAULT_LOCALE
        );
    }

    /**
     * {@inheritDoc}
     *
     * The items that are not JSON by themselves are their text.
     *
     * @return array{items: list<JsonSerializable|string>, separator: string|null, type: int, width: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'items' => array_map(
                fn (TranslatableInterface|string $item): JsonSerializable|string => $item instanceof JsonSerializable
                    ? $item
                    : (string) $item,
                $this->items
            ),
            'separator' => $this->separator,
            'type' => $this->type,
            'width' => $this->width,
        ];
    }

    /**
     * Joins the texts, as an enumeration or with the separator.
     *
     * If the formatter fails (it should not, with a valid type and width), the
     * texts are joined with a comma, so a text is always given.
     *
     * @param list<string> $texts The texts of the items.
     * @param string $locale The language of the enumeration.
     * @return string The texts, joined.
     */
    private function join(array $texts, string $locale): string
    {
        if ($this->separator !== null) {
            return implode($this->separator, $texts);
        }

        return (new IntlListFormatter($locale, $this->type, $this->width))->format($texts)
            ?: implode(', ', $texts)
        ;
    }

    /**
     * Gets the items as a list.
     *
     * @param iterable<TranslatableInterface|string> $items The items.
     * @return list<TranslatableInterface|string> The items, in their order.
     */
    private static function normalize(iterable $items): array
    {
        return array_values(is_array($items) ? $items : iterator_to_array($items, false));
    }
}
