<?php

declare(strict_types=1);

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

function artisan_bin(): string
{
    return 'artisan';
}

#[AsTask(namespace: 'laravel', description: 'Proxy to artisan with ARGS', aliases: ['la'])]
function artisan(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s %s', php(), artisan_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'laravel', description: 'Install Composer dependencies')]
function install(): void
{
    composer_install();
}

#[AsTask(namespace: 'laravel', description: 'Run migrations and seeders')]
function migrate_seed(): void
{
    migrate();
    seed();
}

#[AsTask(namespace: 'laravel', description: 'Clear and rebuild cache', aliases: ['lcc'])]
function cache(): void
{
    artisan('optimize:clear');
    artisan('optimize');
}

#[AsTask(namespace: 'laravel', description: 'Run tests (Pest/PHPUnit)', aliases: ['lt'])]
function test(
    #[AsRawTokens]
    array $args = []
): void {
    $cmd = file_exists('vendor/bin/pest') ? 'vendor/bin/pest' : phpunit_bin();
    run(dockerize(\sprintf('%s %s', $cmd, implode(' ', $args))));
}

#[AsTask(namespace: 'laravel', description: 'Start the queue worker in background (if not in Docker, use supervisord)')]
function queue(): void
{
    artisan('queue:work --tries=3 --timeout=120');
}

#[AsTask(namespace: 'laravel', description: 'Start Laravel development server', aliases: ['ls'])]
function serve(): void
{
    artisan('serve');
}

#[AsTask(namespace: 'laravel', description: 'Run database migrations', aliases: ['lm'])]
function migrate(): void
{
    artisan('migrate --force');
}

#[AsTask(namespace: 'laravel', description: 'Run database seeders')]
function seed(): void
{
    artisan('db:seed --force');
}

#[AsTask(namespace: 'laravel', description: 'Fresh migrate (drops all tables and re-runs all migrations)')]
function migrate_fresh(): void
{
    artisan('migrate:fresh --force');
}

#[AsTask(namespace: 'laravel', description: 'Generate application key')]
function key_generate(): void
{
    artisan('key:generate');
}

#[AsTask(namespace: 'laravel', description: 'Clear all caches (app, route, config, view)')]
function cache_clear_all(): void
{
    artisan('optimize:clear');
}

#[AsTask(namespace: 'laravel', description: 'Cache configuration')]
function config_cache(): void
{
    artisan('config:cache');
}

#[AsTask(namespace: 'laravel', description: 'Cache routes')]
function route_cache(): void
{
    artisan('route:cache');
}

#[AsTask(namespace: 'laravel', description: 'Cache events')]
function event_cache(): void
{
    artisan('event:cache');
}

#[AsTask(namespace: 'laravel', description: 'Restart queue workers')]
function queue_restart(): void
{
    artisan('queue:restart');
}

#[AsTask(namespace: 'laravel', description: 'Listen to the queue (foreground)')]
function queue_listen(): void
{
    artisan('queue:listen');
}

#[AsTask(namespace: 'laravel', description: 'Run scheduler once')]
function schedule_run(): void
{
    artisan('schedule:run');
}

#[AsTask(namespace: 'laravel', description: 'Create storage symlink')]
function storage_link(): void
{
    artisan('storage:link');
}

#[AsTask(namespace: 'laravel', description: 'Open Laravel Tinker shell')]
function tinker(): void
{
    artisan('tinker');
}

#[AsTask(namespace: 'laravel', description: 'Tail Laravel logs (env: LARAVEL_LOG_FILE, LARAVEL_LOG_LINES)')]
function logs_tail(string $file = 'storage/logs/laravel.log', string $lines = '200'): void
{
    run(dockerize(\sprintf('tail -n %s -f %s', $lines, $file)));
}

#[AsTask(namespace: 'laravel', description: 'Project setup (composite): composer install, key, migrate, seed, storage link, cache')]
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

#[AsTask(namespace: 'laravel', description: 'CI helper (cache + tests) - composite')]
function ci(): void
{
    cache();
    test();
}
