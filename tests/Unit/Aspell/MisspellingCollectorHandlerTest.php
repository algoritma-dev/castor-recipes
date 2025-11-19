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

        $this->assertArrayHasKey('test.php', $errors);
        $this->assertCount(2, $errors['test.php']);
        $this->assertEquals('tset', $errors['test.php'][0]['word']);
        $this->assertEquals('eror', $errors['test.php'][1]['word']);
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

        $this->assertArrayHasKey('file1.php', $errors);
        $this->assertArrayHasKey('file2.php', $errors);
        $this->assertCount(1, $errors['file1.php']);
        $this->assertCount(1, $errors['file2.php']);
    }

    public function testHandleStripsProjectRoot(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings = [
            new Misspelling('tset', 0, 1, [], ['file' => '/home/project/src/subfolder/test.php']),
        ];

        $handler->handle($misspellings);

        $errors = $handler->getErrors();

        $this->assertArrayHasKey('src/subfolder/test.php', $errors);
        $this->assertArrayNotHasKey('/home/project/src/subfolder/test.php', $errors);
    }

    public function testHandleWithUnknownFile(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings = [
            new Misspelling('tset', 0, 1, [], []),
        ];

        $handler->handle($misspellings);

        $errors = $handler->getErrors();

        $this->assertArrayHasKey('unknown', $errors);
    }

    public function testResetClearsErrors(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $misspellings = [
            new Misspelling('tset', 0, 1, [], ['file' => '/home/project/test.php']),
        ];

        $handler->handle($misspellings);
        $this->assertNotEmpty($handler->getErrors());

        $handler->reset();
        $this->assertEmpty($handler->getErrors());
    }

    public function testHandleEmptyIterable(): void
    {
        $handler = new MisspellingCollectorHandler('/home/project');

        $handler->handle([]);

        $this->assertEmpty($handler->getErrors());
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

        $this->assertCount(2, $errors['test.php']);
    }
}
