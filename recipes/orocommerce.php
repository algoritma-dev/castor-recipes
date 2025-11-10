<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

function oro_console_bin(): string
{
    return getenv('ORO_CONSOLE') ?: 'bin/console';
}

#[AsTask(description: 'Installa dipendenze e setup database OroCommerce (composite)')]
function oro_setup(bool $withDemoData = false): void
{
    run(dockerize('composer install'));

    $demoArg = $withDemoData ? ' --with-demo-data' : '';
    $cmd = sprintf('%s %s oro:install --env=prod --timeout=1800 --no-interaction%s', php(), oro_console_bin(), $demoArg);
    run(dockerize($cmd));

    oro_assets_build();
}

#[AsTask(description: 'Ricostruisce cache e assets')]
function oro_build(): void
{
    run(dockerize(sprintf('%s %s cache:clear --env=prod', php(), oro_console_bin())));
    run(dockerize(sprintf('%s %s assets:install --symlink --relative public', php(), oro_console_bin())));
    run(dockerize(sprintf('%s %s oro:assets:build', php(), oro_console_bin())));
}

#[AsTask(description: 'Esegue i test (PHPUnit)')]
function oro_test(string $args = ''): void
{
    run(dockerize(sprintf('%s %s', phpunit_bin(), $args)));
}

#[AsTask(description: 'Run Oro update (database and schema)')]
function oro_update(string $args = '--env=prod --timeout=1800 --no-interaction'): void
{
    run(dockerize(sprintf('%s %s oro:platform:update %s', php(), oro_console_bin(), $args)));
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
function oro_cache_clear(string $args = '--env=prod'): void
{
    run(dockerize(sprintf('%s %s cache:clear %s', php(), oro_console_bin(), $args)));
}

#[AsTask(description: 'Tail Oro logs (env: APP_ENV, ORO_LOG_FILE, ORO_LOG_LINES)')]
function oro_logs_tail(string $lines = '200'): void
{
    $env = getenv('APP_ENV') ?: 'dev';
    $file = getenv('ORO_LOG_FILE') ?: sprintf('var/log/%s.log', $env);
    run(dockerize(sprintf('tail -n %s -f %s', escapeshellarg($lines), escapeshellarg($file))));
}

#[AsTask(description: 'Proxy to bin/console with ARGS')]
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
