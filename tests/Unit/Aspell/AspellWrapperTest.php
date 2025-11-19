<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\Unit\Aspell;

use Algoritma\CastorRecipes\Aspell\AspellWrapper;
use PHPUnit\Framework\TestCase;

final class AspellWrapperTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/aspell_test_' . uniqid('', true);
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                array_map('unlink', $files);
            }
            @rmdir($this->tempDir);
        }
    }

    public function testCheckWithoutDictionary(): void
    {
        $aspell = new AspellWrapper();
        $misspellings = $aspell->check('This is a tset with misspeling', ['en_US'], []);

        $words = array_map(fn(\PhpSpellcheck\MisspellingInterface $m): string => $m->getWord(), iterator_to_array($misspellings));

        $this->assertContains('tset', $words);
        $this->assertContains('misspeling', $words);
    }

    public function testCheckWithPersonalDictionary(): void
    {
        // Create personal dictionary
        $dictPath = $this->tempDir . '/.aspell.en.pws';
        file_put_contents($dictPath, "personal_ws-1.1 en 2\ntset\nmisspeling\n");

        $aspell = new AspellWrapper();
        $aspell->addPersonalDictionary($dictPath);

        $misspellings = $aspell->check('This is a tset with misspeling', ['en_US'], []);

        $words = array_map(fn(\PhpSpellcheck\MisspellingInterface $m): string => $m->getWord(), iterator_to_array($misspellings));

        // Words in dictionary should not be reported as misspellings
        $this->assertNotContains('tset', $words);
        $this->assertNotContains('misspeling', $words);
    }

    public function testCheckWithMultipleDictionaries(): void
    {
        // Create two dictionaries
        $dict1 = $this->tempDir . '/.aspell1.en.pws';
        file_put_contents($dict1, "personal_ws-1.1 en 1\ntset\n");

        $dict2 = $this->tempDir . '/.aspell2.en.pws';
        file_put_contents($dict2, "personal_ws-1.1 en 1\nmisspeling\n");

        $aspell = new AspellWrapper();
        $aspell->addPersonalDictionary($dict1);
        $aspell->addPersonalDictionary($dict2);

        $misspellings = $aspell->check('This is a tset with misspeling', ['en_US'], []);

        $words = array_map(fn(\PhpSpellcheck\MisspellingInterface $m): string => $m->getWord(), iterator_to_array($misspellings));

        // Words in both dictionaries should not be reported
        $this->assertNotContains('tset', $words);
        $this->assertNotContains('misspeling', $words);
    }

    public function testCheckWithCorrectText(): void
    {
        $aspell = new AspellWrapper();
        $misspellings = $aspell->check('This is correct text', ['en_US'], []);

        $this->assertEmpty(iterator_to_array($misspellings));
    }

    public function testGetSupportedLanguages(): void
    {
        $aspell = new AspellWrapper();
        $languages = $aspell->getSupportedLanguages();

        $languagesArray = iterator_to_array($languages);

        $this->assertContains('en_US', $languagesArray);
        $this->assertContains('it_IT', $languagesArray);
        $this->assertContains('fr_FR', $languagesArray);
    }

    public function testAddNonExistentDictionary(): void
    {
        $aspell = new AspellWrapper();
        $aspell->addPersonalDictionary('/non/existent/path.pws');

        // Should not throw exception, just ignore
        $misspellings = $aspell->check('tset', ['en_US'], []);
        $words = array_map(fn(\PhpSpellcheck\MisspellingInterface $m): string => $m->getWord(), iterator_to_array($misspellings));

        $this->assertContains('tset', $words);
    }

    public function testMisspellingContext(): void
    {
        $aspell = new AspellWrapper();
        $context = ['file' => 'test.php', 'line' => 10];
        $misspellings = $aspell->check('tset', ['en_US'], $context);

        $misspelling = iterator_to_array($misspellings)[0];

        $this->assertEquals($context, $misspelling->getContext());
    }
}
