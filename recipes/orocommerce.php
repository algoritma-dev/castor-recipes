<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

#[AsTask(description: 'Installa dipendenze e setup database OroCommerce')]
function oro_setup(): void
{
    run(dockerize('composer install'));
    // OroCommerce install command (may require env vars like APP_ENV, DB creds)
    run(dockerize('php bin/console oro:install --env=prod --timeout=1200 --no-interaction'));
}

#[AsTask(description: 'Ricostruisce cache e assets')]
function oro_build(): void
{
    run(dockerize('php bin/console cache:clear --env=prod'));
    run(dockerize('php bin/console assets:install --symlink --relative public'));
}

#[AsTask(description: 'Esegue i test (PHPUnit)')]
function oro_test(): void
{
    run(dockerize('vendor/bin/phpunit'));
}


#[AsTask(description: 'Run Oro update (database and schema)')]
function oro_update(): void
{
    run(dockerize('php bin/console oro:platform:update --env=prod --timeout=1800 --no-interaction'));
}

#[AsTask(description: 'Consume message queue')]
function oro_mq_consume(): void
{
    run(dockerize('php bin/console oro:message-queue:consume --no-interaction'));
}

#[AsTask(description: 'Reindex search')]
function oro_search_reindex(): void
{
    run(dockerize('php bin/console oro:search:reindex'));
}

#[AsTask(description: 'Dump and build assets')]
function oro_assets_build(): void
{
    run(dockerize('php bin/console assets:install --symlink --relative public'));
    run(dockerize('php bin/console oro:assets:build'));
}

#[AsTask(description: 'Clear caches')]
function oro_cache_clear(): void
{
    run(dockerize('php bin/console cache:clear --env=prod'));
}
