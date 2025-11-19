<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\Unit\Aspell;

use Algoritma\CastorRecipes\Aspell\AspellChecker;
use PHPUnit\Framework\TestCase;

/**
 * Tests for legacy AspellChecker class
 * Note: This class is being replaced by SpellChecker but kept for backward compatibility
 */
final class AspellCheckerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aspell_checker_test_' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testIsAspellInstalled(): void
    {
        $this->markTestSkipped('AspellChecker requires Castor container to be initialized');
    }

    public function testInitPersonalDictionary(): void
    {
        $checker = new AspellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $dictPath = $this->tempDir . '/.aspell.en.pws';
        $this->assertFileExists($dictPath);

        $content = file_get_contents($dictPath);
        $this->assertStringStartsWith('personal_ws-1.1 en', $content);
    }

    public function testAddToPersonalDictionary(): void
    {
        $checker = new AspellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $result = $checker->addToPersonalDictionary('testword');

        $this->assertTrue($result);

        $words = $checker->getPersonalDictionaryWords();
        $this->assertContains('testword', $words);
    }

    public function testAddDuplicateWordToPersonalDictionary(): void
    {
        $checker = new AspellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $checker->addToPersonalDictionary('testword');
        $result = $checker->addToPersonalDictionary('testword');

        $this->assertFalse($result);

        $words = $checker->getPersonalDictionaryWords();
        // Count occurrences of 'testword' - should be exactly 1
        $count = count(array_filter($words, fn(string $w): bool => $w === 'testword'));
        $this->assertEquals(1, $count);
    }

    public function testGetPersonalDictionaryWords(): void
    {
        $checker = new AspellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $checker->addToPersonalDictionary('apple');
        $checker->addToPersonalDictionary('banana');
        $checker->addToPersonalDictionary('cherry');

        $words = $checker->getPersonalDictionaryWords();

        $this->assertCount(3, $words);
        $this->assertContains('apple', $words);
        $this->assertContains('banana', $words);
        $this->assertContains('cherry', $words);
    }

    public function testGetPersonalDictionaryWordsReturnsEmptyArrayWhenNoDictionary(): void
    {
        $checker = new AspellChecker($this->tempDir);

        $words = $checker->getPersonalDictionaryWords();

        $this->assertEmpty($words);
    }

    public function testPersonalDictionaryWordsAreSorted(): void
    {
        $checker = new AspellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $checker->addToPersonalDictionary('zebra');
        $checker->addToPersonalDictionary('apple');
        $checker->addToPersonalDictionary('banana');

        $words = $checker->getPersonalDictionaryWords();

        $this->assertEquals(['apple', 'banana', 'zebra'], $words);
    }

    public function testCheckPhpCodeWithValidCode(): void
    {
        $this->markTestSkipped('AspellChecker requires Castor container to be initialized');
    }

    public function testCheckTextFilesWithValidText(): void
    {
        $this->markTestSkipped('AspellChecker requires Castor container to be initialized');
    }

    public function testCheckAllCombinesBothChecks(): void
    {
        $this->markTestSkipped('AspellChecker requires Castor container to be initialized');
    }
}
