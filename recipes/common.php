<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

/**
 * Helper: decide if we should run in Docker or locally.
 *
 * Env vars:
 *  - CASTOR_DOCKER=1 enables docker
 *  - DOCKER_SERVICE (default: php)
 *  - DOCKER_COMPOSE_FILE (default: docker-compose.yml)
 */
function dockerize(string $command, ?string $workdir = null): string
{
    $useDocker = getenv('CASTOR_DOCKER') === '1' || getenv('CASTOR_DOCKER') === 'true';

    if (!$useDocker) {
        return $command;
    }

    $service = getenv('DOCKER_SERVICE') ?: 'php';
    $compose = getenv('DOCKER_COMPOSE_FILE') ?: 'docker-compose.yml';

    $workdirArg = $workdir ? sprintf('--workdir %s', escapeshellarg($workdir)) : '';

    $process = run(sprintf('docker compose -f %s ps -q %s', escapeshellarg($compose), escapeshellarg($service)));

    $cmd = $process->getOutput() !== '' && $process->getOutput() !== '0' ? 'exec' : 'run --rm';

    return sprintf(
        'docker compose -f %s %s %s %s sh -lc %s',
        escapeshellarg($compose),
        escapeshellarg($cmd),
        escapeshellarg($service),
        $workdirArg,
        escapeshellarg($command)
    );
}

#[AsTask(description: 'Run an arbitrary command, locally or in Docker', aliases: ['x'])]
function sh(string $cmd = 'php -v', string $cwd = '.'): void
{
    run(dockerize($cmd, $cwd));
}

function php(): string
{
    return getenv('PHP_BIN') ?: 'php';
}

function phpunit_bin(): string
{
    return getenv('PHPUNIT_BIN') ?: (is_file('vendor/bin/phpunit') ? 'vendor/bin/phpunit' : 'bin/phpunit');
}

#[AsTask(description: 'Install Composer dependencies')]
function composer_install(string $composerArgs = ''): void
{
    $composerCmd = getenv('COMPOSER_BIN') ?: 'composer';
    run(dockerize(sprintf('%s %s %s', $composerCmd, 'install', $composerArgs)));
}

#[AsTask(description: 'Require Composer dependencies')]
function composer_require(bool $dev = false, string $composerArgs = ''): void
{
    $composerCmd = getenv('COMPOSER_BIN') ?: 'composer';
    run(dockerize(sprintf('%s %s %s %s', $composerCmd, 'require', $dev, $composerArgs)));
}

#[AsTask(description: 'Require Composer dependencies')]
function composer_update(string $composerArgs = ''): void
{
    $composerCmd = getenv('COMPOSER_BIN') ?: 'composer';
    run(dockerize(sprintf('%s %s %s', $composerCmd, 'update', $composerArgs)));
}

