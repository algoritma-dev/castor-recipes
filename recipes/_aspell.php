<?php

declare(strict_types=1);

use Algoritma\CastorRecipes\Aspell\SpellChecker;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;
use function Castor\capture;
use function Castor\io;

require __DIR__ . '/../vendor/autoload.php';

#[AsTask(name: 'check', namespace: 'aspell', description: 'Find spelling mistakes in text files (md, txt, yaml, json)')]
function aspell_check_text(string $lang = 'en'): int
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    if (!$checker->isAspellInstalled()) {
        io()->error('aspell is not installed. Install it with: sudo apt-get install aspell');
        return 1;
    }

    io()->title('Checking text files for spelling errors');

    $errors = $checker->checkTextFiles();

    return displayResults($errors);
}

#[AsTask(name: 'check-code', namespace: 'aspell', description: 'Find spelling mistakes in PHP code identifiers')]
function aspell_check_code(string $lang = 'en'): int
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    if (!$checker->isAspellInstalled()) {
        io()->error('aspell is not installed. Install it with: sudo apt-get install aspell');
        return 1;
    }

    io()->title('Checking PHP code for spelling errors');

    $errors = $checker->checkPhpCode();

    return displayResults($errors);
}

#[AsTask(name: 'check-all', namespace: 'aspell', description: 'Find spelling mistakes in all files (text + code)')]
function aspell_check_all(string $lang = 'en'): int
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    if (!$checker->isAspellInstalled()) {
        io()->error('aspell is not installed. Install it with: sudo apt-get install aspell');
        return 1;
    }

    io()->title('Checking all files for spelling errors');

    $errors = $checker->checkAll();

    return displayResults($errors);
}

#[AsTask(name: 'add-word', namespace: 'aspell', description: 'Add a word to the personal dictionary')]
function aspell_add_word(string $word, string $lang = 'en'): void
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    $checker->initPersonalDictionary();

    if ($checker->addToPersonalDictionary($word)) {
        io()->success("Added '{$word}' to personal dictionary (.aspell.en.pws)");
    } else {
        io()->note("Word '{$word}' already exists in personal dictionary");
    }
}

#[AsTask(name: 'show-dict', namespace: 'aspell', description: 'Show words in personal dictionary')]
function aspell_show_dictionary(string $lang = 'en'): void
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    $words = $checker->getPersonalDictionaryWords();

    if (empty($words)) {
        io()->note('Personal dictionary is empty. Use "aspell:add-word" to add words.');
        return;
    }

    io()->title(sprintf('Personal Dictionary (%d words)', count($words)));
    io()->listing($words);
}

#[AsTask(name: 'init', namespace: 'aspell', description: 'Initialize personal dictionary')]
function aspell_init(string $lang = 'en'): void
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    if (!$checker->isAspellInstalled()) {
        io()->error('aspell is not installed. Install it with: sudo apt-get install aspell');
        return;
    }

    $checker->initPersonalDictionary();

    io()->success('Personal dictionary initialized at .aspell.en.pws');
    io()->note('Add project-specific words with: castor aspell:add-word <word>');
}

/**
 * Display spell check results
 *
 * @param array<string, array<array{word: string, context: array<string>}>> $errors
 */
function displayResults(array $errors): int
{
    if (empty($errors)) {
        io()->success('No spelling errors found!');
        return 0;
    }

    $totalErrors = 0;
    foreach ($errors as $file => $errorsList) {
        io()->section($file);

        $formattedErrors = [];
        foreach ($errorsList as $error) {
            $word = $error['word'];
            $contexts = $error['context'];
            $contextStr = !empty($contexts) ? ' (' . implode(', ', $contexts) . ')' : '';
            $formattedErrors[] = $word . $contextStr;
        }

        io()->listing($formattedErrors);
        $totalErrors += count($errorsList);
    }

    io()->writeln('');
    io()->warning(sprintf('Found %d potential spelling error(s) in %d file(s)', $totalErrors, count($errors)));
    io()->note([
        'To add a word to your personal dictionary: castor aspell:add-word <word>',
        'To fix errors interactively: aspell check <filename>',
    ]);

    return 1;
}
