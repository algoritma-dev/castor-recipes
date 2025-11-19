<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Aspell;

use PhpSpellcheck\MisspellingHandler\MisspellingHandlerInterface;
use PhpSpellcheck\MisspellingInterface;

/**
 * Handler that collects misspellings into an array grouped by file
 */
final class MisspellingCollectorHandler implements MisspellingHandlerInterface
{
    /**
     * @var array<string, array<array{word: string, context: array<string>}>>
     */
    private array $errors = [];

    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @param iterable<MisspellingInterface> $misspellings
     */
    public function handle(iterable $misspellings): void
    {
        foreach ($misspellings as $misspelling) {
            /** @var MisspellingInterface $misspelling */
            $context = $misspelling->getContext();
            $filePath = $context['file'] ?? 'unknown';
            $relativePath = str_replace($this->projectRoot . '/', '', $filePath);

            if (!isset($this->errors[$relativePath])) {
                $this->errors[$relativePath] = [];
            }

            $this->errors[$relativePath][] = [
                'word' => $misspelling->getWord(),
                'context' => [],
            ];
        }
    }

    /**
     * Get collected erors asdsadsa
     *
     * @return array<string, array<array{word: string, context: array<string>}>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Reset collected errors
     */
    public function reset(): void
    {
        $this->errors = [];
    }
}
