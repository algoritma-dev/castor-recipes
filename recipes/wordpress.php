<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

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
