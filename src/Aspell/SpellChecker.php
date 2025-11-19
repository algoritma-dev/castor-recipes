<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Aspell;

use PhpSpellcheck\MisspellingFinder;
use PhpSpellcheck\Text;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Spell checker for PHP code and text files using php-spellchecker library.
 */
final readonly class SpellChecker
{
    private const PERSONAL_DICT_PATH = '.aspell.en.pws';

    private const GLOBAL_DICT_PATH = __DIR__ . '/../../recipes/.aspell.en.pws';

    private AspellWrapper $spellChecker;

    private PhpSourceProcessor $phpProcessor;

    public function __construct(
        private string $projectRoot,
        private string $lang = 'en',
    ) {
        // Create custom aspell with dictionary support
        $this->spellChecker = new AspellWrapper();

        // Add global dictionary (shipped with recipes)
        $globalDict = self::GLOBAL_DICT_PATH;
        if (file_exists($globalDict)) {
            $this->spellChecker->addPersonalDictionary($globalDict);
        }

        // Add project-specific personal dictionary
        $personalDict = $this->getPersonalDictionaryPath();
        if (file_exists($personalDict)) {
            $this->spellChecker->addPersonalDictionary($personalDict);
        }

        // Initialize PHP processor
        $this->phpProcessor = new PhpSourceProcessor();
    }

    /**
     * Check text files (Markdown, txt, YAML, JSON translation files).
     *
     * @param list<string> $patterns
     *
     * @return array<string, list<array{word: string, context: list<string>}>>
     */
    public function checkTextFiles(array $patterns = ['*.md', '*.txt', 'messages.*.yaml', 'messages.*.yml', 'messages.*.json']): array
    {
        $finder = $this->createFinder($patterns);

        return $this->checkFiles($finder, false);
    }

    /**
     * Check PHP code using custom processor.
     *
     * @param list<string> $patterns
     *
     * @return array<string, list<array{word: string, context: list<string>}>>
     */
    public function checkPhpCode(array $patterns = ['*.php']): array
    {
        $finder = $this->createFinder($patterns);

        return $this->checkFiles($finder, true);
    }

    /**
     * Check all files (both text and code).
     *
     * @return array<string, list<array{word: string, context: list<string>}>>
     */
    public function checkAll(): array
    {
        $textErrors = $this->checkTextFiles();
        $codeErrors = $this->checkPhpCode();

        return array_merge($textErrors, $codeErrors);
    }

    /**
     * Check if aspell is installed.
     */
    public function isAspellInstalled(): bool
    {
        $result = shell_exec('which aspell 2>/dev/null');

        return ! \in_array($result, ['', '0', false], true) && $result !== null;
    }

    /**
     * Add word to personal dictionary.
     */
    public function addToPersonalDictionary(string $word): bool
    {
        $words = $this->loadPersonalDictionary();

        $word = strtolower(trim($word));
        if (\in_array($word, $words, true)) {
            return false; // Already exists
        }

        $words[] = $word;
        sort($words);

        return $this->savePersonalDictionary($words);
    }

    /**
     * Get words from personal dictionary.
     *
     * @return list<string>
     */
    public function getPersonalDictionaryWords(): array
    {
        return $this->loadPersonalDictionary();
    }

    /**
     * Create personal dictionary if it doesn't exist.
     */
    public function initPersonalDictionary(): void
    {
        $dictPath = $this->getPersonalDictionaryPath();

        if (! file_exists($dictPath)) {
            $this->savePersonalDictionary([]);
        }
    }

    /**
     * Create finder with common exclusions.
     *
     * @param list<string> $patterns
     */
    private function createFinder(array $patterns): Finder
    {
        $finder = new Finder();
        $finder->files()
            ->in($this->projectRoot)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->ignoreUnreadableDirs()
            ->ignoreVCSIgnored(true)
            ->notPath('#vendor/#')
            ->notPath('#node_modules/#')
            ->notPath('#\.git/#')
            ->notPath('#var/#')
            ->notPath('#cache/#')
            ->notPath('#\.cache/#')
            ->sortByName();

        // Add patterns
        foreach ($patterns as $pattern) {
            $finder->name($pattern);
        }

        return $finder;
    }

    /**
     * Check files with finder.
     *
     * @return array<string, list<array{word: string, context: list<string>}>>
     */
    private function checkFiles(Finder $finder, bool $isPhpCode): array
    {
        // Create custom handler to collect misspellings
        $handler = new MisspellingCollectorHandler($this->projectRoot);

        // Create MisspellingFinder with or without processor
        $textProcessor = $isPhpCode ? $this->phpProcessor : null;
        $misspellingFinder = new MisspellingFinder($this->spellChecker, $handler, $textProcessor);

        // Check each file
        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $content = $file->getContents();

            $text = new Text($content, ['file' => $file->getRealPath()]);
            $misspellingFinder->find($text, [$this->getAspellLanguageCode()]);
        }

        return $handler->getErrors();
    }

    /**
     * Get aspell language code.
     */
    private function getAspellLanguageCode(): string
    {
        return match ($this->lang) {
            'en' => 'en_US',
            'it' => 'it_IT',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'es' => 'es_ES',
            default => 'en_US',
        };
    }

    private function getPersonalDictionaryPath(): string
    {
        return $this->projectRoot . '/' . self::PERSONAL_DICT_PATH;
    }

    /**
     * @return list<string>
     */
    private function loadPersonalDictionary(): array
    {
        $dictPath = $this->getPersonalDictionaryPath();

        if (! file_exists($dictPath)) {
            return [];
        }

        $content = file_get_contents($dictPath);
        $lines = explode("\n", $content);

        $words = [];
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip header line and empty lines
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, 'personal_ws-')) {
                continue;
            }
            $words[] = $line;
        }

        return $words;
    }

    /**
     * @param list<string> $words
     */
    private function savePersonalDictionary(array $words): bool
    {
        $dictPath = $this->getPersonalDictionaryPath();

        // Aspell personal dictionary format
        $content = \sprintf("personal_ws-1.1 %s %d\n", $this->lang, \count($words));
        $content .= implode("\n", $words) . "\n";

        return file_put_contents($dictPath, $content) !== false;
    }
}
