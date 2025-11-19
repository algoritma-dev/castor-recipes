<?php

declare(strict_types=1);

use Algoritma\CastorRecipes\Aspell\SpellChecker;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\exit_code;
use function Castor\io;
use function Castor\run;

if (is_file(__DIR__ . '/../../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../../vendor/autoload.php';
} elseif (is_file(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
} else {
    require_once __DIR__ . '/../vendor/autoload.php';
}

#[AsTask(name: 'check', namespace: 'aspell', description: 'Find spelling mistakes in text files (md, txt, yaml, json)')]
function aspell_check_text(string $lang = 'en', bool $ignoreAll = false): void
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    if (! $checker->isAspellInstalled()) {
        io()->error('aspell is not installed. Install it with: sudo apt-get install aspell');

        return;
    }

    io()->title('Checking text files for spelling errors');

    $errors = $checker->checkTextFiles();

    displayResults($errors, $checker, $ignoreAll);
}

#[AsTask(name: 'check-code', namespace: 'aspell', description: 'Find spelling mistakes in PHP code identifiers')]
function aspell_check_code(string $lang = 'en', bool $ignoreAll = false): void
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    if (! $checker->isAspellInstalled()) {
        io()->error('aspell is not installed. Install it with: sudo apt-get install aspell');

        return;
    }

    io()->title('Checking PHP code for spelling errors');

    $errors = $checker->checkPhpCode();

    displayResults($errors, $checker, $ignoreAll);
}

#[AsTask(name: 'check-all', namespace: 'aspell', description: 'Find spelling mistakes in all files (text + code)')]
function aspell_check_all(string $lang = 'en', string $files = '', bool $ignoreAll = false): void
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    if (! $checker->isAspellInstalled()) {
        io()->error('aspell is not installed. Install it with: sudo apt-get install aspell');

        return;
    }

    $specificFiles = $files !== '' ? array_filter(explode(' ', $files)) : [];

    if ($specificFiles !== []) {
        io()->title(\sprintf('Checking %d file(s) for spelling errors', \count($specificFiles)));
    } else {
        io()->title('Checking all files for spelling errors');
    }

    $errors = $checker->checkAll($specificFiles);

    displayResults($errors, $checker, $ignoreAll);
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

    if ($words === []) {
        io()->note('Personal dictionary is empty. Use "aspell:add-word" to add words.');

        return;
    }

    io()->title(\sprintf('Personal Dictionary (%d words)', \count($words)));
    io()->listing($words);
}

#[AsTask(name: 'init', namespace: 'aspell', description: 'Initialize personal dictionary')]
function aspell_init(string $lang = 'en'): void
{
    $checker = new SpellChecker(PathHelper::getRoot(), $lang);

    if (! $checker->isAspellInstalled()) {
        io()->error('aspell is not installed. Install it with: sudo apt-get install aspell');

        return;
    }

    $checker->initPersonalDictionary();

    io()->success('Personal dictionary initialized at .aspell.en.pws');
    io()->note('Add project-specific words with: castor aspell:add-word <word>');
}

/**
 * Display spell check results.
 *
 * @param array<string, list<array{word: string, context: list<string>}>> $errors
 */
function displayResults(array $errors, SpellChecker $checker, bool $ignoreAll = false): void
{
    if ($errors === []) {
        io()->success('No spelling errors found!');

        return;
    }

    if ($ignoreAll) {
        $checker->initPersonalDictionary();
        $addedWords = [];

        foreach ($errors as $errorsList) {
            foreach ($errorsList as $error) {
                $word = $error['word'];
                if ($checker->addToPersonalDictionary($word)) {
                    $addedWords[] = $word;
                }
            }
        }

        if ($addedWords !== []) {
            io()->success(\sprintf('Added %d word(s) to personal dictionary', \count($addedWords)));
            io()->listing($addedWords);
        } else {
            io()->note('All words already exist in personal dictionary');
        }

        return;
    }

    $totalErrors = 0;
    foreach ($errors as $file => $errorsList) {
        io()->section($file);

        $formattedErrors = [];
        foreach ($errorsList as $error) {
            $word = $error['word'];
            $contexts = $error['context'];
            $contextStr = empty($contexts) ? '' : ' (' . implode(', ', $contexts) . ')';
            $formattedErrors[] = $word . $contextStr;
        }

        io()->listing($formattedErrors);
        $totalErrors += \count($errorsList);
    }

    io()->writeln('');
    io()->error(\sprintf('Found %d potential spelling error(s) in %d file(s)', $totalErrors, \count($errors)));
    io()->note([
        'To add a word to your personal dictionary: castor aspell:add-word <word>',
        'To add all words automatically: add --ignore-all option',
        'To fix errors interactively: aspell check <filename>',
    ]);

    exit_code(run('exit 1'));
}
