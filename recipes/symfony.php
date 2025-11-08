<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

#[AsTask(description: 'Install Composer dependencies')]
function sf_install(): void
{
    run(dockerize('composer install'));
}

#[AsTask(description: 'Start Symfony local server')]
function sf_serve(): void
{
    run('symfony server:start -d');
}

#[AsTask(description: 'Run Doctrine migrations')]
function sf_migrate(): void
{
    run(dockerize('php bin/console doctrine:migrations:migrate --no-interaction'));
}

#[AsTask(description: 'Clear Symfony cache')]
function sf_cache_clear(): void
{
    run(dockerize('php bin/console cache:clear'));
}

#[AsTask(description: 'Run tests (PHPUnit)')]
function sf_test(): void
{
    run(dockerize('php bin/phpunit'));
}


#[AsTask(description: 'Generate Doctrine migration from changes')]
function sf_migrate_diff(): void
{
    run(dockerize('php bin/console doctrine:migrations:diff --no-interaction'));
}

#[AsTask(description: 'Create database')]
function sf_db_create(): void
{
    run(dockerize('php bin/console doctrine:database:create --if-not-exists'));
}

#[AsTask(description: 'Drop database')]
function sf_db_drop(): void
{
    run(dockerize('php bin/console doctrine:database:drop --force --if-exists'));
}

#[AsTask(description: 'Load Doctrine fixtures (if installed)')]
function sf_fixtures_load(): void
{
    run(dockerize('php bin/console doctrine:fixtures:load --no-interaction --purge-with-truncate'));
}

#[AsTask(description: 'Warm up Symfony cache')]
function sf_cache_warmup(): void
{
    run(dockerize('php bin/console cache:warmup'));
}

#[AsTask(description: 'Install assets (copy/symlink)')]
function sf_assets_install(): void
{
    run(dockerize('php bin/console assets:install --symlink --relative public'));
}

#[AsTask(description: 'Lint YAML files')]
function sf_lint_yaml(): void
{
    run(dockerize('php bin/console lint:yaml config --parse-tags'));
}

#[AsTask(description: 'Lint Twig templates')]
function sf_lint_twig(): void
{
    run(dockerize('php bin/console lint:twig templates'));
}

#[AsTask(description: 'Lint container')]
function sf_lint_container(): void
{
    run(dockerize('php bin/console lint:container'));
}

#[AsTask(description: 'Consume Messenger messages')]
function sf_messenger_consume(): void
{
    $transports = getenv('SF_TRANSPORTS') ?: 'async';
    run(dockerize(sprintf('php bin/console messenger:consume %s --time-limit=3600 --memory-limit=256M', escapeshellarg($transports))));
}

#[AsTask(description: 'Tail Symfony logs')]
function sf_logs_tail(): void
{
    $env = getenv('APP_ENV') ?: 'dev';
    $path = sprintf('var/log/%s.log', $env);
    run(dockerize(sprintf('tail -n 200 -f %s', escapeshellarg($path))));
}

#[AsTask(description: 'Proxy to bin/console (set ARGS env var)')]
function sf_console(): void
{
    $args = getenv('ARGS') ?: '';
    run(dockerize(sprintf('php bin/console %s', $args)));
}
