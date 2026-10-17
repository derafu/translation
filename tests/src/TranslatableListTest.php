<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation;

use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableList;
use Derafu\Translation\TranslatableMessage;
use Derafu\Translation\Translation\TranslationResourceProvider;
use Derafu\Translation\TranslationResourceRegistrar;
use Derafu\Translation\TranslatorFactory;
use Generator;
use IntlListFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A list is one translatable value that has several translatable values inside.
 * What is not known when the code is written is how many there are, so they can
 * not be parameters of a message, one by one; and they can not be joined before
 * they are translated, because then they would not be translated.
 *
 * There are two ways to join them. An enumeration is for short items ("red,
 * green and blue"): its separator and its last conjunction are the ones of the
 * language. Items that are sentences are joined as they are, with a separator:
 * they have their own commas, conjunctions and full stops, so an enumeration
 * would be ambiguous.
 */
#[CoversClass(TranslatableList::class)]
#[UsesClass(TranslatableMessage::class)]
#[UsesClass(TranslatableInvalidArgumentException::class)]
#[UsesClass(TranslationResourceRegistrar::class)]
#[UsesClass(TranslationResourceProvider::class)]
#[UsesClass(TranslatorFactory::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
final class TranslatableListTest extends TestCase
{
    private function translator(): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'Red' => 'Rojo',
            'Green' => 'Verde',
            'Blue' => 'Azul',
            'The file could not be read, or it is damaged.' => 'El archivo no se pudo leer, o está dañado.',
            'The password is wrong (Error #{code}).' => 'La contraseña es incorrecta (Error #{code}).',
        ], 'es', 'messages+intl-icu');

        return $translator;
    }

    /**
     * @return list<TranslatableMessage>
     */
    private function colors(): array
    {
        return [new TranslatableMessage('Red'), new TranslatableMessage('Green'), new TranslatableMessage('Blue')];
    }

    /**
     * @return list<TranslatableMessage>
     */
    private function sentences(): array
    {
        return [
            new TranslatableMessage('The file could not be read, or it is damaged.'),
            new TranslatableMessage('The password is wrong (Error #{code}).', ['code' => 7]),
        ];
    }

    public function testAnEnumerationHasTheSeparatorAndTheConjunctionOfTheLanguage(): void
    {
        $list = TranslatableList::enumeration($this->colors());

        $this->assertSame('Rojo, Verde y Azul', $list->trans($this->translator()));
        $this->assertSame('Red, Green, and Blue', (string) $list);
    }

    public function testAnEnumerationCanBeAnOr(): void
    {
        $list = TranslatableList::enumeration($this->colors(), IntlListFormatter::TYPE_OR);

        $this->assertSame('Rojo, Verde o Azul', $list->trans($this->translator()));
        $this->assertSame('Red, Green, or Blue', (string) $list);
    }

    public function testAnEnumerationCanBeOfUnits(): void
    {
        $list = TranslatableList::enumeration(
            ['5 ft', '6 in', '2 oz'],
            IntlListFormatter::TYPE_UNITS,
            IntlListFormatter::WIDTH_SHORT
        );

        $this->assertSame('5 ft, 6 in, 2 oz', (string) $list);
        $this->assertSame('5 ft, 6 in, 2 oz', $list->trans($this->translator()));
    }

    public function testTheWidthOfAnEnumerationIsTheOneOfTheFormatter(): void
    {
        $wide = TranslatableList::enumeration(['a', 'b'], IntlListFormatter::TYPE_AND, IntlListFormatter::WIDTH_WIDE);
        $narrow = TranslatableList::enumeration(['a', 'b'], IntlListFormatter::TYPE_UNITS, IntlListFormatter::WIDTH_NARROW);

        $this->assertSame('a and b', (string) $wide);
        $this->assertSame('a b', (string) $narrow);
    }

    public function testTheLanguageOfAnEnumerationIsTheOneThatIsGiven(): void
    {
        $list = TranslatableList::enumeration(['a', 'b', 'c']);

        $this->assertSame('a, b, and c', $list->trans($this->translator(), 'en'));
        $this->assertSame('a, b y c', $list->trans($this->translator(), 'es'));
        $this->assertSame('a, b et c', $list->trans($this->translator(), 'fr'));
    }

    public function testTheLanguageIsTheOneOfTheTranslatorWhenItIsNotGiven(): void
    {
        $this->assertSame('a, b y c', TranslatableList::enumeration(['a', 'b', 'c'])->trans($this->translator()));
    }

    public function testTheLanguageIsEnglishIfTheTranslatorDoesNotHaveOne(): void
    {
        $translator = new class () implements TranslatorInterface {
            /**
             * @param array<string, mixed> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtoupper($id);
            }

            public function getLocale(): string
            {
                return 'es';
            }
        };

        $list = TranslatableList::enumeration(['a', 'b', 'c']);

        $this->assertSame('a, b, and c', $list->trans($translator));
    }

    public function testSentencesAreJoinedAsTheyAreWithASpace(): void
    {
        $list = TranslatableList::separated($this->sentences());

        $this->assertSame(
            'El archivo no se pudo leer, o está dañado. La contraseña es incorrecta (Error #7).',
            $list->trans($this->translator())
        );
        $this->assertSame(
            'The file could not be read, or it is damaged. The password is wrong (Error #7).',
            (string) $list
        );
    }

    public function testSentencesCanBeJoinedWithAnotherSeparator(): void
    {
        $list = TranslatableList::separated($this->sentences(), "\n");

        $this->assertSame(
            "El archivo no se pudo leer, o está dañado.\nLa contraseña es incorrecta (Error #7).",
            $list->trans($this->translator())
        );
    }

    public function testAnEnumerationOfSentencesWouldBeAmbiguous(): void
    {
        // The reason of the two ways: the items have their own commas, full
        // stops and conjunctions.
        $sentences = [...$this->sentences(), new TranslatableMessage('Red')];
        $enumeration = TranslatableList::enumeration($sentences);
        $separated = TranslatableList::separated($sentences);

        $this->assertStringContainsString('., ', (string) $enumeration);
        $this->assertStringNotContainsString('., ', (string) $separated);
    }

    public function testTheItemsThatAreStringsAreNotTranslated(): void
    {
        $list = TranslatableList::enumeration(['Red', new TranslatableMessage('Green')]);

        $this->assertSame('Red y Verde', $list->trans($this->translator()));
    }

    public function testAListCanBeAnyIterable(): void
    {
        $generator = (function (): Generator {
            yield 'first' => 'a';
            yield 'second' => 'b';
        })();

        $this->assertSame('a and b', (string) TranslatableList::enumeration($generator));
        $this->assertSame('a b', (string) TranslatableList::separated(['x' => 'a', 'y' => 'b']));
    }

    public function testAnEmptyListIsAnEmptyText(): void
    {
        $this->assertSame('', (string) TranslatableList::enumeration([]));
        $this->assertSame('', (string) TranslatableList::separated([]));
        $this->assertSame('', TranslatableList::enumeration([])->trans($this->translator()));
    }

    public function testAListWithOneItemIsThatItem(): void
    {
        $this->assertSame('a', (string) TranslatableList::enumeration(['a']));
        $this->assertSame('a', (string) TranslatableList::separated(['a']));
        $this->assertSame('0', (string) TranslatableList::enumeration(['0']));
    }

    public function testAListIsATranslatableThatIsStringable(): void
    {
        $list = TranslatableList::enumeration(['a']);

        $this->assertInstanceOf(TranslatableInterface::class, $list);
    }

    public function testAListIsTranslatedWhenItIsAParameterOfAMessage(): void
    {
        $translator = $this->translator();
        $translator->addResource('array', [
            'Colors: {colors}.' => 'Colores: {colors}.',
        ], 'es', 'messages+intl-icu');

        $message = new TranslatableMessage('Colors: {colors}.', ['colors' => TranslatableList::enumeration($this->colors())]);

        $this->assertSame('Colores: Rojo, Verde y Azul.', $message->trans($translator));
        $this->assertSame('Colors: Red, Green, and Blue.', (string) $message);
    }

    public function testAListInsideAnotherListIsTranslated(): void
    {
        $inner = TranslatableList::enumeration($this->colors());
        $outer = TranslatableList::separated(['Colors:', $inner], ' ');

        $this->assertSame('Colors: Rojo, Verde y Azul', $outer->trans($this->translator()));
    }

    public function testAListThatIsSerializedIsTheSame(): void
    {
        $list = TranslatableList::separated($this->sentences());

        $copy = unserialize(serialize($list));

        $this->assertInstanceOf(TranslatableList::class, $copy);
        $this->assertSame($list->trans($this->translator()), $copy->trans($this->translator()));
        $this->assertSame((string) $list, (string) $copy);
    }

    public function testAListIsJsonWithItsItemsAndItsFormat(): void
    {
        $list = TranslatableList::enumeration([new TranslatableMessage('Red', [], 'colors'), 'Green'], IntlListFormatter::TYPE_OR);

        $this->assertSame(
            [
                'items' => [
                    ['message' => 'Red', 'parameters' => [], 'domain' => 'colors', 'defaultLocale' => null],
                    'Green',
                ],
                'separator' => null,
                'type' => IntlListFormatter::TYPE_OR,
                'width' => IntlListFormatter::WIDTH_WIDE,
            ],
            json_decode((string) json_encode($list), true)
        );
    }

    public function testASeparatedListIsJsonWithItsSeparator(): void
    {
        $data = json_decode((string) json_encode(TranslatableList::separated(['a', 'b'], '; ')), true);

        $this->assertIsArray($data);
        $this->assertSame('; ', $data['separator']);
        $this->assertSame(['a', 'b'], $data['items']);
    }

    public function testAnItemThatIsTranslatableButNotJsonIsItsText(): void
    {
        $item = new class () implements TranslatableInterface {
            public function trans(TranslatorInterface $translator, ?string $locale = null): string
            {
                return 'translated';
            }

            public function __toString(): string
            {
                return 'text';
            }
        };

        $data = json_decode((string) json_encode(TranslatableList::enumeration([$item])), true);

        $this->assertIsArray($data);
        $this->assertSame(['text'], $data['items']);
    }

    public function testATypeThatIsNotValidIsRejected(): void
    {
        try {
            TranslatableList::enumeration(['a'], 99);
            $this->fail('It should have failed.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The type of the list is not valid: 99.', $e->getMessage());
            $this->assertSame(
                'El tipo de la lista no es válido: 99.',
                $e->trans(TranslatorFactory::create('es', [], [new TranslationResourceProvider()]))
            );
        }
    }

    public function testAWidthThatIsNotValidIsRejected(): void
    {
        try {
            TranslatableList::enumeration(['a'], IntlListFormatter::TYPE_AND, 99);
            $this->fail('It should have failed.');
        } catch (TranslatableInvalidArgumentException $e) {
            $this->assertSame('The width of the list is not valid: 99.', $e->getMessage());
            $this->assertSame(
                'El ancho de la lista no es válido: 99.',
                $e->trans(TranslatorFactory::create('es', [], [new TranslationResourceProvider()]))
            );
        }
    }
}
