<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

#[AsTask(name: 'containers-remove', description: 'Remove all containers (running and stopped)', namespace: 'docker')]
function docker_containers_remove(): void
{
    run('docker rm -f $(docker ps -a -q)');
}

#[AsTask(name: 'ps', description: 'Show the compose list of running containers', namespace: 'docker')]
function docker_ps(): void
{
    run('docker compose ps');
}

#[AsTask(name: 'composer-start', description: 'Restart compose services', namespace: 'docker')]
function docker_compose_restart(string $services = '', string $args = ''): void
{
    run(sprintf('docker compose restart %s %s', $services, $args));
}

#[AsTask(name: 'composer-up', description: 'Up docker compose services', namespace: 'docker')]
function docker_compose_up(bool $rm = true, bool $build = false, bool $detach = true): void
{
    $command = 'docker compose up';

    if ($rm) {
        docker_containers_remove();
    }

    if ($build) {
        $command .= ' --build';
    }

    if ($detach) {
        $command .= ' -d';
    }

    run($command);
}


#[AsTask(name: 'compose-down', description: 'Down docker compose services', namespace: 'docker')]
function docker_compose_down(bool $volumes = false): void
{
    $command = 'docker compose down';

    if ($volumes) {
        $command .= ' --volumes';
    }

    run($command);
}

#[AsTask(name: 'compose-logs', description: 'Show log of a container', namespace: 'docker')]
function docker_compose_logs(bool $tail = true, string $container = ''): void
{
    $command = 'docker compose logs';

    if ($tail) {
        $command .= ' --tail 100';
    }

    if ($container !== '' && $container !== '0') {
        $command .= ' ' . $container;
    }

    run($command);
}
