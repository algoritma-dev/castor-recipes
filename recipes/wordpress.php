<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;

require_once __DIR__ . '/common.php';

#[AsTask(description: 'Install dependencies and prepare WordPress (wp-cli)')]
function wp_setup(): void
{
    // If composer.json exists, install dependencies (for bedrock-like projects)
    if (file_exists('composer.json')) {
        run(dockerize('composer install'));
    }

    // Install WordPress via wp-cli if available
    $downloadCmd = 'wp core download --force';
    $configCmd = 'wp config create --dbname=${WP_DB_NAME:-wordpress} --dbuser=${WP_DB_USER:-root} --dbpass=${WP_DB_PASSWORD:-root} --dbhost=${WP_DB_HOST:-127.0.0.1} --skip-check';
    $installCmd = 'wp core install --url=${WP_URL:-http://localhost} --title="WP Site" --admin_user=admin --admin_password=${WP_ADMIN_PASSWORD:-Admin123!} --admin_email=admin@example.com';

    run(dockerize($downloadCmd));
    if (! file_exists('wp-config.php')) {
        run(dockerize($configCmd));
    }
    run(dockerize($installCmd));
}

#[AsTask(description: 'Update core, plugins and themes')]
function wp_update_all(): void
{
    run(dockerize('wp core update'));
    run(dockerize('wp plugin update --all'));
    run(dockerize('wp theme update --all'));
}

#[AsTask(description: 'Clean cache and build assets (if supported)')]
function wp_build(): void
{
    if (file_exists('package.json')) {
        $cmd = file_exists('yarn.lock') ? 'yarn install && yarn build' : 'npm ci && npm run build';
        run(dockerize($cmd));
    }
}


#[AsTask(description: 'Install and activate a plugin (set WP_PLUGIN env)')]
function wp_plugin_install_activate(): void
{
    $plugin = getenv('WP_PLUGIN') ?: '';
    if ($plugin === '') {
        run(dockerize('wp plugin list'));

        return;
    }
    run(dockerize(sprintf('wp plugin install %s --activate --force', escapeshellarg($plugin))));
}

#[AsTask(description: 'Install and activate a theme (set WP_THEME env)')]
function wp_theme_install_activate(): void
{
    $theme = getenv('WP_THEME') ?: '';
    if ($theme === '') {
        run(dockerize('wp theme list'));

        return;
    }
    run(dockerize(sprintf('wp theme install %s --activate --force', escapeshellarg($theme))));
}

#[AsTask(description: 'Flush permalinks')]
function wp_permalinks_flush(): void
{
    run(dockerize('wp rewrite flush --hard'));
}

#[AsTask(description: 'Create an admin user (env: WP_ADMIN_USER, WP_ADMIN_PASS, WP_ADMIN_EMAIL)')]
function wp_user_create_admin(): void
{
    $user = getenv('WP_ADMIN_USER') ?: 'admin2';
    $pass = getenv('WP_ADMIN_PASS') ?: 'Admin123!';
    $email = getenv('WP_ADMIN_EMAIL') ?: 'admin2@example.com';
    run(dockerize(sprintf(
        'wp user create %s %s --role=administrator --user_pass=%s',
        escapeshellarg($user),
        escapeshellarg($email),
        escapeshellarg($pass)
    )));
}

#[AsTask(description: 'Database export (to file, set WP_DB_EXPORT default db.sql)')]
function wp_db_export(): void
{
    $file = getenv('WP_DB_EXPORT') ?: 'db.sql';
    run(dockerize(sprintf('wp db export %s', escapeshellarg($file))));
}

#[AsTask(description: 'Database import (from file, set WP_DB_IMPORT)')]
function wp_db_import(): void
{
    $file = getenv('WP_DB_IMPORT') ?: '';
    if ($file === '') {
        io()->write('Set WP_DB_IMPORT to the SQL file path');

        return;
    }
    run(dockerize(sprintf('wp db import %s', escapeshellarg($file))));
}

#[AsTask(description: 'Search-replace in DB (set WP_SR_FROM, WP_SR_TO)')]
function wp_search_replace(): void
{
    $from = getenv('WP_SR_FROM') ?: '';
    $to = getenv('WP_SR_TO') ?: '';
    if ($from === '' || $to === '') {
        io()->write('Set WP_SR_FROM and WP_SR_TO');

        return;
    }
    run(dockerize(sprintf('wp search-replace %s %s --all-tables', escapeshellarg($from), escapeshellarg($to))));
}

#[AsTask(description: 'Flush caches (if any caching plugin is present)')]
function wp_cache_flush(): void
{
    run(dockerize('wp cache flush'));
}
