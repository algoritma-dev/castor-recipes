<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

function bin(): string
{
    return env_value('WP_BIN', 'wp');
}

#[AsTask(description: 'Proxy to wp-cli with ARGS', namespace: 'wp')]
function cli(string $args = ''): void
{
    run(dockerize(sprintf('%s %s', bin(), $args)));
}

#[AsTask(description: 'Install dependencies and prepare WordPress (wp-cli)', namespace: 'wp')]
function setup(): void
{
    // If composer.json exists, install dependencies (for bedrock-like projects)
    if (file_exists('composer.json')) {
        run(dockerize('composer install'));
    }

    // Install WordPress via wp-cli if available
    $downloadCmd = sprintf('%s core download --force', bin());
    $configCmd = sprintf('%s config create --dbname=${WP_DB_NAME:-wordpress} --dbuser=${WP_DB_USER:-root} --dbpass=${WP_DB_PASSWORD:-root} --dbhost=${WP_DB_HOST:-127.0.0.1} --skip-check', bin());
    $installCmd = sprintf('%s core install --url=${WP_URL:-http://localhost} --title="WP Site" --admin_user=admin --admin_password=${WP_ADMIN_PASSWORD:-Admin123!} --admin_email=admin@example.com', bin());

    run(dockerize($downloadCmd));
    if (! file_exists('wp-config.php')) {
        run(dockerize($configCmd));
    }
    run(dockerize($installCmd));
}

#[AsTask(description: 'Update core, plugins and themes', namespace: 'wp')]
function update_all(): void
{
    cli('core update');
    cli('plugin update --all');
    cli('theme update --all');
}

#[AsTask(description: 'Clean cache and build assets (if supported)', namespace: 'wp')]
function build(): void
{
    if (file_exists('package.json')) {
        $cmd = file_exists('yarn.lock') ? 'yarn install && yarn build' : 'npm ci && npm run build';
        run(dockerize($cmd));
    }
}

#[AsTask(description: 'Install and activate a plugin (set WP_PLUGIN env)', namespace: 'wp')]
function plugin_install_activate(string $plugin): void
{
    run(dockerize(sprintf('%s plugin install %s --activate --force', bin(), escapeshellarg($plugin))));
}

#[AsTask(description: 'Install and activate a theme (set WP_THEME env)', namespace: 'wp')]
function theme_install_activate(string $theme = ''): void
{
    if ($theme === '') {
        cli('theme list');

        return;
    }
    run(dockerize(sprintf('%s theme install %s --activate --force', bin(), escapeshellarg($theme))));
}

#[AsTask(description: 'Flush permalinks', namespace: 'wp')]
function permalinks_flush(): void
{
    cli('rewrite flush --hard');
}

#[AsTask(description: 'Create an admin user (env: WP_ADMIN_USER, WP_ADMIN_PASS, WP_ADMIN_EMAIL)', namespace: 'wp')]
function user_create_admin(string $user = 'admin', string $pass = 'Admin123', string $email = 'admin@example.com'): void
{
    run(dockerize(sprintf(
        '%s user create %s %s --role=administrator --user_pass=%s',
        bin(),
        escapeshellarg($user),
        escapeshellarg($email),
        escapeshellarg($pass)
    )));
}

#[AsTask(description: 'Database export (to file, set WP_DB_EXPORT default db.sql)', namespace: 'wp')]
function db_export(string $dump): void
{
    run(dockerize(sprintf('%s db export %s', bin(), escapeshellarg($dump))));
}

#[AsTask(description: 'Database import (from file, set WP_DB_IMPORT)', namespace: 'wp')]
function db_import(string $dump): void
{
    run(dockerize(sprintf('%s db import %s', bin(), escapeshellarg($dump))));
}

#[AsTask(description: 'Search-replace in DB (set WP_SR_FROM, WP_SR_TO)', namespace: 'wp')]
function search_replace(string $from, string $to): void
{
    run(dockerize(sprintf('%s search-replace %s %s --all-tables', bin(), escapeshellarg($from), escapeshellarg($to))));
}

#[AsTask(description: 'Flush caches (if any caching plugin is present)', namespace: 'wp')]
function cache_flush(): void
{
    cli('cache flush');
}

#[AsTask(description: 'CI helper (update + optional build) - composite', namespace: 'wp')]
function ci(): void
{
    update_all();
    cache_flush();
    build();
}
