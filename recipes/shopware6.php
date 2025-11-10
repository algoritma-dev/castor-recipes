<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

function sw_console_bin(): string
{
    return env_value('SW_CONSOLE', 'bin/console');
}

#[AsTask(description: 'Install dependencies and prepare Shopware (system:install)', aliases: ['setup'], namespace: 'sw')]
function system_install(string $args = '--create-database --basic-setup --force'): void
{
    run(dockerize(sprintf('%s %s system:install %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Rebuild cache and indexes', namespace: 'sw')]
function build(bool $storefront = true, bool $admin = true, string $args = ''): void
{
    cache_clear();
    run(dockerize(sprintf('%s %s dal:refresh:index %s', php(), sw_console_bin(), $args))); // data abstraction layer
    if ($storefront) {
        storefront_build();
    }
    if ($admin) {
        administration_build();
    }
}

#[AsTask(description: 'Clear Shopware cache', namespace: 'sw')]
function cache_clear(string $args = ''): void
{
    run(dockerize(sprintf('%s %s cache:clear %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Build Storefront (if present)', namespace: 'sw')]
function storefront_build(string $args = ''): void
{
    run(dockerize(sprintf('%s %s storefront:build %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Build Administration (if present)', namespace: 'sw')]
function administration_build(string $args = ''): void
{
    run(dockerize(sprintf('%s %s administration:build %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Run PHP tests (PHPUnit)', namespace: 'sw')]
function test(string $args = ''): void
{
    run(dockerize(sprintf('%s %s', phpunit_bin(), $args)));
}

#[AsTask(description: 'Refresh plugins list', namespace: 'sw')]
function plugin_refresh(string $args = ''): void
{
    run(dockerize(sprintf('%s %s plugin:refresh %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Install and activate plugin(s). Set SW_PLUGIN_NAMES (comma/space) or SHOPWARE_PLUGIN (compat).', namespace: 'sw')]
function plugin_install_activate(string $plugins, string $args = ''): void
{
    $plugins = preg_split('/[\s,]+/', trim($plugins)) ?: [];
    if ($plugins === [] || $plugins[0] === '') {
        run(dockerize(sprintf('%s %s plugin:list', php(), sw_console_bin())));

        return;
    }

    foreach ($plugins as $plugin) {
        run(dockerize(sprintf('%s %s plugin:install --activate %s %s', php(), sw_console_bin(), $args, escapeshellarg($plugin))));
    }
}

#[AsTask(description: 'Compile themes', namespace: 'sw')]
function theme_compile(string $args = ''): void
{
    run(dockerize(sprintf('%s %s theme:compile %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Run DB migrations (non-destructive)', namespace: 'sw')]
function migrate(string $args = ''): void
{
    run(dockerize(sprintf('%s %s database:migrate %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Run DB migrations (destructive)', namespace: 'sw')]
function migrate_destructive(string $args = ''): void
{
    run(dockerize(sprintf('%s %s database:migrate-destructive %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Create admin user (env: SW_ADMIN_EMAIL, SW_ADMIN_PASSWORD, names/locale)', namespace: 'sw')]
function admin_create(string $email = 'admin@shopware.com', string $password = 'shopware', string $firstname = 'Admin', string $lastname = 'User', string $locale = 'en'): void
{
    $localeArg = $locale !== '' ? sprintf('--locale=%s', escapeshellarg($locale)) : '';

    run(dockerize(sprintf(
        '%s %s user:create %s --admin --password=%s --firstName=%s --lastName=%s %s',
        php(),
        sw_console_bin(),
        escapeshellarg($email),
        escapeshellarg($password),
        escapeshellarg($firstname),
        escapeshellarg($lastname),
        $localeArg
    )));
}

#[AsTask(description: 'Proxy to bin/console with ARGS (env)', namespace: 'sw')]
function console(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Project setup (composite): composer install, system:install, migrate, plugin refresh/install, theme, optional admin', namespace: 'sw')]
function setup_full(string $plugins, bool $withAdmin): void
{
    composer_install();
    system_install();
    migrate();
    migrate_destructive();
    plugin_refresh();

    if ($plugins !== '') {
        plugin_install_activate($plugins);
    }

    theme_compile();

    if ($withAdmin) {
        admin_create();
    }
}

#[AsTask(description: 'CI helper (build + tests) - composite', namespace: 'sw')]
function ci(): void
{
    build();
    test();
}
