<?php

use Castor\Attribute\AsTask;

use function Castor\run;
use function Castor\io;

#[AsTask(name: 'enable', namespace: 'xdebug', description: 'Enable Xdebug')]
function xdebug_enable(string $fpmServiceName = 'php-fpm', string $webServerServiceName = 'webserver'): void
{
    set_env('DOCKER_SERVICE', $fpmServiceName);
    $iniPath = '/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini';

    try{
        run(dockerize(sprintf('sudo mv %s.disabled %s', $iniPath, $iniPath)));
    } catch (\Throwable $e) {
        io()->warning('Xdebug is already disabled');
        return;
    }

    if(env_value('CASTOR_DOCKER') === 'true' || env_value('CASTOR_DOCKER') !== '1') {
        docker_compose_restart($fpmServiceName);
        docker_compose_restart($webServerServiceName);
    }

    restore_env('DOCKER_SERVICE');

    io()->success('Xdebug is enabled. Run `castor xdebug:disable` to disable it again.');
}

#[AsTask(name: 'disable', namespace: 'xdebug', description: 'Disable Xdebug')]
function xdebug_disable(string $fpmServiceName = 'php-fpm', string $webServerServiceName = 'webserver'): void
{
    set_env('DOCKER_SERVICE', $fpmServiceName);
    $iniPath = '/usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini';

    try {
        run(dockerize(sprintf('sudo mv %s %s.disabled', $iniPath, $iniPath)));
    } catch (\Throwable $e) {
        io()->warning('Xdebug is already disabled');
        return;
    }

    if(env_value('CASTOR_DOCKER') === 'true' || env_value('CASTOR_DOCKER') !== '1') {
        docker_compose_restart($fpmServiceName);
        docker_compose_restart($webServerServiceName);
    }

    restore_env('DOCKER_SERVICE');

    io()->success('Xdebug is disabled. Run `castor xdebug:enable` to enable it again.');
}
