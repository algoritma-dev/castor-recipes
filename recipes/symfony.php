<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

function sf_console_bin(): string
{
    return getenv('SF_CONSOLE') ?: 'bin/console';
}
function sf_symfony_bin(): string
{
    return getenv('SYMFONY_BIN') ?: 'symfony';
}

#[AsTask(description: 'Start Symfony local server (uses SYMFONY_BIN, SF_SERVER_FLAGS)')]
function sf_serve(string $flags = '-d'): void
{
    run(sprintf('%s server:start %s', sf_symfony_bin(), $flags));
}

#[AsTask(description: 'Stop Symfony local server')]
function sf_serve_stop(): void
{
    run(sprintf('%s server:stop', sf_symfony_bin()));
}

#[AsTask(description: 'Run Doctrine migrations')]
function sf_migrate(string $args = '--no-interaction'): void
{
    run(dockerize(sprintf('%s %s doctrine:migrations:migrate %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Generate Doctrine migration from changes')]
function sf_migrate_diff(string $args = '--no-interaction'): void
{
    run(dockerize(sprintf('%s %s doctrine:migrations:diff %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Clear Symfony cache')]
function sf_cache_clear(string $args = '--no-debug'): void
{
    run(dockerize(sprintf('%s %s cache:clear %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Warm up Symfony cache')]
function sf_cache_warmup(string $args = '--no-debug'): void
{
    run(dockerize(sprintf('%s %s cache:warmup %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Clear then warm up cache (composite)')]
function sf_cache_clear_warmup(): void
{
    sf_cache_clear();
    sf_cache_warmup();
}

#[AsTask(description: 'Run tests (PHPUnit)')]
function sf_test(string $args = ''): void
{
    run(dockerize(sprintf('%s %s', phpunit_bin(), $args)));
}

#[AsTask(description: 'Create database')]
function sf_db_create(string $args = ''): void
{
    run(dockerize(sprintf('%s %s doctrine:database:create --if-not-exists %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Drop database')]
function sf_db_drop(string $args = ''): void
{
    run(dockerize(sprintf('%s %s doctrine:database:drop --force --if-exists %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Reset database (drop, create, migrate, fixtures) - composite')]
function sf_db_reset(bool $fixtures = true): void
{
    sf_db_drop();
    sf_db_create();
    sf_migrate();
    if ($fixtures) {
        sf_fixtures_load();
    }
}

#[AsTask(description: 'Run migrations from scratch (drop schema, migrate)')]
function sf_migrate_fresh(string $args = ''): void
{
    run(dockerize(sprintf('%s %s doctrine:schema:drop --force --full-database', php(), sf_console_bin())));
    run(dockerize(sprintf('%s %s doctrine:migrations:migrate %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Load Doctrine fixtures (if installed)')]
function sf_fixtures_load(string $args = '--no-interaction --purge-with-truncate'): void
{
    run(dockerize(sprintf('%s %s doctrine:fixtures:load %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Install assets (copy/symlink)')]
function sf_assets_install(string $target = 'public', string $flags = '--symlink --relative'): void
{
    run(dockerize(sprintf('%s %s assets:install %s %s', php(), sf_console_bin(), $flags, escapeshellarg($target))));
}

#[AsTask(description: 'Lint YAML files')]
function sf_lint_yaml(string $paths = 'config', string $args = '--parse-tags'): void
{
    run(dockerize(sprintf('%s %s lint:yaml %s %s', php(), sf_console_bin(), $paths, $args)));
}

#[AsTask(description: 'Lint Twig templates')]
function sf_lint_twig(string $paths = 'templates', string $args = ''): void
{
    run(dockerize(sprintf('%s %s lint:twig %s %s', php(), sf_console_bin(), $paths, $args)));
}

#[AsTask(description: 'Lint container')]
function sf_lint_container(string $args = ''): void
{
    run(dockerize(sprintf('%s %s lint:container %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Run all lints (YAML, Twig, container) - composite')]
function sf_lint_all(): void
{
    sf_lint_yaml();
    sf_lint_twig();
    sf_lint_container();
}

#[AsTask(description: 'Consume Messenger messages')]
function sf_messenger_consume(string $transports = 'async', string $args = '--time-limit=3600 --memory-limit=256M'): void
{
    run(dockerize(sprintf('%s %s messenger:consume %s %s', php(), sf_console_bin(), escapeshellarg($transports), $args)));
}

#[AsTask(description: 'Tail Symfony logs')]
function sf_logs_tail(string $lines = '200'): void
{
    $env = getenv('APP_ENV') ?: 'dev';
    $file = getenv('SF_LOG_FILE') ?: sprintf('var/log/%s.log', $env);
    run(dockerize(sprintf('tail -n %s -f %s', escapeshellarg($lines), escapeshellarg($file))));
}

#[AsTask(description: 'Proxy to bin/console with ARGS (env)')]
function sf_console(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', php(), sf_console_bin(), $args)));
}

#[AsTask(description: 'Project setup (composite): install, db create, migrate, fixtures, cache warmup, assets')]
function sf_setup(): void
{
    composer_install();
    sf_db_create();
    sf_migrate();
    if (getenv('SF_SETUP_WITH_FIXTURES') === '1' || getenv('SF_SETUP_WITH_FIXTURES') === 'true') {
        sf_fixtures_load();
    }
    sf_cache_warmup();
    sf_assets_install();
}

#[AsTask(description: 'CI helper (lints + tests) - composite')]
function sf_ci(): void
{
    sf_lint_all();
    sf_test();
}
