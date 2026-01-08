<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\exit_code;

function getAspellBinaryPath(): string
{
    $paths = [
        __DIR__ . '/bin/alg-aspell',
        __DIR__ . '/../bin/alg-aspell',
        __DIR__ . '/../../../bin/alg-aspell',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return __DIR__ . '/../bin/alg-aspell';
}

#[AsTask(name: 'check', namespace: 'aspell', description: 'Find spelling mistakes in text files (md, txt, yaml, json)', aliases: ['spell'])]
function aspell_check_text(string $lang = 'en', bool $ignoreAll = false): int
{
    $binary = getAspellBinaryPath();
    $ignoreAllFlag = $ignoreAll ? ' --ignore-all' : '';

    return exit_code(dockerize("php {$binary} check-text --lang={$lang}{$ignoreAllFlag}"));
}

#[AsTask(name: 'check-code', namespace: 'aspell', description: 'Find spelling mistakes in PHP code identifiers', aliases: ['sc'])]
function aspell_check_code(string $lang = 'en', bool $ignoreAll = false): int
{
    $binary = getAspellBinaryPath();
    $ignoreAllFlag = $ignoreAll ? ' --ignore-all' : '';

    return exit_code(dockerize("php {$binary} check-code --lang={$lang}{$ignoreAllFlag}"));
}

#[AsTask(name: 'check-all', namespace: 'aspell', description: 'Find spelling mistakes in all files (text + code)', aliases: ['spell-all'])]
function aspell_check_all(string $lang = 'en', string $files = '', bool $ignoreAll = false): int
{
    $binary = getAspellBinaryPath();
    $ignoreAllFlag = $ignoreAll ? ' --ignore-all' : '';
    $filesArg = $files !== '' ? ' --files=' . escapeshellarg($files) : '';

    return exit_code(dockerize("php {$binary} check-all --lang={$lang}{$ignoreAllFlag}{$filesArg}"));
}

#[AsTask(name: 'add-word', namespace: 'aspell', description: 'Add a word to the personal dictionary', aliases: ['aw'])]
function aspell_add_word(string $word, string $lang = 'en'): int
{
    $binary = getAspellBinaryPath();

    return exit_code(dockerize("php {$binary} add-word {$word} --lang={$lang}"));
}

#[AsTask(name: 'show-dict', namespace: 'aspell', description: 'Show words in personal dictionary')]
function aspell_show_dictionary(string $lang = 'en'): int
{
    $binary = getAspellBinaryPath();

    return exit_code(dockerize("php {$binary} show-dict --lang={$lang}"));
}

#[AsTask(name: 'init', namespace: 'aspell', description: 'Initialize personal dictionary')]
function aspell_init(string $lang = 'en'): int
{
    $binary = getAspellBinaryPath();

    return exit_code(dockerize("php {$binary} init --lang={$lang}"));
}
