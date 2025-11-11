<?php

require_once __DIR__ . '/_common.php';

use Castor\Attribute\AsTask;

use function Castor\run;

function composer_bin(): string
{
    return (string) env_value('COMPOSER_BIN', 'composer');
}

#[AsTask(name: 'install', namespace: 'composer', description: 'Install Composer dependencies')]
function composer_install(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', composer_bin(), 'install', $args)));
}

#[AsTask(name: 'require', namespace: 'composer', description: 'Require Composer dependencies')]
function composer_requires(bool $dev = false, string $args = ''): void
{
    $devFlag = $dev ? '--dev' : '';
    run(dockerize(sprintf('%s %s %s %s', composer_bin(), 'require', $devFlag, $args)));
}

#[AsTask(name: 'update', namespace: 'composer', description: 'Require Composer dependencies')]
function composer_update(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', composer_bin(), 'update', $args)));
}

#[AsTask(name: 'run', namespace: 'composer', description: 'Run script(s)')]
function composer_run(string $scriptNames): void
{
    run(dockerize(sprintf('%s %s %s', composer_bin(), 'run', $scriptNames)));
}
