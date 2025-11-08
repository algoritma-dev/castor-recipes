<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

#[AsTask(description: 'Install Composer dependencies')]
function laravel_install(): void
{
    run(dockerize('composer install'));
}

#[AsTask(description: 'Run migrations and seeders')]
function laravel_migrate_seed(): void
{
    run(dockerize('php artisan migrate --force'));
    run(dockerize('php artisan db:seed --force'));
}

#[AsTask(description: 'Clear and rebuild cache')]
function laravel_cache(): void
{
    run(dockerize('php artisan optimize:clear'));
    run(dockerize('php artisan optimize'));
}

#[AsTask(description: 'Run tests (Pest/PHPUnit)')]
function laravel_test(): void
{
    $cmd = file_exists('vendor/bin/pest') ? 'vendor/bin/pest' : 'vendor/bin/phpunit';
    run(dockerize($cmd));
}

#[AsTask(description: 'Start the queue worker in background (if not in Docker, use supervisord)')]
function laravel_queue(): void
{
    run(dockerize('php artisan queue:work --tries=3 --timeout=120'));
}


#[AsTask(description: 'Start Laravel development server')]
function laravel_serve(): void
{
    run(dockerize('php artisan serve'));
}

#[AsTask(description: 'Run database migrations')]
function laravel_migrate(): void
{
    run(dockerize('php artisan migrate --force'));
}

#[AsTask(description: 'Run database seeders')]
function laravel_seed(): void
{
    run(dockerize('php artisan db:seed --force'));
}

#[AsTask(description: 'Fresh migrate (drops all tables and re-runs all migrations)')]
function laravel_migrate_fresh(): void
{
    run(dockerize('php artisan migrate:fresh --force'));
}

#[AsTask(description: 'Generate application key')]
function laravel_key_generate(): void
{
    run(dockerize('php artisan key:generate'));
}

#[AsTask(description: 'Clear all caches (app, route, config, view)')]
function laravel_cache_clear_all(): void
{
    run(dockerize('php artisan optimize:clear'));
}

#[AsTask(description: 'Cache configuration')]
function laravel_config_cache(): void
{
    run(dockerize('php artisan config:cache'));
}

#[AsTask(description: 'Cache routes')]
function laravel_route_cache(): void
{
    run(dockerize('php artisan route:cache'));
}

#[AsTask(description: 'Cache events')]
function laravel_event_cache(): void
{
    run(dockerize('php artisan event:cache'));
}

#[AsTask(description: 'Restart queue workers')]
function laravel_queue_restart(): void
{
    run(dockerize('php artisan queue:restart'));
}

#[AsTask(description: 'Listen to the queue (foreground)')]
function laravel_queue_listen(): void
{
    run(dockerize('php artisan queue:listen'));
}

#[AsTask(description: 'Run scheduler once')]
function laravel_schedule_run(): void
{
    run(dockerize('php artisan schedule:run'));
}

#[AsTask(description: 'Create storage symlink')]
function laravel_storage_link(): void
{
    run(dockerize('php artisan storage:link'));
}

#[AsTask(description: 'Open Laravel Tinker shell')]
function laravel_tinker(): void
{
    run(dockerize('php artisan tinker'));
}
