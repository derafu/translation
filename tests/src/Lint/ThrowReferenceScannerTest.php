<?php

declare(strict_types=1);

/**
 * Derafu: Translation - Translation Library with Exception Support.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsTranslation\Lint;

use Derafu\TestsTranslation\Lint\Fixture\FixtureNativeChildException;
use Derafu\Translation\Lint\ThrowReference;
use Derafu\Translation\Lint\ThrowReferenceScanner;
use Derafu\Translation\Lint\ThrowReferenceVisitor;
use Derafu\Translation\Trait\TranslatableExceptionTrait;
use Derafu\Translation\TranslatableMessage;
use InvalidArgumentException;
use PhpParser\Error as ParseError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

/**
 * Finds the exceptions that the code throws, or declares, that are not
 * translatable.
 *
 * It is the other half of the message scanner: that one reads the messages of
 * the translatable exceptions, this one finds the exceptions that are not, so
 * none is left out by looking at only the first ones. It reads the code (nothing
 * runs) and tells the classes apart by what they are, not by their name.
 *
 * It only finds references. Whether one is a problem is for whoever uses it to
 * decide: an exception of another package that can not be translatable is
 * allowed or not by the one who uses it.
 */
#[CoversClass(ThrowReferenceScanner::class)]
#[CoversClass(ThrowReference::class)]
#[CoversClass(ThrowReferenceVisitor::class)]
#[UsesTrait(TranslatableExceptionTrait::class)]
#[UsesClass(TranslatableMessage::class)]
final class ThrowReferenceScannerTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->remove($directory);
        }
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }

        rmdir($path);
    }

    private function directory(): string
    {
        $directory = sys_get_temp_dir() . '/translation-lint-throw-' . uniqid('', true);
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }

    /**
     * Scans the body of a function written in a temporary file.
     *
     * @return list<ThrowReference>
     */
    private function scan(string $body): array
    {
        $file = $this->directory() . '/code.php';
        file_put_contents($file, <<<PHP
            <?php

            namespace Scanned;

            use Derafu\\Translation\\Exception\\Core\\TranslatableException;
            use Derafu\\Translation\\Exception\\Logic\\TranslatableInvalidArgumentException as InvalidArgumentException;
            use RuntimeException;

            function probe(): void
            {
                {$body}
            }
            PHP);

        return (new ThrowReferenceScanner())->scanFile($file);
    }

    /**
     * @param list<ThrowReference> $references
     * @return list<string>
     */
    private function classes(array $references): array
    {
        return array_map(fn (ThrowReference $r) => $r->class, $references);
    }

    public function testItFindsANativeExceptionThatIsThrown(): void
    {
        $references = $this->scan("throw new RuntimeException('Failed.');");

        $this->assertCount(1, $references);
        $this->assertSame(RuntimeException::class, $references[0]->class);
        $this->assertTrue($references[0]->loadable);
        $this->assertFalse($references[0]->isDeclaration());
        $this->assertNull($references[0]->extends);
        $this->assertSame(11, $references[0]->line);
        $this->assertStringEndsWith('/code.php', $references[0]->file);
    }

    public function testItFindsANativeExceptionWrittenWithItsFullName(): void
    {
        $this->assertSame(
            [InvalidArgumentException::class],
            $this->classes($this->scan("throw new \\InvalidArgumentException('Failed.');"))
        );
    }

    public function testItFindsAnErrorThatIsNotAnException(): void
    {
        $this->assertSame([TypeError::class], $this->classes($this->scan("throw new \\TypeError('Failed.');")));
    }

    public function testItIgnoresTheExceptionsThatAreTranslatable(): void
    {
        $references = $this->scan(
            "throw new TranslatableException('Failed.');\n"
            // `InvalidArgumentException` here is the translatable one, by an alias.
            . "throw new InvalidArgumentException('Failed.');"
        );

        $this->assertSame([], $references);
    }

    /**
     * What is thrown must be a throwable: a class that is not one is not an
     * exception that is not translatable.
     */
    public function testItIgnoresAClassThatIsNotThrowable(): void
    {
        $this->assertSame([], $this->scan("throw new \\stdClass();"));
    }

    /**
     * A `throw` that contains an anonymous class with another `throw` inside is
     * read after the inner one: the references still come out in order of line.
     */
    public function testTheReferencesOfAFileAreInOrderOfLine(): void
    {
        $references = $this->scan(
            "throw new class ('Outer.') extends RuntimeException {\n"
            . "    public function inner(): never\n"
            . "    {\n"
            . "        throw new \\LogicException('Inner.');\n"
            . "    }\n"
            . "};"
        );

        $this->assertSame([RuntimeException::class, 'LogicException'], $this->classes($references));
        $this->assertLessThan($references[1]->line, $references[0]->line);
    }

    public function testItFindsAThrowThatIsAnExpression(): void
    {
        $references = $this->scan("\$value = \$other ?? throw new RuntimeException('Missing.');");

        $this->assertSame([RuntimeException::class], $this->classes($references));
    }

    public function testItFindsAnExceptionOfTheClassThatIsBeingRead(): void
    {
        $references = array_filter(
            (new ThrowReferenceScanner())->scanDirectory(__DIR__ . '/Fixture'),
            fn (ThrowReference $r) => !$r->isDeclaration()
        );

        // `new static` and `new self` are the class that is being read.
        $this->assertSame(
            [FixtureNativeChildException::class, FixtureNativeChildException::class],
            $this->classes(array_values($references))
        );
    }

    public function testItFindsAClassThatExtendsAnExceptionThatIsNotTranslatable(): void
    {
        $references = array_values(array_filter(
            (new ThrowReferenceScanner())->scanDirectory(__DIR__ . '/Fixture'),
            fn (ThrowReference $r) => $r->isDeclaration()
        ));

        $this->assertCount(1, $references);
        $this->assertSame(FixtureNativeChildException::class, $references[0]->class);
        $this->assertSame(RuntimeException::class, $references[0]->extends);
        $this->assertTrue($references[0]->loadable);
    }

    /**
     * What counts is whether the class is translatable, not what it extends: a
     * class can extend a native exception and be translatable itself.
     */
    public function testItIgnoresAClassThatExtendsANativeExceptionButIsTranslatable(): void
    {
        $references = array_filter(
            (new ThrowReferenceScanner())->scanDirectory(__DIR__ . '/Fixture'),
            fn (ThrowReference $r) => str_ends_with($r->file, 'FixtureSelfTranslatableException.php')
        );

        $this->assertSame([], array_values($references));
    }

    public function testItFindsAnAnonymousClassThatExtendsANativeException(): void
    {
        $references = $this->scan("throw new class ('Failed.') extends RuntimeException {};");

        $this->assertSame([RuntimeException::class], $this->classes($references));
    }

    public function testItIgnoresAnAnonymousClassThatExtendsATranslatableException(): void
    {
        $this->assertSame([], $this->scan("throw new class ('Failed.') extends TranslatableException {};"));
    }

    /**
     * What is thrown is an exception, so a class that can not be loaded is an
     * exception that is not known: finding nothing would look like a clean
     * result.
     */
    public function testAThrownClassThatCanNotBeLoadedIsReportedAsUnknown(): void
    {
        $references = $this->scan("throw new \\Not\\Installed\\SomeException('Failed.');");

        $this->assertCount(1, $references);
        $this->assertSame('Not\\Installed\\SomeException', $references[0]->class);
        $this->assertFalse($references[0]->loadable);
    }

    public function testAThrownClassThatFailsWhenItIsLoadedIsReportedAsUnknown(): void
    {
        $loader = static function (string $class): void {
            if ($class === 'Scanned\\Unloadable') {
                throw new \Error('Interface "Missing\\Dependency" not found');
            }
        };
        spl_autoload_register($loader);

        try {
            $references = $this->scan("throw new Unloadable('Failed.');");
        } finally {
            spl_autoload_unregister($loader);
        }

        $this->assertCount(1, $references);
        $this->assertFalse($references[0]->loadable);
    }

    /**
     * A class that is declared extending something that can not be loaded may
     * not be an exception at all, so it is not reported.
     */
    public function testADeclaredClassThatExtendsSomethingThatCanNotBeLoadedIsIgnored(): void
    {
        $file = $this->directory() . '/declared.php';
        file_put_contents($file, "<?php\nnamespace Scanned;\nclass Child extends \\Not\\Installed\\Parent {}\n");

        $this->assertSame([], (new ThrowReferenceScanner())->scanFile($file));
    }

    public function testItIgnoresWhatIsNotAnException(): void
    {
        $references = $this->scan(
            "throw \$exception;\n"
            . "// throw new RuntimeException('In a comment.');\n"
            . "\$text = \"throw new RuntimeException('In a string.')\";\n"
            . "/* throw new RuntimeException('In a block comment.'); */\n"
            . "\$object = new \\stdClass();\n"
            . "\$other = new \$class();\n"
            . "\$unknown = new \\Not\\Installed\\Something();"
        );

        $this->assertSame([], $references);
    }

    /**
     * An exception that is made and thrown later, or given back by a factory, is
     * found where it is made: it is not a `throw`, but it is the same exception.
     */
    public function testItFindsAnExceptionThatIsMadeAndNotThrownThere(): void
    {
        $references = $this->scan(
            "\$exception = new RuntimeException('Made here.');\n"
            . "throw \$exception;"
        );

        $this->assertSame([RuntimeException::class], $this->classes($references));
        $this->assertSame(11, $references[0]->line);
    }

    public function testItFindsAnExceptionThatAFactoryGivesBack(): void
    {
        $references = $this->scan("return new \\LogicException('Made by a factory.');");

        $this->assertSame(['LogicException'], $this->classes($references));
    }

    /**
     * What is thrown is an exception, but with a class that is a variable it is
     * not known which one: it is reported as unknown, with the expression that
     * makes the class.
     */
    public function testAThrownClassThatIsAVariableIsReportedAsUnknown(): void
    {
        $references = $this->scan("throw new \$class('Failed.');");

        $this->assertCount(1, $references);
        $this->assertSame('$class', $references[0]->class);
        $this->assertFalse($references[0]->loadable);
    }

    public function testItScansEveryPhpFileOfADirectoryInOrder(): void
    {
        $directory = $this->directory();
        mkdir($directory . '/sub');
        file_put_contents($directory . '/b.php', "<?php\nthrow new \\RuntimeException('In b.');");
        file_put_contents($directory . '/a.php', "<?php\nthrow new \\LogicException('In a.');");
        file_put_contents($directory . '/sub/c.php', "<?php\nthrow new \\DomainException('In c.');");
        file_put_contents($directory . '/notes.txt', "throw new \\Exception('Not php.');");

        $references = (new ThrowReferenceScanner())->scanDirectory($directory);

        $this->assertSame(
            ['LogicException', 'RuntimeException', 'DomainException'],
            $this->classes($references)
        );
    }

    public function testItFailsWhenTheDirectoryDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ThrowReferenceScanner())->scanDirectory('/does/not/exist');
    }

    public function testItFailsWhenTheFileDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ThrowReferenceScanner())->scanFile('/does/not/exist.php');
    }

    /**
     * A file that can not be read would look like one that throws nothing.
     */
    public function testASyntaxErrorIsNotHidden(): void
    {
        $file = $this->directory() . '/broken.php';
        file_put_contents($file, '<?php throw new RuntimeException(;');

        $this->expectException(ParseError::class);

        (new ThrowReferenceScanner())->scanFile($file);
    }
}
