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
 * Finds the exceptions that the code throws, or declares, that are not
 * translatable.
 *
 * It is the other half of `MessageReferenceScanner`: that one reads the messages
 * of the translatable exceptions, so an exception that is not translatable (a
 * native one, one of another package) is out of its sight. This one finds them.
 *
 * It reads the code (nothing runs), so a comment or a string that only looks like
 * a `throw` is never taken for one. The classes are told apart by what they are
 * (a throwable that is translatable), not by their name, so they have to be
 * loadable: a class that is thrown and can not be loaded is reported as unknown,
 * because finding nothing would look like a clean result.
 *
 * It finds an exception where it is made (`new X`), whether it is thrown there,
 * thrown later or given back by a factory. A `throw new` of a class that can not
 * be known (it can not be loaded, or the class is a variable) is reported as
 * unknown, because what is thrown is an exception. The only thing it can not see
 * is `throw $exception` when the exception was not made in the code that is
 * scanned (for example, one that is caught and thrown again): without the types
 * of the code it is not known which class it is.
 *
 * It only finds references. Whether one is a problem is for whoever uses it to
 * decide: an exception of another package that must be thrown as it is can be
 * allowed.
 *
 * Part of the lint tools: it is for tools and tests, never for the code that
 * runs the package. It needs `nikic/php-parser`.
 */
final class ThrowReferenceScanner
{
    /**
     * Finds the exceptions of a PHP file that are not translatable, in order of
     * line.
     *
     * @param string $file Path of the file.
     * @return list<ThrowReference>
     * @throws InvalidArgumentException If the file does not exist.
     * @throws \PhpParser\Error If the file can not be parsed. It is not hidden:
     * a file that can not be read would look like one that throws nothing.
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

        $visitor = new ThrowReferenceVisitor($file);
        $traverser = new NodeTraverser(new NameResolver(), $visitor);
        $traverser->traverse($ast);

        $references = $visitor->references();
        usort($references, fn (ThrowReference $a, ThrowReference $b) => $a->line <=> $b->line);

        return $references;
    }

    /**
     * Finds the exceptions of every PHP file of a directory (and its
     * subdirectories) that are not translatable, in order of file and then of
     * line.
     *
     * @param string $directory Path of the directory.
     * @return list<ThrowReference>
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
