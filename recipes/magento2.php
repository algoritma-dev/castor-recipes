<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_composer.php';

#[AsTask(description: 'Install Magento 2 dependencies and initial setup', namespace: 'magento')]
function setup(): void
{
    composer_install();
    // setup:install requires many options; here we assume env vars are already set in the project
    $cmd = 'php bin/magento setup:install '
        . '--base-url=${M2_BASE_URL:-http://localhost/} '
        . '--db-host=${M2_DB_HOST:-127.0.0.1} --db-name=${M2_DB_NAME:-magento} --db-user=${M2_DB_USER:-root} --db-password=${M2_DB_PASSWORD:-root} '
        . '--admin-firstname=Admin --admin-lastname=User --admin-email=admin@example.com --admin-user=admin --admin-password=${M2_ADMIN_PASSWORD:-Admin123!} '
        . '--backend-frontname=admin --language=en_US --currency=EUR --timezone=Europe/Rome --use-rewrites=1';
    run(dockerize($cmd));
}

#[AsTask(description: 'Developer mode, cache flush, reindex', namespace: 'magento')]
function dev(): void
{
    run(dockerize('php bin/magento deploy:mode:set developer'));
    run(dockerize('php bin/magento cache:flush'));
    run(dockerize('php bin/magento indexer:reindex'));
}

#[AsTask(description: 'Run unit tests (PHPUnit)', namespace: 'magento')]
function test(): void
{
    run(dockerize('vendor/bin/phpunit'));
}


#[AsTask(description: 'Run setup upgrade (DB/schema updates)', namespace: 'magento', aliases: ['msu'])]
function setup_upgrade(): void
{
    run(dockerize('php bin/magento setup:upgrade'));
}

#[AsTask(description: 'Compile DI', namespace: 'magento')]
function di_compile(): void
{
    run(dockerize('php bin/magento setup:di:compile'));
}

#[AsTask(description: 'Deploy static content (set M2_LOCALES env, default en_US)', namespace: 'magento')]
function static_deploy(string $locales = 'en_US'): void
{
    run(dockerize(sprintf('php bin/magento setup:static-content:deploy -f %s', $locales)));
}

#[AsTask(description: 'Cache clean', namespace: 'magento')]
function cache_clean(): void
{
    run(dockerize('php bin/magento cache:clean'));
}

#[AsTask(description: 'Cache flush', namespace: 'magento', aliases: ['mcf'])]
function cache_flush(): void
{
    run(dockerize('php bin/magento cache:flush'));
}

#[AsTask(description: 'Reindex all', namespace: 'magento', aliases: ['mri'])]
function indexer_reindex(): void
{
    run(dockerize('php bin/magento indexer:reindex'));
}

#[AsTask(description: 'Indexer status', namespace: 'magento')]
function indexer_status(): void
{
    run(dockerize('php bin/magento indexer:status'));
}

#[AsTask(description: 'Enable a module (set M2_MODULE env)', namespace: 'magento')]
function module_enable(string $module = ''): void
{
    if ($module === '') {
        run(dockerize('php bin/magento module:status'));

        return;
    }
    run(dockerize(sprintf('php bin/magento module:enable %s', $module)));
}

#[AsTask(description: 'Disable a module (set M2_MODULE env)', namespace: 'magento')]
function module_disable(string $module = ''): void
{
    if ($module === '') {
        run(dockerize('php bin/magento module:status'));

        return;
    }
    run(dockerize(sprintf('php bin/magento module:disable %s', $module)));
}

#[AsTask(description: 'Enable maintenance mode', namespace: 'magento')]
function maintenance_enable(): void
{
    run(dockerize('php bin/magento maintenance:enable'));
}

#[AsTask(description: 'Disable maintenance mode', namespace: 'magento')]
function maintenance_disable(): void
{
    run(dockerize('php bin/magento maintenance:disable'));
}

#[AsTask(description: 'Run cron', namespace: 'magento')]
function cron_run(): void
{
    run(dockerize('php bin/magento cron:run'));
}

// === Enhancements to match Symfony/Shopware richness ===
function m2_console_bin(): string
{
    return env_value('M2_BIN', 'bin/magento');
}

#[AsTask(description: 'Proxy to bin/magento with ARGS', namespace: 'magento', aliases: ['mc'])]
function console(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', php(), m2_console_bin(), $args)));
}

#[AsTask(description: 'Switch to production mode and deploy static content (composite)', namespace: 'magento')]
function mode_production(string $locales = 'en_US'): void
{
    console('deploy:mode:set production');
    console(sprintf('setup:static-content:deploy -f %s', $locales));
    cache_flush();
}

#[AsTask(description: 'Deploy sample data packages', namespace: 'magento')]
function sampledata_deploy(): void
{
    console('sampledata:deploy');
}

#[AsTask(description: 'Apply setup upgrade after sample data', namespace: 'magento')]
function sampledata_upgrade(): void
{
    console('setup:upgrade');
}

#[AsTask(description: 'Set configuration value (path, value, scope, scope-code)', namespace: 'magento')]
function config_set(string $path, string $value, string $scope = 'default', string $scopeCode = ''): void
{
    $scopeArgs = $scope !== '' ? sprintf('--scope=%s', $scope) : '';
    $scopeCodeArgs = $scopeCode !== '' ? sprintf('--scope-code=%s', $scopeCode) : '';
    console(sprintf('config:set %s %s %s %s', $path, $value, $scopeArgs, $scopeCodeArgs));
}

#[AsTask(description: 'Get configuration value (path, scope, scope-code)', namespace: 'magento')]
function config_get(string $path, string $scope = '', string $scopeCode = ''): void
{
    $scopeArgs = $scope !== '' ? sprintf('--scope=%s', $scope) : '';
    $scopeCodeArgs = $scopeCode !== '' ? sprintf('--scope-code=%s', $scopeCode) : '';
    console(sprintf('config:show %s %s %s', $path, $scopeArgs, $scopeCodeArgs));
}

#[AsTask(description: 'Tail Magento logs', namespace: 'magento')]
function logs_tail(string $file = 'var/log/system.log', string $lines = '200'): void
{
    run(dockerize(sprintf('tail -n %s -f %s', $lines, $file)));
}

#[AsTask(description: 'Project setup full (composite): composer install, setup:upgrade, di:compile, static deploy, cache flush, reindex', namespace: 'magento')]
function setup_full(): void
{
    composer_install();
    setup_upgrade();
    di_compile();
    static_deploy();
    cache_flush();
    indexer_reindex();
}

#[AsTask(description: 'CI helper (compile + reindex + tests) - composite', namespace: 'magento')]
function ci(): void
{
    di_compile();
    indexer_reindex();
    test();
}
