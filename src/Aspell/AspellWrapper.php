<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Aspell;

use PhpSpellcheck\Misspelling;
use PhpSpellcheck\Spellchecker\SpellcheckerInterface;

/**
 * Custom Aspell spellchecker with support for multiple personal dictionaries.
 */
final class AspellWrapper implements SpellcheckerInterface
{
    /**
     * @var list<string>
     */
    private array $personalDictionaries = [];

    public function __construct(
        private readonly string $binaryPath = 'aspell',
    ) {}

    /**
     * Add a personal dictionary.
     */
    public function addPersonalDictionary(string $path): void
    {
        if (file_exists($path)) {
            $this->personalDictionaries[] = $path;
        }
    }

    public function check(string $text, array $languages = [], array $context = []): iterable
    {
        $language = $languages[0] ?? 'en_US';

        // Build aspell command (without personal dictionary, we'll filter manually)
        $cmd = escapeshellcmd($this->binaryPath) . ' --encoding=utf-8 --lang=' . escapeshellarg($language) . ' list';

        // Create temporary file with text
        $tmpFile = tempnam(sys_get_temp_dir(), 'aspell_');
        file_put_contents($tmpFile, $text);

        try {
            $output = shell_exec($cmd . ' < ' . escapeshellarg($tmpFile));

            if ($output === null || trim($output) === '') {
                return [];
            }

            $misspelledWords = array_unique(array_filter(explode("\n", trim($output))));

            // Load all dictionary words
            $dictionaryWords = $this->loadAllDictionaryWords();

            $misspellings = [];
            foreach ($misspelledWords as $word) {
                // Skip if word is in any of our dictionaries
                if (\in_array(strtolower($word), $dictionaryWords, true)) {
                    continue;
                }

                $misspellings[] = new Misspelling(
                    $word,
                    0, // offset
                    0, // line number
                    [], // suggestions (aspell list mode doesn't provide them)
                    $context
                );
            }

            return $misspellings;
        } finally {
            unlink($tmpFile);
        }
    }

    public function getSupportedLanguages(): iterable
    {
        yield from ['en_US', 'en_GB', 'it_IT', 'fr_FR', 'de_DE', 'es_ES'];
    }

    /**
     * Load words from all personal dictionaries.
     *
     * @return list<string>
     */
    private function loadAllDictionaryWords(): array
    {
        $words = [];

        foreach ($this->personalDictionaries as $dictPath) {
            if (! file_exists($dictPath)) {
                continue;
            }

            $content = file_get_contents($dictPath);
            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                $line = trim($line);
                // Skip header line and empty lines
                if ($line === '') {
                    continue;
                }
                if (str_starts_with($line, 'personal_ws-')) {
                    continue;
                }
                $words[] = strtolower($line);
            }
        }

        return array_unique($words);
    }
}
