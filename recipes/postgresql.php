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
    run(dockerize(sprintf('psql -U %s -c "DROP DATABASE IF EXISTS %s"', $user, $dbName)));
}

#[AsTask(description: 'Create UUID extension', namespace: 'psql')]
function uuid_extension_create(?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    run(dockerize(sprintf('psql -U %s -c "\c %s" -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\""', $user, $dbName)));
}

#[AsTask(description: 'Create the database', namespace: 'psql')]
function db_create(?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    run(dockerize(sprintf('psql -U %s -c "CREATE DATABASE %s"', $user, $dbName)));
    uuid_extension_create($user, $dbName);
}

#[AsTask(description: 'Restore database from dump file', namespace: 'psql')]
function db_restore(string $dump, ?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    db_drop($user);
    db_create($user);
    run(dockerize(sprintf('cat %s | psql -U %s -d %s', $dump, $user, $dbName)));
}

#[AsTask(name: 'dbbackup', description: 'Backup the database', namespace: 'psql')]
function db_backup(?string $user = null, ?string $dbName = null): void
{
    $user ??= env_value('DB_USER');
    $dbName ??= env_value('DB_NAME');
    $dumpfile = date('Ymd') . '_' . env_value('DB_NAME') . '.sql';
    run(dockerize(sprintf('pg_dump -U %s %s > %s', $user, $dbName, $dumpfile)));
}

#[AsTask(name: 'db-tune', description: 'Tune database performance', namespace: 'psql')]
function db_tune(?string $dbHost = null, ?string $user = null, ?string $dbPass = null, ?string $dbName = null): void
{
    $dbHost ??= env_value('DB_HOST');
    $user ??= env_value('DB_USER');
    $dbPass ??= env_value('DB_PASS');
    $dbName ??= env_value('DB_NAME');
    run(dockerize(sprintf(
        '/usr/local/bin/postgresqltuner.pl --host=%s --database=%s --user=%s --password=%s',
        $dbHost,
        $dbName,
        $user,
        $dbPass
    )));
}
