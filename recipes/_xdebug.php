<?php

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(name: 'enable', namespace: 'xdebug', description: 'Enable Xdebug')]
function xdebug_enable(string $fpmServiceName = 'php-fpm', string $webServerServiceName = 'webserver'): void
{
    $hasXdebug = \Castor\capture(dockerize('php -m | grep xdebug'));
    $iniPath = '/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini';

    if ($hasXdebug === '' || $hasXdebug === '0') {
        run(dockerize(sprintf('sudo mv %s.disabled %s', $iniPath, $iniPath)));

        docker_compose_restart($fpmServiceName);
        docker_compose_restart($webServerServiceName);
    }
}

#[AsTask(name: 'disable', namespace: 'xdebug', description: 'Disable Xdebug')]
function xdebug_disable(string $fpmServiceName = 'php-fpm', string $webServerServiceName = 'webserver'): void
{
    $hasXdebug = \Castor\capture(dockerize('php -m | grep xdebug'));

    $iniPath = '/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini';

    if ($hasXdebug !== '' && $hasXdebug !== '0') {
        run(dockerize(sprintf('sudo mv %s %s.disabled', $iniPath, $iniPath)));

        docker_compose_restart($fpmServiceName);
        docker_compose_restart($webServerServiceName);
    }
}
