<?php

declare(strict_types=1);

use Castor\Attribute\AsRawTokens;

require_once __DIR__ . '/_common.php';

use Castor\Attribute\AsTask;

use function Castor\run;

function composer_bin(): string
{
    return (string) env_value('COMPOSER_BIN', 'composer');
}

#[AsTask(name: 'install', namespace: 'composer', description: 'Install Composer dependencies', aliases: ['ci'])]
function composer_install(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s %s', composer_bin(), 'install', implode(' ', $args))));
}

#[AsTask(name: 'require', namespace: 'composer', description: 'Require Composer dependencies', aliases: ['req'])]
function composer_requires(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s %s', composer_bin(), 'require', implode(' ', $args))));
}

#[AsTask(name: 'update', namespace: 'composer', description: 'Require Composer dependencies', aliases: ['cu'])]
function composer_update(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s %s', composer_bin(), 'update', implode(' ', $args))));
}

#[AsTask(name: 'run', namespace: 'composer', description: 'Run script(s)', aliases: ['cr'])]
function composer_run(string $scriptNames): void
{
    run(dockerize(\sprintf('%s %s %s', composer_bin(), 'run', $scriptNames)));
}
