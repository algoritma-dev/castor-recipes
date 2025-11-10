<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\load_dot_env;
use function Castor\run;

require_once __DIR__ . '/_composer.php';
require_once __DIR__ . '/_xdebug.php';

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

    return (array_key_exists($key, $_SERVER) ? $_SERVER[$key] : getenv($key)) ?: $default;
}

function set_env(string $key, mixed $value): void
{
    if (array_key_exists($key, $_SERVER)) {
        $_SERVER['orig_'.$key] = $value;
    }

    if (array_key_exists($key, $_ENV)) {
        $_ENV['orig_'.$key] = $value;
    }

    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv(sprintf('%s=%s', $key, $value));
}

function restore_env(string $key): void
{
    if (array_key_exists('orig_'.$key, $_SERVER)) {
        $_SERVER[$key] = $_SERVER['orig_'.$key];
        unset($_SERVER['orig_'.$key]);
    }

    if (array_key_exists('orig_'.$key, $_ENV)) {
        $_ENV[$key] = $_ENV['orig_'.$key];
        unset($_ENV['orig_'.$key]);
    }

    putenv(sprintf('%s=%s', $key, $_SERVER[$key] ?? $_ENV[$key] ?? null));
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

    $service = env_value('DOCKER_SERVICE', 'workspace');
    $compose = env_value('DOCKER_COMPOSE_FILE', 'docker-compose.yml');

    $workdirArg = $workdir ? sprintf('--workdir %s', escapeshellarg($workdir)) : '';

    $isRunning = \Castor\capture(sprintf('docker compose -f %s ps -q %s', escapeshellarg((string) $compose), escapeshellarg((string) $service)));

    $cmd = $isRunning !== '' && $isRunning !== '0' ? 'exec' : 'run --rm';

    return sprintf(
        'docker compose -f %s %s %s %s %s',
        $compose,
        $cmd,
        $service,
        $workdirArg,
        $command
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
