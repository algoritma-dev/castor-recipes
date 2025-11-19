<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\Unit\Aspell;

use Algoritma\CastorRecipes\Aspell\SpellChecker;
use PHPUnit\Framework\TestCase;

final class SpellCheckerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/spellchecker_test_' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testIsAspellInstalled(): void
    {
        $checker = new SpellChecker($this->tempDir);

        self::assertTrue($checker->isAspellInstalled());
    }

    public function testAddToPersonalDictionary(): void
    {
        $checker = new SpellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $result = $checker->addToPersonalDictionary('customword');

        self::assertTrue($result);

        $words = $checker->getPersonalDictionaryWords();
        self::assertContains('customword', $words);
    }

    public function testAddDuplicateWordReturnsFalse(): void
    {
        $checker = new SpellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $checker->addToPersonalDictionary('customword');
        $result = $checker->addToPersonalDictionary('customword');

        self::assertFalse($result);
    }

    public function testGetPersonalDictionaryWords(): void
    {
        $checker = new SpellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $checker->addToPersonalDictionary('word1');
        $checker->addToPersonalDictionary('word2');

        $words = $checker->getPersonalDictionaryWords();

        self::assertContains('word1', $words);
        self::assertContains('word2', $words);
        self::assertCount(2, $words);
    }

    public function testInitPersonalDictionary(): void
    {
        $checker = new SpellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $dictPath = $this->tempDir . '/.aspell.en.pws';
        self::assertFileExists($dictPath);
    }

    public function testCheckPhpCodeFindsErrors(): void
    {
        $checker = new SpellChecker($this->tempDir);

        // Create a PHP file with misspellings
        $testFile = $this->tempDir . '/test.php';
        file_put_contents($testFile, '<?php $userNmae = "test"; // This has erors');

        $errors = $checker->checkPhpCode(['*.php']);

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('test.php', $errors);
    }

    public function testCheckTextFilesFindsErrors(): void
    {
        $checker = new SpellChecker($this->tempDir);

        // Create a text file with misspellings
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, 'This is a tset with misspeling');

        $errors = $checker->checkTextFiles(['*.txt']);

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('test.txt', $errors);
    }

    public function testCheckAllCombinesBothChecks(): void
    {
        $checker = new SpellChecker($this->tempDir);

        // Create both types of files
        file_put_contents($this->tempDir . '/test.php', '<?php // eror');
        file_put_contents($this->tempDir . '/test.txt', 'tset');

        $errors = $checker->checkAll();

        self::assertGreaterThanOrEqual(2, \count($errors));
    }

    public function testPersonalDictionaryFiltersErrors(): void
    {
        // First, add word to dictionary
        $checker = new SpellChecker($this->tempDir);
        $checker->initPersonalDictionary();
        $checker->addToPersonalDictionary('customword');

        // Create a NEW checker instance to reload the dictionary
        $checker = new SpellChecker($this->tempDir);

        // Create a file with our custom word
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, 'This contains customword which should not be flagged');

        $errors = $checker->checkTextFiles(['*.txt']);

        // Should be empty or not contain 'customword'
        if ($errors !== []) {
            foreach ($errors as $fileErrors) {
                $words = array_column($fileErrors, 'word');
                self::assertNotContains('customword', $words);
            }
        } else {
            $this->expectNotToPerformAssertions();
        }
    }

    public function testWordsAreSortedInDictionary(): void
    {
        $checker = new SpellChecker($this->tempDir);
        $checker->initPersonalDictionary();

        $checker->addToPersonalDictionary('zebra');
        $checker->addToPersonalDictionary('apple');
        $checker->addToPersonalDictionary('banana');

        $words = $checker->getPersonalDictionaryWords();

        self::assertEquals(['apple', 'banana', 'zebra'], $words);
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
}
