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
