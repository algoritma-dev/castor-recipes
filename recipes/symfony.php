<?php

declare(strict_types=1);

use Castor\Attribute\AsRawTokens;
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

#[AsTask(namespace: 'sf', description: 'Start Symfony local server (uses SYMFONY_BIN, SF_SERVER_FLAGS)', aliases: ['sfs'])]
function serve(string $flags = '-d'): void
{
    run(\sprintf('%s server:start %s', symfony_bin(), $flags));
}

#[AsTask(namespace: 'sf', description: 'Stop Symfony local server')]
function serve_stop(): void
{
    run(\sprintf('%s server:stop', symfony_bin()));
}

#[AsTask(namespace: 'sf', description: 'Run Doctrine migrations', aliases: ['sfm'])]
function migrate(
    #[AsRawTokens]
    array $args = ['--no-interaction']
): void {
    $args = $args === [] ? ['--no-interaction'] : $args;
    console(['doctrine:migrations:migrate', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Generate Doctrine migration from changes')]
function migrate_diff(
    #[AsRawTokens]
    array $args = []
): void {
    $args = $args === [] ? ['--no-interaction'] : $args;
    console(['doctrine:migrations:diff', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Clear Symfony cache', aliases: ['sfcc'])]
function cache_clear(
    #[AsRawTokens]
    array $args = []
): void {
    $args = $args === [] ? ['--no-debug'] : $args;
    console(['cache:clear', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Warm up Symfony cache')]
function cache_warmup(
    #[AsRawTokens]
    array $args = []
): void {
    $args = $args === [] ? ['--no-debug'] : $args;
    console(['cache:warmup', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Clear then warm up cache (composite)')]
function cache_clear_warmup(): void
{
    cache_clear();
    cache_warmup();
}

#[AsTask(namespace: 'sf', description: 'Run tests (PHPUnit)', aliases: ['sft'])]
function test(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s', phpunit_bin(), implode(' ', $args))));
}

#[AsTask(name: 'db-create', namespace: 'sf', description: 'Create database')]
function sf_db_create(
    #[AsRawTokens]
    array $args = []
): void {
    console(['doctrine:database:create', '--if-not-exists', ...$args]);
}

#[AsTask(name: 'db-drop', namespace: 'sf', description: 'Drop database')]
function sf_db_drop(
    #[AsRawTokens]
    array $args = []
): void {
    console(['doctrine:database:drop', '--force', '--if-exists', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Reset database (drop, create, migrate, fixtures) - composite')]
function db_reset(bool $fixtures = true): void
{
    sf_db_drop();
    sf_db_create();
    migrate();
    if ($fixtures) {
        fixtures_load();
    }
}

#[AsTask(namespace: 'sf', description: 'Run migrations from scratch (drop schema, migrate)')]
function migrate_fresh(
    #[AsRawTokens]
    array $args = []
): void {
    console(['doctrine:schema:drop', '--force', '--full-database', ...$args]);
    console(['doctrine:migrations:migrate', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Load Doctrine fixtures (if installed)')]
function fixtures_load(
    #[AsRawTokens]
    array $args = []
): void {
    $args = $args === [] ? ['--no-interaction --purge-with-truncate'] : $args;
    console(['doctrine:fixtures:load', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Install assets (copy/symlink)')]
function assets_install(
    #[AsRawTokens]
    array $args = []
): void {
    $args = $args === [] ? ['--symlink', '--relative', 'public'] : $args;
    console(['assets:install', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Lint YAML files')]
function lint_yaml(
    string $paths = 'config',
    #[AsRawTokens]
    array $args = []
): void {
    $args = $args === [] ? ['--parse-tags'] : $args;
    console(['lint:yaml', $paths, ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Lint Twig templates')]
function lint_twig(
    string $paths = 'templates',
    #[AsRawTokens]
    array $args = []
): void {
    console(['lint:twig', $paths, ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Lint container')]
function lint_container(
    #[AsRawTokens]
    array $args = []
): void {
    console(['lint:container', ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Run all lints (YAML, Twig, container) - composite')]
function lint_all(): void
{
    lint_yaml();
    lint_twig();
    lint_container();
}

#[AsTask(namespace: 'sf', description: 'Consume Messenger messages')]
function messenger_consume(
    string $transports = 'async',
    #[AsRawTokens]
    array $args = []
): void {
    $args = $args === [] ? ['--time-limit=3600 --memory-limit=256M'] : $args;
    console(['messenger:consume', $transports, ...$args]);
}

#[AsTask(namespace: 'sf', description: 'Tail Symfony logs')]
function logs_tail(string $lines = '200'): void
{
    $env = env_value('APP_ENV', 'dev');
    $file = env_value('SF_LOG_FILE', \sprintf('var/log/%s.log', $env));

    // Ensure the log directory and file exist to avoid tail errors, and do not follow to keep task finite for tests/CI
    if (! is_file((string) $file)) {
        $dir = \dirname((string) $file);
        if (! is_dir($dir)) {
            run(dockerize(\sprintf('mkdir -p %s', $dir)));
        }
        run(dockerize(\sprintf('touch %s', $file)));
    }

    run(dockerize(\sprintf('tail -n %s %s', $lines, $file)));
}

#[AsTask(namespace: 'sf', description: 'Proxy to bin/console with ARGS (env)', aliases: ['sfc'])]
function console(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s %s', php(), console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sf', description: 'Project setup (composite): install, db create, migrate, fixtures, cache warmup, assets')]
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

#[AsTask(namespace: 'sf', description: 'CI helper (lints + tests) - composite')]
function ci(): void
{
    lint_all();
    test();
}
