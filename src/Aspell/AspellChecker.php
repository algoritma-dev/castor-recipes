<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Aspell;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function Castor\capture;
use function Castor\Helper\PathHelper;
use function Castor\run;

final class AspellChecker
{
    private const PERSONAL_DICT_PATH = '.aspell.en.pws';

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $lang = 'en',
    ) {
    }

    /**
     * Check text files (Markdown, txt, YAML, JSON translation files)
     *
     * @return array<string, array<string>>
     */
    public function checkTextFiles(array $patterns = ['*.md', '*.txt', 'messages.*.yaml', 'messages.*.yml', 'messages.*.json']): array
    {
        $finder = $this->createFinder($patterns);

        return $this->checkFiles($finder, false);
    }

    /**
     * Check PHP code by extracting identifiers and splitting them into words
     *
     * @return array<string, array<string>>
     */
    public function checkPhpCode(array $patterns = ['*.php']): array
    {
        $finder = $this->createFinder($patterns);

        return $this->checkFiles($finder, true);
    }

    /**
     * Check all files (both text and code)
     *
     * @return array<string, array<string>>
     */
    public function checkAll(): array
    {
        $textErrors = $this->checkTextFiles();
        $codeErrors = $this->checkPhpCode();

        return array_merge($textErrors, $codeErrors);
    }

    /**
     * Check if aspell is installed
     */
    public function isAspellInstalled(): bool
    {
        $result = capture('which aspell', onFailure: 'false');

        return (bool) $result;
    }

    /**
     * Add word to personal dictionary
     */
    public function addToPersonalDictionary(string $word): bool
    {
        $dictPath = $this->getPersonalDictionaryPath();
        $words = $this->loadPersonalDictionary();

        $word = strtolower(trim($word));
        if (in_array($word, $words, true)) {
            return false; // Already exists
        }

        $words[] = $word;
        sort($words);

        return $this->savePersonalDictionary($words);
    }

    /**
     * Get words from personal dictionary
     *
     * @return array<string>
     */
    public function getPersonalDictionaryWords(): array
    {
        return $this->loadPersonalDictionary();
    }

    /**
     * Create personal dictionary if it doesn't exist
     */
    public function initPersonalDictionary(): void
    {
        $dictPath = $this->getPersonalDictionaryPath();

        if (!file_exists($dictPath)) {
            $this->savePersonalDictionary([]);
        }
    }

    /**
     * Extract words from PHP code (identifiers, camelCase, snake_case)
     */
    private function extractPhpWords(string $content): string
    {
        // Remove comments
        $content = preg_replace('#/\*.*?\*/#s', '', $content);
        $content = preg_replace('#//.*$#m', '', $content);
        $content = preg_replace('#\#\[.*?\]#s', '', $content);

        // Extract identifiers (including multi-word tokens)
        preg_match_all('/[A-Za-z][A-Za-z0-9_]{2,}/', $content, $matches);

        $words = [];
        foreach ($matches[0] as $token) {
            // Split snake_case
            $parts = explode('_', $token);
            foreach ($parts as $part) {
                // Split camelCase/PascalCase
                $subParts = preg_split('/(?=[A-Z])/', $part, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($subParts as $word) {
                    if (strlen($word) > 2) {
                        $words[] = strtolower($word);
                    }
                }
            }
        }

        return implode("\n", array_unique($words));
    }

    /**
     * Run aspell on content
     *
     * @return array<string>
     */
    private function runAspell(string $content): array
    {
        $personalDict = $this->getPersonalDictionaryPath();
        $personalDictOption = file_exists($personalDict) ? "--personal={$personalDict}" : '';

        // Create temporary file for content
        $tmpFile = tempnam(sys_get_temp_dir(), 'aspell_');
        file_put_contents($tmpFile, $content);

        try {
            $cmd = sprintf(
                'aspell --encoding=utf-8 --lang=%s --ignore-case %s list < %s',
                escapeshellarg($this->lang),
                $personalDictOption,
                escapeshellarg($tmpFile)
            );

            $result = run($cmd);

            if ($result->isSuccessful()) {
                $output = trim($result->getOutput());
                if (empty($output)) {
                    return [];
                }

                return array_unique(array_filter(explode("\n", $output)));
            }

            return [];
        } finally {
            unlink($tmpFile);
        }
    }

    /**
     * Create finder with common exclusions
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
     * Check files with finder
     *
     * @return array<string, array<string>>
     */
    private function checkFiles(Finder $finder, bool $isPhpCode): array
    {
        $errors = [];

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $content = $file->getContents();

            // Extract words from PHP code if needed
            if ($isPhpCode) {
                $content = $this->extractPhpWords($content);
            }

            $misspelled = $this->runAspell($content);

            if (!empty($misspelled)) {
                $relativePath = str_replace($this->projectRoot . '/', '', $file->getRealPath());
                $errors[$relativePath] = $misspelled;
            }
        }

        return $errors;
    }

    private function getPersonalDictionaryPath(): string
    {
        return $this->projectRoot . '/' . self::PERSONAL_DICT_PATH;
    }

    /**
     * @return array<string>
     */
    private function loadPersonalDictionary(): array
    {
        $dictPath = $this->getPersonalDictionaryPath();

        if (!file_exists($dictPath)) {
            return [];
        }

        $content = file_get_contents($dictPath);
        $lines = explode("\n", $content);

        $words = [];
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip header line and empty lines
            if ($line === '' || str_starts_with($line, 'personal_ws-')) {
                continue;
            }
            $words[] = $line;
        }

        return $words;
    }

    /**
     * @param array<string> $words
     */
    private function savePersonalDictionary(array $words): bool
    {
        $dictPath = $this->getPersonalDictionaryPath();

        // Aspell personal dictionary format
        $content = sprintf("personal_ws-1.1 %s %d\n", $this->lang, count($words));
        $content .= implode("\n", $words) . "\n";

        return file_put_contents($dictPath, $content) !== false;
    }
}
