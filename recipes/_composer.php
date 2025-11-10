<?php

require_once __DIR__ . '/_common.php';

use Castor\Attribute\AsTask;

use function Castor\run;

function composer_bin(): string
{
    return (string) env_value('COMPOSER_BIN', 'composer');
}

#[AsTask(name: 'install', namespace: 'composer', description: 'Install Composer dependencies')]
function composer_install(string $composerArgs = ''): void
{
    run(dockerize(sprintf('%s %s %s', composer_bin(), 'install', $composerArgs)));
}

#[AsTask(name: 'require', namespace: 'composer', description: 'Require Composer dependencies')]
function composer_requires(bool $dev = false, string $composerArgs = ''): void
{
    run(dockerize(sprintf('%s %s %s %s', composer_bin(), 'require', $dev, $composerArgs)));
}

#[AsTask(name: 'update', namespace: 'composer', description: 'Require Composer dependencies')]
function composer_update(string $composerArgs = ''): void
{
    run(dockerize(sprintf('%s %s %s', composer_bin(), 'update', $composerArgs)));
}

#[AsTask(name: 'run', namespace: 'composer', description: 'Run script(s)')]
function composer_run(string $scriptNames): void
{
    run(dockerize(sprintf('%s %s %s', composer_bin(), 'run', $scriptNames)));
}
