<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Translation\Lint;

/**
 * What an audit of a package found.
 *
 * It only has facts, and each one is a fact about what was read: the code of the
 * directory that was audited. It does not say whether any of them is a problem:
 * that is for the test of each package to say, by asserting that the lists it
 * cares about are empty.
 *
 *     $this->assertSame([], $report->describe($report->missingTranslations));
 *
 * Part of the lint tools: it is for tools and tests, never for the code that
 * runs the package.
 */
final readonly class TranslationAuditReport
{
    /**
     * @param string $directory The directory that was audited.
     * @param list<MessageReference> $dynamicMessages Messages that are not a
     * literal, so they can not be checked by reading the code.
     * @param list<MessageReference> $missingTranslations Messages that have no
     * entry in the catalogues, in their domain.
     * @param list<array{domain: string, id: string}> $notUsedBySources Entries of
     * the catalogues, in any domain, that no message of the code that was read
     * uses. It is relative to what was read: an entry can be used by something
     * that was not, for example a template.
     * @param list<ThrowReference> $notTranslatable Exceptions that are not
     * translatable and were not allowed.
     * @param bool $nothingFound Whether no message was found at all.
     */
    public function __construct(
        public string $directory,
        public array $dynamicMessages,
        public array $missingTranslations,
        public array $notUsedBySources,
        public array $notTranslatable,
        public bool $nothingFound
    ) {
    }

    /**
     * Turns findings into lines, one each, for the message of a failed test.
     *
     * It says where each one is (relative to the audited directory) and what it
     * is: a message that can not be read, by its function and its call; a
     * message, by its id and domain; an exception, by its class; an entry of a
     * catalogue, by its id and domain. It does not say whether they are a
     * problem.
     *
     * @param list<MessageReference|ThrowReference|array{domain: string, id: string}> $findings
     * @return list<string>
     */
    public function describe(array $findings): array
    {
        $lines = [];

        foreach ($findings as $finding) {
            $lines[] = match (true) {
                $finding instanceof MessageReference => $this->where($finding->file, $finding->line) . ' ' . (
                    $finding->isDynamic()
                        ? $finding->identity()
                        : sprintf('"%s" [%s]', $finding->id, $finding->domain)
                ),
                $finding instanceof ThrowReference => $this->where($finding->file, $finding->line) . ' ' . $this->describeThrow($finding),
                default => sprintf('"%s" [%s]', $finding['id'], $finding['domain']),
            };
        }

        return $lines;
    }

    private function describeThrow(ThrowReference $reference): string
    {
        return $reference->class
            . ($reference->isDeclaration() ? ' extends ' . $reference->extends : '')
            . ($reference->loadable ? '' : ' (it could not be loaded)');
    }

    private function where(string $file, int $line): string
    {
        $prefix = rtrim($this->directory, '/') . '/';

        return sprintf('%s:%d', str_starts_with($file, $prefix) ? substr($file, strlen($prefix)) : $file, $line);
    }
}
