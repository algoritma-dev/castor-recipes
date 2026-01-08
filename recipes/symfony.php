<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

function console_bin(): string
{
    return env_value('SF_CONSOLE', 'bin/console');
}
function symfony_bin(): string
{
    return env_value('SYMFONY_BIN', 'symfony');
}

#[AsTask(description: 'Start Symfony local server (uses SYMFONY_BIN, SF_SERVER_FLAGS)', namespace: 'sf', aliases: ['sfs'])]
function serve(string $flags = '-d'): void
{
    run(sprintf('%s server:start %s', symfony_bin(), $flags));
}

#[AsTask(description: 'Stop Symfony local server', namespace: 'sf')]
function serve_stop(): void
{
    run(sprintf('%s server:stop', symfony_bin()));
}

#[AsTask(description: 'Run Doctrine migrations', namespace: 'sf', aliases: ['sfm'])]
function migrate(string $args = '--no-interaction'): void
{
    run(dockerize(sprintf('%s %s doctrine:migrations:migrate %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Generate Doctrine migration from changes', namespace: 'sf')]
function migrate_diff(string $args = '--no-interaction'): void
{
    run(dockerize(sprintf('%s %s doctrine:migrations:diff %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Clear Symfony cache', namespace: 'sf', aliases: ['sfcc'])]
function cache_clear(string $args = '--no-debug'): void
{
    run(dockerize(sprintf('%s %s cache:clear %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Warm up Symfony cache', namespace: 'sf')]
function cache_warmup(string $args = '--no-debug'): void
{
    run(dockerize(sprintf('%s %s cache:warmup %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Clear then warm up cache (composite)', namespace: 'sf')]
function cache_clear_warmup(): void
{
    cache_clear();
    cache_warmup();
}

#[AsTask(description: 'Run tests (PHPUnit)', namespace: 'sf', aliases: ['sft'])]
function test(string $args = ''): void
{
    run(dockerize(sprintf('%s %s', phpunit_bin(), $args)));
}

#[AsTask(name: 'db-create', description: 'Create database', namespace: 'sf')]
function sf_db_create(string $args = ''): void
{
    run(dockerize(sprintf('%s %s doctrine:database:create --if-not-exists %s', php(), console_bin(), $args)));
}

#[AsTask(name: 'db-drop', description: 'Drop database', namespace: 'sf')]
function sf_db_drop(string $args = ''): void
{
    run(dockerize(sprintf('%s %s doctrine:database:drop --force --if-exists %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Reset database (drop, create, migrate, fixtures) - composite', namespace: 'sf')]
function db_reset(bool $fixtures = true): void
{
    sf_db_drop();
    sf_db_create();
    migrate();
    if ($fixtures) {
        fixtures_load();
    }
}

#[AsTask(description: 'Run migrations from scratch (drop schema, migrate)', namespace: 'sf')]
function migrate_fresh(string $args = ''): void
{
    run(dockerize(sprintf('%s %s doctrine:schema:drop --force --full-database', php(), console_bin())));
    run(dockerize(sprintf('%s %s doctrine:migrations:migrate %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Load Doctrine fixtures (if installed)', namespace: 'sf')]
function fixtures_load(string $args = '--no-interaction --purge-with-truncate'): void
{
    run(dockerize(sprintf('%s %s doctrine:fixtures:load %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Install assets (copy/symlink)', namespace: 'sf')]
function assets_install(string $target = 'public', string $flags = '--symlink --relative'): void
{
    run(dockerize(sprintf('%s %s assets:install %s %s', php(), console_bin(), $flags, $target)));
}

#[AsTask(description: 'Lint YAML files', namespace: 'sf')]
function lint_yaml(string $paths = 'config', string $args = '--parse-tags'): void
{
    run(dockerize(sprintf('%s %s lint:yaml %s %s', php(), console_bin(), $paths, $args)));
}

#[AsTask(description: 'Lint Twig templates', namespace: 'sf')]
function lint_twig(string $paths = 'templates', string $args = ''): void
{
    run(dockerize(sprintf('%s %s lint:twig %s %s', php(), console_bin(), $paths, $args)));
}

#[AsTask(description: 'Lint container', namespace: 'sf')]
function lint_container(string $args = ''): void
{
    run(dockerize(sprintf('%s %s lint:container %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Run all lints (YAML, Twig, container) - composite', namespace: 'sf')]
function lint_all(): void
{
    lint_yaml();
    lint_twig();
    lint_container();
}

#[AsTask(description: 'Consume Messenger messages', namespace: 'sf')]
function messenger_consume(string $transports = 'async', string $args = '--time-limit=3600 --memory-limit=256M'): void
{
    run(dockerize(sprintf('%s %s messenger:consume %s %s', php(), console_bin(), $transports, $args)));
}

#[AsTask(description: 'Tail Symfony logs', namespace: 'sf')]
function logs_tail(string $lines = '200'): void
{
    $env = env_value('APP_ENV', 'dev');
    $file = env_value('SF_LOG_FILE', sprintf('var/log/%s.log', $env));

    // Ensure the log directory and file exist to avoid tail errors, and do not follow to keep task finite for tests/CI
    if (!is_file((string) $file)) {
        $dir = dirname((string) $file);
        if (!is_dir($dir)) {
            run(dockerize(sprintf('mkdir -p %s', $dir)));
        }
        run(dockerize(sprintf('touch %s', $file)));
    }

    run(dockerize(sprintf('tail -n %s %s', $lines, $file)));
}

#[AsTask(description: 'Proxy to bin/console with ARGS (env)', namespace: 'sf', aliases: ['sfc'])]
function console(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', php(), console_bin(), $args)));
}

#[AsTask(description: 'Project setup (composite): install, db create, migrate, fixtures, cache warmup, assets', namespace: 'sf')]
function setup(bool $fixtures = true): void
{
    composer_install();
    db_create();
    migrate();
    if ($fixtures) {
        fixtures_load();
    }
    cache_warmup();
    assets_install();
}

#[AsTask(description: 'CI helper (lints + tests) - composite', namespace: 'sf')]
function ci(): void
{
    lint_all();
    test();
}
