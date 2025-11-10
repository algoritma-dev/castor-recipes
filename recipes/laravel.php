<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

function laravel_artisan_bin(): string
{
    return 'artisan';
}

#[AsTask(description: 'Proxy to artisan with ARGS')]
function laravel_artisan(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', php(), laravel_artisan_bin(), $args)));
}

#[AsTask(description: 'Install Composer dependencies')]
function laravel_install(): void
{
    composer_install();
}

#[AsTask(description: 'Run migrations and seeders')]
function laravel_migrate_seed(): void
{
    laravel_migrate();
    laravel_seed();
}

#[AsTask(description: 'Clear and rebuild cache')]
function laravel_cache(): void
{
    laravel_artisan('optimize:clear');
    laravel_artisan('optimize');
}

#[AsTask(description: 'Run tests (Pest/PHPUnit)')]
function laravel_test(string $args = ''): void
{
    $cmd = file_exists('vendor/bin/pest') ? 'vendor/bin/pest' : phpunit_bin();
    run(dockerize(sprintf('%s %s', $cmd, $args)));
}

#[AsTask(description: 'Start the queue worker in background (if not in Docker, use supervisord)')]
function laravel_queue(): void
{
    laravel_artisan('queue:work --tries=3 --timeout=120');
}

#[AsTask(description: 'Start Laravel development server')]
function laravel_serve(): void
{
    laravel_artisan('serve');
}

#[AsTask(description: 'Run database migrations')]
function laravel_migrate(): void
{
    laravel_artisan('migrate --force');
}

#[AsTask(description: 'Run database seeders')]
function laravel_seed(): void
{
    laravel_artisan('db:seed --force');
}

#[AsTask(description: 'Fresh migrate (drops all tables and re-runs all migrations)')]
function laravel_migrate_fresh(): void
{
    laravel_artisan('migrate:fresh --force');
}

#[AsTask(description: 'Generate application key')]
function laravel_key_generate(): void
{
    laravel_artisan('key:generate');
}

#[AsTask(description: 'Clear all caches (app, route, config, view)')]
function laravel_cache_clear_all(): void
{
    laravel_artisan('optimize:clear');
}

#[AsTask(description: 'Cache configuration')]
function laravel_config_cache(): void
{
    laravel_artisan('config:cache');
}

#[AsTask(description: 'Cache routes')]
function laravel_route_cache(): void
{
    laravel_artisan('route:cache');
}

#[AsTask(description: 'Cache events')]
function laravel_event_cache(): void
{
    laravel_artisan('event:cache');
}

#[AsTask(description: 'Restart queue workers')]
function laravel_queue_restart(): void
{
    laravel_artisan('queue:restart');
}

#[AsTask(description: 'Listen to the queue (foreground)')]
function laravel_queue_listen(): void
{
    laravel_artisan('queue:listen');
}

#[AsTask(description: 'Run scheduler once')]
function laravel_schedule_run(): void
{
    laravel_artisan('schedule:run');
}

#[AsTask(description: 'Create storage symlink')]
function laravel_storage_link(): void
{
    laravel_artisan('storage:link');
}

#[AsTask(description: 'Open Laravel Tinker shell')]
function laravel_tinker(): void
{
    laravel_artisan('tinker');
}

#[AsTask(description: 'Tail Laravel logs (env: LARAVEL_LOG_FILE, LARAVEL_LOG_LINES)')]
function laravel_logs_tail(string $file = 'storage/logs/laravel.log', string $lines = '200'): void
{
    run(dockerize(sprintf('tail -n %s -f %s', escapeshellarg($lines), escapeshellarg($file))));
}

#[AsTask(description: 'Project setup (composite): composer install, key, migrate, seed, storage link, cache')]
function laravel_setup(bool $seed = true): void
{
    laravel_install();
    laravel_key_generate();
    laravel_migrate();
    if ($seed) {
        laravel_seed();
    }
    laravel_storage_link();
    laravel_cache();
}

#[AsTask(description: 'CI helper (cache + tests) - composite')]
function laravel_ci(): void
{
    laravel_cache();
    laravel_test();
}
