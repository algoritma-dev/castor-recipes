<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\Unit\Aspell;

use Algoritma\CastorRecipes\Aspell\MisspellingCollectorHandler;
use PhpSpellcheck\Misspelling;
use PHPUnit\Framework\TestCase;

final class MisspellingCollectorHandlerTest extends TestCase
{
    public function testHandleCollectsMisspellings(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings = [
            new Misspelling('tset', 0, 1, [], ['file' => '/home/project/test.php']),
            new Misspelling('eror', 10, 1, [], ['file' => '/home/project/test.php']),
        ];

        $handler->handle($misspellings);

        $errors = $handler->getErrors();

        self::assertArrayHasKey('test.php', $errors);
        self::assertCount(2, $errors['test.php']);
        self::assertEquals('tset', $errors['test.php'][0]['word']);
        self::assertEquals('eror', $errors['test.php'][1]['word']);
    }

    public function testHandleGroupsByFile(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings1 = [
            new Misspelling('tset', 0, 1, [], ['file' => '/home/project/file1.php']),
        ];

        $misspellings2 = [
            new Misspelling('eror', 0, 1, [], ['file' => '/home/project/file2.php']),
        ];

        $handler->handle($misspellings1);
        $handler->handle($misspellings2);

        $errors = $handler->getErrors();

        self::assertArrayHasKey('file1.php', $errors);
        self::assertArrayHasKey('file2.php', $errors);
        self::assertCount(1, $errors['file1.php']);
        self::assertCount(1, $errors['file2.php']);
    }

    public function testHandleStripsProjectRoot(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings = [
            new Misspelling('tset', 0, 1, [], ['file' => '/home/project/src/subfolder/test.php']),
        ];

        $handler->handle($misspellings);

        $errors = $handler->getErrors();

        self::assertArrayHasKey('src/subfolder/test.php', $errors);
        self::assertArrayNotHasKey('/home/project/src/subfolder/test.php', $errors);
    }

    public function testHandleWithUnknownFile(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings = [
            new Misspelling('tset', 0, 1, [], []),
        ];

        $handler->handle($misspellings);

        $errors = $handler->getErrors();

        self::assertArrayHasKey('unknown', $errors);
    }

    public function testResetClearsErrors(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings = [
            new Misspelling('tset', 0, 1, [], ['file' => '/home/project/test.php']),
        ];

        $handler->handle($misspellings);
        self::assertNotEmpty($handler->getErrors());

        $handler->reset();
        self::assertEmpty($handler->getErrors());
    }

    public function testHandleEmptyIterable(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $handler->handle([]);

        self::assertEmpty($handler->getErrors());
    }

    public function testHandleAccumulatesMultipleCalls(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings1 = [
            new Misspelling('tset', 0, 1, [], ['file' => '/home/project/test.php']),
        ];

        $misspellings2 = [
            new Misspelling('eror', 0, 1, [], ['file' => '/home/project/test.php']),
        ];

        $handler->handle($misspellings1);
        $handler->handle($misspellings2);

        $errors = $handler->getErrors();

        self::assertCount(2, $errors['test.php']);
    }
}
