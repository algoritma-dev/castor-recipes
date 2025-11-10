<?php

require_once __DIR__ . '/_common.php';

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(name: 'install', namespace: 'composer', description: 'Install Composer dependencies')]
function composer_install(string $composerArgs = ''): void
{
    $composerCmd = (string) env_value('COMPOSER_BIN', 'composer');
    run(dockerize(sprintf('%s %s %s', $composerCmd, 'install', $composerArgs)));
}

#[AsTask(name: 'require', namespace: 'composer', description: 'Require Composer dependencies')]
function composer_requires(bool $dev = false, string $composerArgs = ''): void
{
    $composerCmd = (string) env_value('COMPOSER_BIN', 'composer');
    run(dockerize(sprintf('%s %s %s %s', $composerCmd, 'require', $dev, $composerArgs)));
}

#[AsTask(name: 'update', namespace: 'composer', description: 'Require Composer dependencies')]
function composer_update(string $composerArgs = ''): void
{
    $composerCmd = (string) env_value('COMPOSER_BIN', 'composer');
    run(dockerize(sprintf('%s %s %s', $composerCmd, 'update', $composerArgs)));
}
