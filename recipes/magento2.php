<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

#[AsTask(description: 'Install Magento 2 dependencies and initial setup')]
function magento2_setup(): void
{
    run(dockerize('composer install'));
    // setup:install requires many options; here we assume env vars are already set in the project
    $cmd = 'php bin/magento setup:install '
        . '--base-url=${M2_BASE_URL:-http://localhost/} '
        . '--db-host=${M2_DB_HOST:-127.0.0.1} --db-name=${M2_DB_NAME:-magento} --db-user=${M2_DB_USER:-root} --db-password=${M2_DB_PASSWORD:-root} '
        . '--admin-firstname=Admin --admin-lastname=User --admin-email=admin@example.com --admin-user=admin --admin-password=${M2_ADMIN_PASSWORD:-Admin123!} '
        . '--backend-frontname=admin --language=en_US --currency=EUR --timezone=Europe/Rome --use-rewrites=1';
    run(dockerize($cmd));
}

#[AsTask(description: 'Developer mode, cache flush, reindex')]
function magento2_dev(): void
{
    run(dockerize('php bin/magento deploy:mode:set developer'));
    run(dockerize('php bin/magento cache:flush'));
    run(dockerize('php bin/magento indexer:reindex'));
}

#[AsTask(description: 'Run unit tests (PHPUnit)')]
function magento2_test(): void
{
    run(dockerize('vendor/bin/phpunit'));
}
