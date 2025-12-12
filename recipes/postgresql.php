<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

#[AsTask(description: 'Drop the database', namespace: 'psql')]
function db_drop(?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    $dbService ??= env_value('DOCKER_DB_SERVICE', 'database');

    run(dockerize(sprintf('psql -U %s -c "DROP DATABASE IF EXISTS %s"', $user, $dbName), $dbService, '/'));
}

#[AsTask(description: 'Create UUID extension', namespace: 'psql')]
function uuid_extension_create(?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    $dbService ??= env_value('DOCKER_DB_SERVICE', 'database');

    run(dockerize(sprintf('psql -U %s -c "\c %s" -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\""', $user, $dbName), $dbService, '/'));
}

#[AsTask(description: 'Create the database', namespace: 'psql')]
function db_create(?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    $dbService ??= env_value('DOCKER_DB_SERVICE', 'database');

    run(dockerize(sprintf('psql -U %s -c "CREATE DATABASE %s"', $user, $dbName), $dbService, '/'));
    uuid_extension_create($user, $dbName);
}

#[AsTask(description: 'Restore database from dump file', namespace: 'psql')]
function db_restore(string $dump, ?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    $dbService ??= env_value('DOCKER_DB_SERVICE', 'database');

    db_drop($user);
    db_create($user);
    run(sprintf('cat %s | ', $dump) . dockerize(sprintf('psql -U %s -d %s', $user, $dbName), $dbService, '/', true));
}

#[AsTask(name: 'dbbackup', description: 'Backup the database', namespace: 'psql')]
function db_backup(?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    $dumpfile = date('Ymd') . '_' . env_value('DB_NAME') . '.sql';
    $dbService ??= env_value('DOCKER_DB_SERVICE', 'database');

    $gzipAvailable = trim(\Castor\capture(dockerize('which gzip'))) !== '';

    if ($gzipAvailable) {
        run(dockerize(sprintf('pg_dump -U %s %s \| gzip \> %s.gz', $user, $dbName, $dumpfile), $dbService, '/'));

    } else {
        run(dockerize(sprintf('pg_dump -U %s %s \> %s', $user, $dbName, $dumpfile), $dbService, '/'));
    }
}

#[AsTask(name: 'db-tune', description: 'Tune database performance', namespace: 'psql')]
function db_tune(?string $dbHost = null, ?string $user = null, ?string $dbPass = null, ?string $dbName = null): void
{
    $dbHost ??= env_value('DB_HOST');
    $user ??= env_value('DB_USER');
    $dbPass ??= env_value('DB_PASS');
    $dbName ??= env_value('DB_NAME');
    $dbService ??= env_value('DOCKER_DB_SERVICE', 'database');

    run(dockerize(sprintf(
        '/usr/local/bin/postgresqltuner.pl --host=%s --database=%s --user=%s --password=%s',
        $dbHost,
        $dbName,
        $user,
        $dbPass
    ), $dbService, '/'));
}
