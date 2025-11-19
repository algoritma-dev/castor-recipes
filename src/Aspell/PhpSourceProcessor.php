<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Aspell;

use PhpSpellcheck\TextInterface;
use PhpSpellcheck\TextProcessor\TextProcessorInterface;

/**
 * Custom text processor for PHP source code that extracts only:
 * - Variable names
 * - Class names
 * - Method names
 * - Parameter names
 * - Comments (both inline and block)
 *
 * Uses PHP tokenizer for accurate parsing.
 */
final class PhpSourceProcessor implements TextProcessorInterface
{
    public function process(TextInterface $text): TextInterface
    {
        $content = $text->getContent();
        $words = $this->extractWordsFromPhpCode($content);

        // Return text with only the extracted words
        $processedContent = implode(' ', $words);

        return $text->replaceContent($processedContent);
    }

    /**
     * Extract words from PHP code using tokenizer.
     *
     * @return list<string>
     */
    private function extractWordsFromPhpCode(string $code): array
    {
        $tokens = @token_get_all($code);

        $words = [];
        $inAttribute = false;

        foreach ($tokens as $i => $iValue) {
            $token = $iValue;

            // Handle single-char tokens for attribute closing
            if (! \is_array($token)) {
                if ($token === '#') {
                    $inAttribute = true;
                } elseif ($token === ']' && $inAttribute) {
                    $inAttribute = false;
                }
                continue;
            }

            [$tokenId, $tokenValue] = $token;

            // Detect attribute opening with T_ATTRIBUTE token
            if ($tokenId === \T_ATTRIBUTE) {
                $inAttribute = true;
                continue;
            }

            // Extract string literals from attributes
            if ($inAttribute && $tokenId === \T_CONSTANT_ENCAPSED_STRING) {
                $stringValue = trim($tokenValue, '\'"');
                $this->addWordsFromText($words, $stringValue);
                continue;
            }

            switch ($tokenId) {
                // Extract variable names
                case \T_VARIABLE:
                    $varName = ltrim($tokenValue, '$');
                    if (\strlen($varName) > 2) {
                        $this->addSplitWords($words, $varName);
                    }
                    break;

                    // Extract class, interface, trait, enum names
                case \T_CLASS:
                case \T_INTERFACE:
                case \T_TRAIT:
                case \T_ENUM:
                    $name = $this->getNextIdentifier($tokens, $i);
                    if ($name && \strlen($name) > 2) {
                        $this->addSplitWords($words, $name);
                    }
                    break;

                    // Extract function/method names
                case \T_FUNCTION:
                    $name = $this->getNextIdentifier($tokens, $i);
                    // Skip magic methods
                    if ($name && \strlen($name) > 2 && ! str_starts_with($name, '__')) {
                        $this->addSplitWords($words, $name);
                    }
                    break;

                    // Extract comments
                case \T_COMMENT:
                case \T_DOC_COMMENT:
                    $commentWords = $this->extractWordsFromComment($tokenValue);
                    $words = array_merge($words, $commentWords);
                    break;
            }
        }

        return array_unique($words);
    }

    /**
     * Get the next identifier token after current position.
     *
     * @param list<mixed> $tokens
     */
    private function getNextIdentifier(array $tokens, int &$currentIndex): ?string
    {
        $tokensCount = \count($tokens);

        for ($i = $currentIndex + 1; $i < $tokensCount; ++$i) {
            $token = $tokens[$i];

            [$tokenId, $tokenValue] = $token;

            // Found identifier
            if ($tokenId === \T_STRING) {
                return $tokenValue;
            }

            // Skip whitespace
            if ($tokenId === \T_WHITESPACE) {
                continue;
            }

            // Stop if we hit something else
            break;
        }

        return null;
    }

    /**
     * Extract words from comments, removing PHPDoc tags and special characters.
     *
     * @return list<string>
     */
    private function extractWordsFromComment(string $comment): array
    {
        // Remove comment markers
        $comment = preg_replace('#^/\*\*?|\*/$|^//|^\*#m', '', $comment);

        // Remove PHPDoc tags (@param, @return, etc.)
        $comment = preg_replace('/@[a-zA-Z]+/', '', (string) $comment);

        // Remove type hints in comments (e.g., array<string>, int|null)
        $comment = preg_replace('/\b[a-zA-Z_]+(<[^>]+>)?(\|[a-zA-Z_]+)*\b(?=\s*\$)/', '', (string) $comment);

        // Remove variable names ($variable)
        $comment = preg_replace('/\$[a-zA-Z_]\w*/', '', (string) $comment);

        // Extract words
        preg_match_all('/\b[a-zA-Z]+\b/', (string) $comment, $matches);

        $words = [];
        $phpReservedWords = [
            'int', 'string', 'bool', 'float', 'array', 'object', 'callable', 'iterable', 'void', 'mixed',
            'self', 'parent', 'static', 'true', 'false', 'null', 'resource', 'never',
        ];

        foreach ($matches[0] as $token) {
            // Check if it's a camelCase/PascalCase identifier (contains uppercase after lowercase)
            if (preg_match('/[a-z][A-Z]/', $token)) {
                // Split it like we do for identifiers
                $splitWords = $this->splitIdentifier($token);
                foreach ($splitWords as $word) {
                    if (! \in_array($word, $phpReservedWords, true) && \strlen($word) > 2) {
                        $words[] = $word;
                    }
                }
            } else {
                $word = strtolower($token);
                // Skip short words, reserved words
                if (\strlen($word) <= 2) {
                    continue;
                }
                if (\in_array($word, $phpReservedWords, true)) {
                    continue;
                }

                $words[] = $word;
            }
        }

        return $words;
    }

    /**
     * Split an identifier into individual words.
     *
     * @return list<string>
     */
    private function splitIdentifier(string $identifier): array
    {
        // First split camelCase/PascalCase by inserting space before uppercase letters
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $identifier);

        // Then replace underscores with spaces (snake_case)
        $spaced = str_replace('_', ' ', $spaced);

        // Split by spaces and filter
        $parts = explode(' ', $spaced);

        $words = [];
        foreach ($parts as $word) {
            $word = strtolower(trim($word));
            // Skip short words, numbers, and empty strings
            if ($word === '') {
                continue;
            }
            if (\strlen($word) <= 2) {
                continue;
            }
            if (is_numeric($word)) {
                continue;
            }

            $words[] = $word;
        }

        return $words;
    }

    /**
     * Split identifier into words (camelCase, PascalCase, snake_case)
     * and add them to the words array.
     *
     * @param list<string> $words
     */
    private function addSplitWords(array &$words, string $identifier): void
    {
        $splitWords = $this->splitIdentifier($identifier);
        foreach ($splitWords as $word) {
            $words[] = $word;
        }
    }

    /**
     * Extract words from natural text (used for attribute strings).
     *
     * @param list<string> $words
     */
    private function addWordsFromText(array &$words, string $text): void
    {
        // Split by spaces, hyphens, underscores
        $textWords = preg_split('/[\s\-_]+/', $text);
        foreach ($textWords as $word) {
            $word = strtolower(trim($word));
            if (\strlen($word) > 2) {
                $words[] = $word;
            }
        }
    }
}
