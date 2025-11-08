<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

#[AsTask(description: 'Install dependencies and prepare Shopware')]
function shopware_setup(): void
{
    run(dockerize('composer install'));
    run(dockerize('bin/console system:install --create-database --basic-setup --force')); // Shopware >= 6
}

#[AsTask(description: 'Rebuild cache and indexes')]
function shopware_build(): void
{
    run(dockerize('bin/console cache:clear'));
    run(dockerize('bin/console dal:refresh:index')); // data abstraction layer
}

#[AsTask(description: 'Run PHP tests (PHPUnit)')]
function shopware_test(): void
{
    $cmd = 'vendor/bin/phpunit';
    run(dockerize($cmd));
}
