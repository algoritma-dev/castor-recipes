<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

#[AsTask(description: 'Install dependencies and prepare Shopware')]
function shopware_setup(): void
{
    run(dockerize('composer install'));
    run(dockerize('bin/console system:install --create-database --basic-setup --force')); // Shopware >= 6
}

#[AsTask(description: 'Rebuild cache and indexes')]
function shopware_build(): void
{
    run(dockerize('bin/console cache:clear'));
    run(dockerize('bin/console dal:refresh:index')); // data abstraction layer
}

#[AsTask(description: 'Run PHP tests (PHPUnit)')]
function shopware_test(): void
{
    $cmd = 'vendor/bin/phpunit';
    run(dockerize($cmd));
}


#[AsTask(description: 'Refresh plugins list')]
function shopware_plugin_refresh(): void
{
    run(dockerize('bin/console plugin:refresh'));
}

#[AsTask(description: 'Install and activate a plugin (set SHOPWARE_PLUGIN env)')]
function shopware_plugin_install_activate(): void
{
    $plugin = getenv('SHOPWARE_PLUGIN') ?: '';
    if ($plugin === '') {
        run(dockerize('bin/console plugin:list'));

        return;
    }
    run(dockerize(sprintf('bin/console plugin:install --activate %s', escapeshellarg($plugin))));
}

#[AsTask(description: 'Compile themes')]
function shopware_theme_compile(): void
{
    run(dockerize('bin/console theme:compile'));
}

#[AsTask(description: 'Run DB migrations (non-destructive)')]
function shopware_migrate(): void
{
    run(dockerize('bin/console database:migrate'));
}

#[AsTask(description: 'Run DB migrations (destructive)')]
function shopware_migrate_destructive(): void
{
    run(dockerize('bin/console database:migrate-destructive'));
}

#[AsTask(description: 'Create admin user (env: SW_ADMIN_EMAIL, SW_ADMIN_PASSWORD)')]
function shopware_admin_create(): void
{
    $email = getenv('SW_ADMIN_EMAIL') ?: 'admin@example.com';
    $password = getenv('SW_ADMIN_PASSWORD') ?: 'admin';
    $first = getenv('SW_ADMIN_FIRSTNAME') ?: 'Admin';
    $last = getenv('SW_ADMIN_LASTNAME') ?: 'User';
    run(dockerize(sprintf(
        'bin/console user:create %s --admin --password=%s --firstName=%s --lastName=%s',
        escapeshellarg($email),
        escapeshellarg($password),
        escapeshellarg($first),
        escapeshellarg($last)
    )));
}
