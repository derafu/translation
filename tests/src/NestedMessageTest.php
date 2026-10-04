<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation;

use Derafu\Translation\Exception\Core\TranslatableException;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * A message can have another translatable message as a parameter (a nested
 * message), so both are translated: the sentence around and the one inside.
 *
 * It is how an exception adds context to a message that is its own (for example
 * the component that failed), and how a message of another package is put inside
 * a sentence of this one, so whoever reads it knows that something failed even
 * if the text inside is not understood.
 */
#[CoversClass(TranslatableMessage::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
final class NestedMessageTest extends TestCase
{
    private function translator(array $messages): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $messages, 'es', 'errors+intl-icu');

        return $translator;
    }

    private function exception(): TranslatableException
    {
        return new TranslatableException([
            'Component {component}: {message}',
            'component' => 'accordion',
            'message' => new TranslatableMessage('Item title is required.', [], 'errors'),
        ]);
    }

    public function testBothMessagesAreFormattedWithoutATranslator(): void
    {
        $this->assertSame(
            'Component accordion: Item title is required.',
            $this->exception()->getMessage()
        );
    }

    public function testBothMessagesAreTranslated(): void
    {
        $translator = $this->translator([
            'Component {component}: {message}' => 'Componente {component}: {message}',
            'Item title is required.' => 'El título del ítem es obligatorio.',
        ]);

        $this->assertSame(
            'Componente accordion: El título del ítem es obligatorio.',
            $this->exception()->trans($translator)
        );
    }

    /**
     * A message of the inside that has no entry is shown as it is written, whole,
     * and not with the name of its parameters replaced inside the braces.
     */
    public function testAnInnerMessageWithoutAnEntryIsShownAsItIsWritten(): void
    {
        $translator = $this->translator([
            'Component {component}: {message}' => 'Componente {component}: {message}',
        ]);

        $this->assertSame(
            'Componente accordion: Item title is required.',
            $this->exception()->trans($translator)
        );
    }

    public function testATextThatIsNotOursGoesInsideASentenceThatIs(): void
    {
        $exception = new TranslatableException([
            '{message} {problem}',
            'message' => new TranslatableMessage('Cannot read the certificate.', [], 'errors'),
            'problem' => new TranslatableMessage('A problem happened: {message}', ['message' => 'error:0909006C'], 'errors'),
        ]);
        $translator = $this->translator([
            '{message} {problem}' => '{message} {problem}',
            'Cannot read the certificate.' => 'No se pudo leer el certificado.',
            'A problem happened: {message}' => 'Ocurrió un problema: {message}',
        ]);

        $this->assertSame(
            'Cannot read the certificate. A problem happened: error:0909006C',
            $exception->getMessage()
        );
        $this->assertSame(
            'No se pudo leer el certificado. Ocurrió un problema: error:0909006C',
            $exception->trans($translator)
        );
    }
}
