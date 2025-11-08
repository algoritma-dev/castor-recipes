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
