<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

function oro_console_bin(): string
{
    return oro_env_value('ORO_CONSOLE', 'bin/console');
}

function oro_env_value(string $key, string $default = ''): string
{
    return (string) env_value($key, $default, dirname(\Castor\Helper\PathHelper::getRoot()) . '/.env-app');
}

#[AsTask(description: 'Installa dipendenze e setup database OroCommerce (composite)')]
function oro_setup(bool $withDemoData = false): void
{
    run(dockerize('composer install'));

    $env = oro_env_value('ORO_ENV', 'dev');
    $demoArg = $withDemoData ? ' --with-demo-data' : '';
    $cmd = sprintf('%s %s oro:install --env=%s --timeout=1800 --no-interaction%s', php(), oro_console_bin(), escapeshellarg($env), $demoArg);
    run(dockerize($cmd));

    oro_assets_build();
}

#[AsTask(description: 'Ricostruisce cache e assets')]
function oro_build(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s cache:clear --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
    run(dockerize(sprintf('%s %s assets:install --symlink --relative public', php(), oro_console_bin())));
    run(dockerize(sprintf('%s %s oro:assets:build', php(), oro_console_bin())));
}

#[AsTask(description: 'Esegue i test (PHPUnit)')]
function oro_test(string $args = ''): void
{
    run(dockerize(sprintf('%s %s', phpunit_bin(), $args)));
}

#[AsTask(description: 'Run Oro update (database and schema)')]
function oro_update(?string $env = null, string $args = '--timeout=1800 --no-interaction'): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s oro:platform:update --env=%s %s', php(), oro_console_bin(), escapeshellarg($env), $args)));
}

#[AsTask(description: 'Consume message queue')]
function oro_mq_consume(string $args = '--no-interaction'): void
{
    run(dockerize(sprintf('%s %s oro:message-queue:consume %s', php(), oro_console_bin(), $args)));
}

#[AsTask(description: 'Reindex search')]
function oro_search_reindex(string $args = ''): void
{
    run(dockerize(sprintf('%s %s oro:search:reindex %s', php(), oro_console_bin(), $args)));
}

#[AsTask(description: 'Dump and build assets')]
function oro_assets_build(): void
{
    run(dockerize(sprintf('%s %s assets:install --symlink --relative public', php(), oro_console_bin())));
    run(dockerize(sprintf('%s %s oro:assets:build', php(), oro_console_bin())));
}

#[AsTask(description: 'Clear caches')]
function oro_cache_clear(?string $env = null, string $args = ''): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    $envArg = sprintf('--env=%s', escapeshellarg($env));
    run(dockerize(sprintf('%s %s cache:clear %s %s', php(), oro_console_bin(), $envArg, $args)));
}

#[AsTask(description: 'Tail Oro logs (env: ORO_ENV, ORO_LOG_FILE, ORO_LOG_LINES)')]
function oro_logs_tail(?string $env = null, string $lines = '200'): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    $file = oro_env_value('ORO_LOG_FILE', sprintf('var/log/%s.log', $env));
    run(dockerize(sprintf('tail -n %s -f %s', escapeshellarg($lines), escapeshellarg($file))));
}

#[AsTask(description: 'Proxy to bin/console con ARGS')]
function oro_console(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', php(), oro_console_bin(), $args)));
}

#[AsTask(description: 'CI helper (cache/build + tests) - composite')]
function oro_ci(): void
{
    oro_build();
    oro_test();
}

#[AsTask(description: 'Oro migration: cache:clear + oro:migration:load --force')]
function oro_migrations(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s cache:clear --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
    run(dockerize(sprintf('%s %s oro:migration:load --force --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Doctrine schema update (dump-sql --complete)')]
function oro_schema_update_dump(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s doctrine:schema:update --dump-sql --complete --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Oro migration data load')]
function oro_migration_data_load(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s oro:migration:data:load --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Website search reindex (storefront)')]
function oro_website_search_reindex(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s oro:website-search:reindex --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Upgrade toolkit (compatibilità) - esegue un reindex di ricerca')]
function oro_upgrade_toolkit(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s oro:search:reindex --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Installazione Oro (crea DB e lancia oro:install)')]
function oro_install(?string $env = null, string $installArgs = '--timeout=900000'): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s doctrine:database:create --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
    // Nota: la creazione dell'estensione uuid-ossp via psql non è generica; gestirla esternamente se necessario.
    run(dockerize(sprintf('%s %s oro:install --env=%s %s', php(), oro_console_bin(), escapeshellarg($env), $installArgs)));
}

#[AsTask(description: 'Pulisci cache entità estese e aggiorna configurazione')]
function oro_clear_extend(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s oro:entity-config:cache:clear --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
    run(dockerize(sprintf('%s %s oro:entity-config:update --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
    run(dockerize(sprintf('%s %s oro:entity-extend:cache:clear --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Assets: build in watch mode (opzionale: voce entry es. "main")')]
function oro_assets_watch(?string $entry = null, ?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    $watchArg = $entry ? sprintf('--watch %s', escapeshellarg($entry)) : '--watch';
    run(dockerize(sprintf('%s %s oro:assets:build --env=%s %s', php(), oro_console_bin(), escapeshellarg($env), $watchArg)));
}

#[AsTask(description: 'Assets: installa assets (symlink)')]
function oro_assets_install(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s oro:assets:install --symlink --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Routes: clear cache e dump JS routing (frontend e FOS)')]
function oro_routes_dump(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s router:cache:clear --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
    run(dockerize(sprintf('%s %s oro:frontend:js-routing:dump --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
    run(dockerize(sprintf('%s %s fos:js-routing:dump --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Workflows: ricarica definizioni')]
function oro_workflows_reload(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s oro:workflow:definitions:load --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}

#[AsTask(description: 'Traduzioni: load + dump')]
function oro_translations_refresh(?string $env = null): void
{
    $env ??= oro_env_value('ORO_ENV', 'dev');
    run(dockerize(sprintf('%s %s oro:translation:load --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
    run(dockerize(sprintf('%s %s oro:translation:dump --env=%s', php(), oro_console_bin(), escapeshellarg($env))));
}
