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

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use FilesystemIterator;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds the messages that the code builds to be translated: the ones given to
 * a translatable exception or to a `TranslatableMessage`.
 *
 * It reads the code (nothing runs), so a comment or a string that only looks
 * like a `new` is never taken for one. The classes are told apart by what they
 * are (a throwable that is translatable), not by their name, so they have to be
 * loadable: a class that can not be loaded is not known to be translatable and
 * is left out.
 *
 * It only finds references. Whether each one has an entry in a catalogue is for
 * whoever uses it to decide, with the catalogues of the package that was
 * scanned: that is a fact about which code belongs to which catalogues, which
 * only the user knows.
 *
 * Part of the lint tools: it is for tools and tests, never for the code that
 * runs the package. It needs `nikic/php-parser`.
 */
final class MessageReferenceScanner
{
    /**
     * Finds the references to messages of a PHP file, in order of line.
     *
     * @param string $file Path of the file.
     * @return list<MessageReference>
     * @throws InvalidArgumentException If the file does not exist.
     * @throws \PhpParser\Error If the file can not be parsed. It is not hidden:
     * a file that can not be read would look like one with no messages.
     */
    public function scanFile(string $file): array
    {
        if (!is_file($file)) {
            throw new InvalidArgumentException([
                'The file {file} does not exist.',
                'file' => $file,
            ]);
        }

        $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse(
            (string) file_get_contents($file)
        ) ?? [];

        $visitor = new MessageReferenceVisitor($file);
        $traverser = new NodeTraverser(new NameResolver(), $visitor);
        $traverser->traverse($ast);

        $references = $visitor->references();
        usort($references, fn (MessageReference $a, MessageReference $b) => $a->line <=> $b->line);

        return $references;
    }

    /**
     * Finds the references to messages of every PHP file of a directory (and
     * its subdirectories), in order of file and then of line.
     *
     * @param string $directory Path of the directory.
     * @return list<MessageReference>
     * @throws InvalidArgumentException If the directory does not exist.
     */
    public function scanDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException([
                'The directory {directory} does not exist.',
                'directory' => $directory,
            ]);
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        $references = [];
        foreach ($files as $file) {
            array_push($references, ...$this->scanFile($file));
        }

        return $references;
    }
}
