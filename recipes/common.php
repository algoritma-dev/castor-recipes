<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\load_dot_env;
use function Castor\run;

/**
 * Helper to get environment variables from .env via Castor and $_SERVER only.
 */
function env_value(string $key, mixed $default = null, ?string $path = null): mixed
{
    static $dotenvLoaded = false;
    if ($dotenvLoaded === false) {
        // Load .env only once to avoid repeated parsing and memory usage
        load_dot_env($path);
        $dotenvLoaded = true;
    }

    return array_key_exists($key, $_SERVER) ? $_SERVER[$key] : $default;
}

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
    $useDocker = env_value('CASTOR_DOCKER') === '1' || env_value('CASTOR_DOCKER') === 'true';

    if (! $useDocker) {
        return $command;
    }

    $service = env_value('DOCKER_SERVICE', 'php');
    $compose = env_value('DOCKER_COMPOSE_FILE', 'docker-compose.yml');

    $workdirArg = $workdir ? sprintf('--workdir %s', escapeshellarg($workdir)) : '';

    $process = run(sprintf('docker compose -f %s ps -q %s', escapeshellarg((string) $compose), escapeshellarg((string) $service)));

    $cmd = $process->getOutput() !== '' && $process->getOutput() !== '0' ? 'exec' : 'run --rm';

    return sprintf(
        'docker compose -f %s %s %s %s sh -lc %s',
        escapeshellarg((string) $compose),
        escapeshellarg($cmd),
        escapeshellarg((string) $service),
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
    return (string) env_value('PHP_BIN', 'php');
}

function phpunit_bin(): string
{
    return (string) env_value('PHPUNIT_BIN', is_file('vendor/bin/phpunit') ? 'vendor/bin/phpunit' : 'bin/phpunit');
}

#[AsTask(description: 'Install Composer dependencies')]
function composer_install(string $composerArgs = ''): void
{
    $composerCmd = (string) env_value('COMPOSER_BIN', 'composer');
    run(dockerize(sprintf('%s %s %s', $composerCmd, 'install', $composerArgs)));
}

#[AsTask(description: 'Require Composer dependencies')]
function composer_require(bool $dev = false, string $composerArgs = ''): void
{
    $composerCmd = (string) env_value('COMPOSER_BIN', 'composer');
    run(dockerize(sprintf('%s %s %s %s', $composerCmd, 'require', $dev, $composerArgs)));
}

#[AsTask(description: 'Require Composer dependencies')]
function composer_update(string $composerArgs = ''): void
{
    $composerCmd = (string) env_value('COMPOSER_BIN', 'composer');
    run(dockerize(sprintf('%s %s %s', $composerCmd, 'update', $composerArgs)));
}
