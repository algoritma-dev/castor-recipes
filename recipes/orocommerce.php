<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/postgresql.php';

function console_bin(): string
{
    return oro_env_value('ORO_CONSOLE', 'bin/console');
}

function oro_env_value(string $key, string $default = ''): string
{
    $projectRoot = getcwd();

    return env_value($key, $default, $projectRoot . '/.env-app');
}

#[AsTask(namespace: 'oro', description: 'Installa dipendenze e setup database OroCommerce (composite)')]
function setup(bool $withDemoData = false): void
{
    composer_install();

    $env = oro_env_value('ORO_ENV', 'dev');
    $demoArg = $withDemoData ? ' --with-demo-data' : '';

    dockerize(\sprintf('rm -rf var/cache/%s', $env));
    $cmd = \sprintf('%s %s oro:install --env=%s --timeout=900000%s', php(), console_bin(), $env, $demoArg);
    run(dockerize($cmd));

    assets_build();
}

#[AsTask(namespace: 'oro', description: 'Installazione Oro (crea DB e lancia oro:install)')]
function install(?string $env = null, string $installArgs = '--timeout=900000 --drop-database'): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');

    dockerize(\sprintf('rm -rf var/cache/%s', $env));
    run(dockerize(\sprintf('%s %s oro:install --env=%s %s', php(), console_bin(), $env, $installArgs)));
}

#[AsTask(namespace: 'oro', description: 'Ricostruisce cache e assets')]
function build(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s cache:clear --env=%s', php(), console_bin(), $env)));
    run(dockerize(\sprintf('%s %s assets:install --symlink --relative public', php(), console_bin())));
    run(dockerize(\sprintf('%s %s oro:assets:build', php(), console_bin())));
}

#[AsTask(namespace: 'oro', description: 'Esegue i test (PHPUnit)')]
function test(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s', phpunit_bin(), $args)));
}

#[AsTask(namespace: 'oro', description: 'Run Oro update (database and schema)')]
function update(?string $env = null, string $args = '--timeout=1800 --no-interaction'): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s oro:platform:update --env=%s %s', php(), console_bin(), $env, $args)));
}

#[AsTask(namespace: 'oro', description: 'Consume message queue')]
function mq_consume(string $args = '--no-interaction'): void
{
    run(dockerize(\sprintf('%s %s oro:message-queue:consume %s', php(), console_bin(), $args)));
}

#[AsTask(namespace: 'oro', description: 'Reindex search')]
function search_reindex(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s oro:search:reindex %s', php(), console_bin(), $args)));
}

#[AsTask(namespace: 'oro', description: 'Dump and build assets')]
function assets_build(): void
{
    run(dockerize(\sprintf('%s %s assets:install --symlink --relative public', php(), console_bin())));
    run(dockerize(\sprintf('%s %s oro:assets:build', php(), console_bin())));
}

#[AsTask(namespace: 'oro', description: 'Clear caches')]
function cache_clear(?string $env = null, string $args = ''): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    $envArg = \sprintf('--env=%s', $env);
    run(dockerize(\sprintf('%s %s cache:clear %s %s', php(), console_bin(), $envArg, $args)));
}

#[AsTask(namespace: 'oro', description: 'Tail Oro logs (env: ORO_ENV, ORO_LOG_FILE, ORO_LOG_LINES)')]
function logs_tail(?string $env = null, string $lines = '200'): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    $file = oro_env_value('ORO_LOG_FILE', \sprintf('var/log/%s.log', $env));
    run(dockerize(\sprintf('tail -n %s -f %s', $lines, $file)));
}

#[AsTask(namespace: 'oro', description: 'Proxy to bin/console con ARGS')]
function console(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s %s', php(), console_bin(), $args)));
}

#[AsTask(namespace: 'oro', description: 'CI helper (cache/build + tests) - composite')]
function ci(): void
{
    build();
    test();
}

#[AsTask(namespace: 'oro', description: 'Oro migration: cache:clear + oro:migration:load --force')]
function migrations(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s cache:clear --env=%s', php(), console_bin(), $env)));
    run(dockerize(\sprintf('%s %s oro:migration:load --force --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Doctrine schema update (dump-sql --complete)')]
function schema_update_dump(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s doctrine:schema:update --dump-sql --complete --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Oro migration data load')]
function migration_data_load(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s oro:migration:data:load --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Website search reindex (storefront)')]
function website_search_reindex(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s oro:website-search:reindex --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Upgrade toolkit (compatibilità) - esegue un reindex di ricerca')]
function upgrade_toolkit(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s oro:search:reindex --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Pulisci cache entità estese e aggiorna configurazione')]
function clear_extend(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s oro:entity-config:cache:clear --env=%s', php(), console_bin(), $env)));
    run(dockerize(\sprintf('%s %s oro:entity-config:update --env=%s', php(), console_bin(), $env)));
    run(dockerize(\sprintf('%s %s oro:entity-extend:cache:clear --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Assets: build in watch mode (opzionale: voce entry es. "main")')]
function assets_watch(?string $entry = null, ?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    $watchArg = $entry ? \sprintf('--watch %s', $entry) : '--watch';
    run(dockerize(\sprintf('%s %s oro:assets:build --env=%s %s', php(), console_bin(), $env, $watchArg)));
}

#[AsTask(namespace: 'oro', description: 'Assets: installa assets (symlink)')]
function assets_install(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s oro:assets:install --symlink --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Routes: clear cache e dump JS routing (frontend e FOS)')]
function routes_dump(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s router:cache:clear --env=%s', php(), console_bin(), $env)));
    run(dockerize(\sprintf('%s %s oro:frontend:js-routing:dump --env=%s', php(), console_bin(), $env)));
    run(dockerize(\sprintf('%s %s fos:js-routing:dump --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Workflows: ricarica definizioni')]
function workflows_reload(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s oro:workflow:definitions:load --env=%s', php(), console_bin(), $env)));
}

#[AsTask(namespace: 'oro', description: 'Traduzioni: load + dump')]
function translations_refresh(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(\sprintf('%s %s oro:translation:load --env=%s', php(), console_bin(), $env)));
    run(dockerize(\sprintf('%s %s oro:translation:dump --env=%s', php(), console_bin(), $env)));
}
