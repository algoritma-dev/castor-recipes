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


#[AsTask(description: 'Run setup upgrade (DB/schema updates)')]
function magento2_setup_upgrade(): void
{
    run(dockerize('php bin/magento setup:upgrade'));
}

#[AsTask(description: 'Compile DI')]
function magento2_di_compile(): void
{
    run(dockerize('php bin/magento setup:di:compile'));
}

#[AsTask(description: 'Deploy static content (set M2_LOCALES env, default en_US)')]
function magento2_static_deploy(): void
{
    $locales = getenv('M2_LOCALES') ?: 'en_US';
    run(dockerize(sprintf('php bin/magento setup:static-content:deploy -f %s', escapeshellarg($locales))));
}

#[AsTask(description: 'Cache clean')]
function magento2_cache_clean(): void
{
    run(dockerize('php bin/magento cache:clean'));
}

#[AsTask(description: 'Cache flush')]
function magento2_cache_flush(): void
{
    run(dockerize('php bin/magento cache:flush'));
}

#[AsTask(description: 'Reindex all')]
function magento2_indexer_reindex(): void
{
    run(dockerize('php bin/magento indexer:reindex'));
}

#[AsTask(description: 'Indexer status')]
function magento2_indexer_status(): void
{
    run(dockerize('php bin/magento indexer:status'));
}

#[AsTask(description: 'Enable a module (set M2_MODULE env)')]
function magento2_module_enable(): void
{
    $module = getenv('M2_MODULE') ?: '';
    if ($module === '') {
        run(dockerize('php bin/magento module:status'));

        return;
    }
    run(dockerize(sprintf('php bin/magento module:enable %s', escapeshellarg($module))));
}

#[AsTask(description: 'Disable a module (set M2_MODULE env)')]
function magento2_module_disable(): void
{
    $module = getenv('M2_MODULE') ?: '';
    if ($module === '') {
        run(dockerize('php bin/magento module:status'));

        return;
    }
    run(dockerize(sprintf('php bin/magento module:disable %s', escapeshellarg($module))));
}

#[AsTask(description: 'Enable maintenance mode')]
function magento2_maintenance_enable(): void
{
    run(dockerize('php bin/magento maintenance:enable'));
}

#[AsTask(description: 'Disable maintenance mode')]
function magento2_maintenance_disable(): void
{
    run(dockerize('php bin/magento maintenance:disable'));
}

#[AsTask(description: 'Run cron')]
function magento2_cron_run(): void
{
    run(dockerize('php bin/magento cron:run'));
}
