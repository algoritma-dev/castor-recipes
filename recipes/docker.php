<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

#[AsTask(name: 'containers-remove', namespace: 'docker', description: 'Remove all containers (running and stopped)')]
function docker_containers_remove(): void
{
    run('docker rm -f $(docker ps -a -q)');
}

#[AsTask(name: 'ps', namespace: 'docker', description: 'Show the compose list of running containers')]
function docker_ps(): void
{
    run('docker compose ps');
}

function docker_compose(string $command): string
{
    $compose = env_value('DOCKER_COMPOSE_FILE', 'docker-compose.yml');

    return \sprintf('docker compose -f %s %s', $compose, $command);
}

#[AsTask(name: 'composer-start', namespace: 'docker', description: 'Restart compose services')]
function docker_compose_restart(string $services = '', string $args = ''): void
{
    run(\sprintf('docker compose restart %s %s', $services, $args));
}

#[AsTask(name: 'composer-up', namespace: 'docker', description: 'Up docker compose services')]
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

#[AsTask(name: 'compose-down', namespace: 'docker', description: 'Down docker compose services')]
function docker_compose_down(bool $volumes = false): void
{
    $command = 'docker compose down';

    if ($volumes) {
        $command .= ' --volumes';
    }

    run($command);
}

#[AsTask(name: 'compose-logs', namespace: 'docker', description: 'Show log of a container')]
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
