<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\exit_code;
use function Castor\run;

function getAspellBinaryPath(): string
{
    $paths = [
        './bin/alg-aspell',
        '../bin/alg-aspell',
        '../../../bin/alg-aspell',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '../bin/alg-aspell';
}

#[AsTask(name: 'check', namespace: 'aspell', description: 'Find spelling mistakes in text files (md, txt, yaml, json)')]
function aspell_check_text(string $lang = 'en', bool $ignoreAll = false): void
{
    $binary = getAspellBinaryPath();
    $ignoreAllFlag = $ignoreAll ? ' --ignore-all' : '';

    exit_code(dockerize("php {$binary} check-text --lang={$lang}{$ignoreAllFlag}"));
}

#[AsTask(name: 'check-code', namespace: 'aspell', description: 'Find spelling mistakes in PHP code identifiers')]
function aspell_check_code(string $lang = 'en', bool $ignoreAll = false): void
{
    $binary = getAspellBinaryPath();
    $ignoreAllFlag = $ignoreAll ? ' --ignore-all' : '';

    exit_code(dockerize("php {$binary} check-code --lang={$lang}{$ignoreAllFlag}"));
}

#[AsTask(name: 'check-all', namespace: 'aspell', description: 'Find spelling mistakes in all files (text + code)')]
function aspell_check_all(string $lang = 'en', string $files = '', bool $ignoreAll = false): void
{
    $binary = getAspellBinaryPath();
    $ignoreAllFlag = $ignoreAll ? ' --ignore-all' : '';
    $filesArg = $files !== '' ? ' --files=' . escapeshellarg($files) : '';

    exit_code(dockerize("php {$binary} check-all --lang={$lang}{$ignoreAllFlag}{$filesArg}"));
}

#[AsTask(name: 'add-word', namespace: 'aspell', description: 'Add a word to the personal dictionary')]
function aspell_add_word(string $word, string $lang = 'en'): void
{
    $binary = getAspellBinaryPath();

    run(dockerize("php {$binary} add-word {$word} --lang={$lang}"));
}

#[AsTask(name: 'show-dict', namespace: 'aspell', description: 'Show words in personal dictionary')]
function aspell_show_dictionary(string $lang = 'en'): void
{
    $binary = getAspellBinaryPath();

    run(dockerize("php {$binary} show-dict --lang={$lang}"));
}

#[AsTask(name: 'init', namespace: 'aspell', description: 'Initialize personal dictionary')]
function aspell_init(string $lang = 'en'): void
{
    $binary = getAspellBinaryPath();

    run(dockerize("php {$binary} init --lang={$lang}"));
}
