<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\capture;
use function Castor\load_dot_env;
use function Castor\run;

require_once __DIR__ . '/_composer.php';
require_once __DIR__ . '/_xdebug.php';
require_once __DIR__ . '/_aspell.php';
require_once __DIR__ . '/_quality-check.php';

/**
 * Get the dotenv base filename from composer.json extra.runtime.dotenv_path.
 * Defaults to '.env' if not found.
 *
 * @param string|null $directory The directory to search for composer.json. If null, uses PathHelper::getRoot()
 */
function get_dotenv_base_path(?string $directory = null): string
{
    static $cache = [];

    $root = $directory ?? PathHelper::getRoot(throw: false);
    $composerPath = $root . '/composer.json';

    // Check if already cached for this path
    if (isset($cache[$composerPath])) {
        return $cache[$composerPath];
    }

    if (! is_file($composerPath)) {
        $cache[$composerPath] = '.env';

        return $cache[$composerPath];
    }

    $composerContent = file_get_contents($composerPath);
    if ($composerContent === false) {
        $cache[$composerPath] = '.env';

        return $cache[$composerPath];
    }

    $composer = json_decode($composerContent, true, 512, \JSON_THROW_ON_ERROR);

    $cache[$composerPath] = $composer['extra']['runtime']['dotenv_path'] ?? '.env';

    return $cache[$composerPath];
}

/**
 * Helper to get environment variables from .env via Castor and $_SERVER only.
 *
 * @param string $key The environment variable key to retrieve
 * @param mixed $default Default value if key not found
 * @param string|null $path Explicit path to .env file (overrides auto-detection)
 * @param string|null $environment Environment name (e.g., 'test', 'prod'). If null, uses 'local' as default
 */
function env_value(string $key, mixed $default = null, ?string $path = null, ?string $environment = null): mixed
{
    static $dotenvLoaded = false;

    if ($dotenvLoaded) {
        return $_SERVER[$key] ?? $default;
    }

    if ($path === null) {
        $baseEnvFile = get_dotenv_base_path();
        $cwd = PathHelper::getRoot();

        // Build the list of candidate files based on environment
        $pathsCandidate = [];

        if ($environment !== null && $environment !== '') {
            // Specific environment: try .env.{env} (or .env-app.{env}) with fallback to base
            $pathsCandidate[] = $baseEnvFile . '.' . $environment;
            $pathsCandidate[] = $baseEnvFile;
        } else {
            // No environment specified: try .env.local (or .env-app.local) with fallback to base
            $pathsCandidate[] = $baseEnvFile . '.local';
            $pathsCandidate[] = $baseEnvFile;
        }

        // Always add .env.example as final fallback
        $pathsCandidate[] = '.env.example';

        // Reverse the list to load the most specific first
        $pathsCandidate = array_reverse($pathsCandidate);

        // Load all existing env files (Castor's load_dot_env handles merging)
        foreach ($pathsCandidate as $pathCandidate) {
            $fullPath = $cwd . '/' . $pathCandidate;
            if (is_file($fullPath)) {
                load_dot_env($fullPath);
            }
        }
    } else {
        load_dot_env($path);
    }

    $dotenvLoaded = true;

    return (\array_key_exists($key, $_SERVER) ? $_SERVER[$key] : getenv($key)) ?: $default;
}

/**
 * Helper: decide if we should run in Docker or locally.
 *
 * Env vars:
 *  - CASTOR_DOCKER=1 enables docker
 *  - DOCKER_SERVICE (default: workspace)
 *  - DOCKER_COMPOSE_FILE (default: docker-compose.yml)
 */
function dockerize(string $command, ?string $service = null, ?string $workdir = null, bool $tty = false): string
{
    $useDocker = env_value('CASTOR_DOCKER') === '1' || env_value('CASTOR_DOCKER') === 'true';

    if (! $useDocker) {
        return $command;
    }

    $service ??= env_value('DOCKER_SERVICE', 'workspace');
    $compose = env_value('DOCKER_COMPOSE_FILE', 'docker-compose.yml');

    if ($workdir === null && env_value('DOCKER_PROJECT_ROOT') !== null) {
        $workdir = env_value('DOCKER_PROJECT_ROOT');
        $command = str_replace(PathHelper::getRoot(), $workdir, $command);
    }

    $workdirArg = $workdir ? \sprintf('--workdir %s', $workdir) : '';

    $isRunning = capture(\sprintf('docker compose -f %s ps -q %s', $compose, $service));

    $cmd = $isRunning !== '' && $isRunning !== '0' ? 'exec' : 'run --rm';

    return \sprintf(
        'docker compose -f %s %s %s %s %s %s',
        $compose,
        $cmd,
        $tty ? '-T' : '',
        $workdirArg,
        $service,
        $command
    );
}

#[AsTask(description: 'Run an arbitrary command, locally or in Docker', aliases: ['x'])]
function sh(string $cmd = 'php -v', ?string $cwd = null): void
{
    run(dockerize($cmd, $cwd));
}

function php(): string
{
    return (string) env_value('PHP_BIN', 'php');
}

function phpunit_bin(bool $watch = false): string
{
    if ($watch) {
        return (string) env_value('PHPUNIT_WATCH_BIN', is_file('vendor/bin/phpunit-watch') ? 'vendor/bin/phpunit-watch' : 'bin/phpunit-watch');
    }

    return (string) env_value('PHPUNIT_BIN', is_file('vendor/bin/phpunit') ? 'vendor/bin/phpunit' : 'bin/phpunit');
}

function get_psr4_paths(bool $includeDev = true): array
{
    $composerPath = PathHelper::getRoot() . '/composer.json';

    if (! file_exists($composerPath)) {
        throw new RuntimeException('composer.json not found.');
    }

    $composer = json_decode(file_get_contents($composerPath), true, 512, \JSON_THROW_ON_ERROR);

    $paths = [];

    // Extract "autoload"
    foreach ($composer['autoload']['psr-4'] ?? [] as $path) {
        // Cast to array because psr-4 mapping can be an array of paths
        $paths = array_merge($paths, (array) $path);
    }

    if ($includeDev) {
        // Extract "autoload-dev"
        foreach ($composer['autoload-dev']['psr-4'] ?? [] as $path) {
            $paths = array_merge($paths, (array) $path);
        }
    }

    return array_unique($paths);
}
