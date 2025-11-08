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
