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
function magento2_static_deploy(string $locales = 'en_US'): void
{
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
function magento2_module_enable(string $module = ''): void
{
    if ($module === '') {
        run(dockerize('php bin/magento module:status'));

        return;
    }
    run(dockerize(sprintf('php bin/magento module:enable %s', escapeshellarg($module))));
}

#[AsTask(description: 'Disable a module (set M2_MODULE env)')]
function magento2_module_disable(string $module = ''): void
{
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

// === Enhancements to match Symfony/Shopware richness ===
function m2_console_bin(): string
{
    return (string) env_value('M2_BIN', 'bin/magento');
}

#[AsTask(description: 'Proxy to bin/magento with ARGS')]
function magento2_console(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', php(), m2_console_bin(), $args)));
}

#[AsTask(description: 'Switch to production mode and deploy static content (composite)')]
function magento2_mode_production(string $locales = 'en_US'): void
{
    magento2_console('deploy:mode:set production');
    magento2_console(sprintf('setup:static-content:deploy -f %s', escapeshellarg($locales)));
    magento2_cache_flush();
}

#[AsTask(description: 'Deploy sample data packages')]
function magento2_sampledata_deploy(): void
{
    magento2_console('sampledata:deploy');
}

#[AsTask(description: 'Apply setup upgrade after sample data')]
function magento2_sampledata_upgrade(): void
{
    magento2_console('setup:upgrade');
}

#[AsTask(description: 'Set configuration value (path, value, scope, scope-code)')]
function magento2_config_set(string $path, string $value, string $scope = 'default', string $scopeCode = ''): void
{
    $scopeArgs = $scope !== '' ? sprintf('--scope=%s', escapeshellarg($scope)) : '';
    $scopeCodeArgs = $scopeCode !== '' ? sprintf('--scope-code=%s', escapeshellarg($scopeCode)) : '';
    magento2_console(sprintf('config:set %s %s %s %s', escapeshellarg($path), escapeshellarg($value), $scopeArgs, $scopeCodeArgs));
}

#[AsTask(description: 'Get configuration value (path, scope, scope-code)')]
function magento2_config_get(string $path, string $scope = '', string $scopeCode = ''): void
{
    $scopeArgs = $scope !== '' ? sprintf('--scope=%s', escapeshellarg($scope)) : '';
    $scopeCodeArgs = $scopeCode !== '' ? sprintf('--scope-code=%s', escapeshellarg($scopeCode)) : '';
    magento2_console(sprintf('config:show %s %s %s', escapeshellarg($path), $scopeArgs, $scopeCodeArgs));
}

#[AsTask(description: 'Tail Magento logs')]
function magento2_logs_tail(string $file = 'var/log/system.log', string $lines = '200'): void
{
    run(dockerize(sprintf('tail -n %s -f %s', escapeshellarg($lines), escapeshellarg($file))));
}

#[AsTask(description: 'Project setup full (composite): composer install, setup:upgrade, di:compile, static deploy, cache flush, reindex')]
function magento2_setup_full(): void
{
    composer_install();
    magento2_setup_upgrade();
    magento2_di_compile();
    magento2_static_deploy();
    magento2_cache_flush();
    magento2_indexer_reindex();
}

#[AsTask(description: 'CI helper (compile + reindex + tests) - composite')]
function magento2_ci(): void
{
    magento2_di_compile();
    magento2_indexer_reindex();
    magento2_test();
}
