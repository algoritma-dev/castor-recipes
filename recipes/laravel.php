<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

function artisan_bin(): string
{
    return 'artisan';
}

#[AsTask(description: 'Proxy to artisan with ARGS', namespace: 'laravel', aliases: ['la'])]
function artisan(string $args = ''): void
{
    run(dockerize(sprintf('%s %s %s', php(), artisan_bin(), $args)));
}

#[AsTask(description: 'Install Composer dependencies', namespace: 'laravel')]
function install(): void
{
    composer_install();
}

#[AsTask(description: 'Run migrations and seeders', namespace: 'laravel')]
function migrate_seed(): void
{
    migrate();
    seed();
}

#[AsTask(description: 'Clear and rebuild cache', namespace: 'laravel', aliases: ['lcc'])]
function cache(): void
{
    artisan('optimize:clear');
    artisan('optimize');
}

#[AsTask(description: 'Run tests (Pest/PHPUnit)', namespace: 'laravel', aliases: ['lt'])]
function test(string $args = ''): void
{
    $cmd = file_exists('vendor/bin/pest') ? 'vendor/bin/pest' : phpunit_bin();
    run(dockerize(sprintf('%s %s', $cmd, $args)));
}

#[AsTask(description: 'Start the queue worker in background (if not in Docker, use supervisord)', namespace: 'laravel')]
function queue(): void
{
    artisan('queue:work --tries=3 --timeout=120');
}

#[AsTask(description: 'Start Laravel development server', namespace: 'laravel', aliases: ['ls'])]
function serve(): void
{
    artisan('serve');
}

#[AsTask(description: 'Run database migrations', namespace: 'laravel', aliases: ['lm'])]
function migrate(): void
{
    artisan('migrate --force');
}

#[AsTask(description: 'Run database seeders', namespace: 'laravel')]
function seed(): void
{
    artisan('db:seed --force');
}

#[AsTask(description: 'Fresh migrate (drops all tables and re-runs all migrations)', namespace: 'laravel')]
function migrate_fresh(): void
{
    artisan('migrate:fresh --force');
}

#[AsTask(description: 'Generate application key', namespace: 'laravel')]
function key_generate(): void
{
    artisan('key:generate');
}

#[AsTask(description: 'Clear all caches (app, route, config, view)', namespace: 'laravel')]
function cache_clear_all(): void
{
    artisan('optimize:clear');
}

#[AsTask(description: 'Cache configuration', namespace: 'laravel')]
function config_cache(): void
{
    artisan('config:cache');
}

#[AsTask(description: 'Cache routes', namespace: 'laravel')]
function route_cache(): void
{
    artisan('route:cache');
}

#[AsTask(description: 'Cache events', namespace: 'laravel')]
function event_cache(): void
{
    artisan('event:cache');
}

#[AsTask(description: 'Restart queue workers', namespace: 'laravel')]
function queue_restart(): void
{
    artisan('queue:restart');
}

#[AsTask(description: 'Listen to the queue (foreground)', namespace: 'laravel')]
function queue_listen(): void
{
    artisan('queue:listen');
}

#[AsTask(description: 'Run scheduler once', namespace: 'laravel')]
function schedule_run(): void
{
    artisan('schedule:run');
}

#[AsTask(description: 'Create storage symlink', namespace: 'laravel')]
function storage_link(): void
{
    artisan('storage:link');
}

#[AsTask(description: 'Open Laravel Tinker shell', namespace: 'laravel')]
function tinker(): void
{
    artisan('tinker');
}

#[AsTask(description: 'Tail Laravel logs (env: LARAVEL_LOG_FILE, LARAVEL_LOG_LINES)', namespace: 'laravel')]
function logs_tail(string $file = 'storage/logs/laravel.log', string $lines = '200'): void
{
    run(dockerize(sprintf('tail -n %s -f %s', $lines, $file)));
}

#[AsTask(description: 'Project setup (composite): composer install, key, migrate, seed, storage link, cache', namespace: 'laravel')]
function setup(bool $seed = true): void
{
    install();
    key_generate();
    migrate();
    if ($seed) {
        seed();
    }
    storage_link();
    cache();
}

#[AsTask(description: 'CI helper (cache + tests) - composite', namespace: 'laravel')]
function ci(): void
{
    cache();
    test();
}
