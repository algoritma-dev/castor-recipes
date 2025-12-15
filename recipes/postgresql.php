<?php

declare(strict_types=1);

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\run;

require_once __DIR__ . '/_common.php';

// Reuse the db_env_value function from mysql.php
if (! \function_exists('db_env_value')) {
    /**
     * Helper to get database environment value with proper environment detection.
     */
    function db_env_value(string $key, mixed $default = null, ?string $environment = null): mixed
    {
        return env_value($key, $default, null, $environment);
    }
}

#[AsTask(namespace: 'psql', description: 'Drop the database')]
function db_drop(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= db_env_value('DB_USER', null, $env);
    $dbName ??= db_env_value('DB_NAME', null, $env);
    $dbService ??= db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    run(dockerize(\sprintf('psql -U %s -c "DROP DATABASE IF EXISTS %s"', $user, $dbName), $dbService, '/'));
}

#[AsTask(namespace: 'psql', description: 'Create UUID extension')]
function uuid_extension_create(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= db_env_value('DB_USER', null, $env);
    $dbName ??= db_env_value('DB_NAME', null, $env);
    $dbService ??= db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    run(dockerize(\sprintf('psql -U %s -c "\c %s" -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\""', $user, $dbName), $dbService, '/'));
}

#[AsTask(namespace: 'psql', description: 'Create the database')]
function db_create(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= db_env_value('DB_USER', null, $env);
    $dbName ??= db_env_value('DB_NAME', null, $env);
    $dbService ??= db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    run(dockerize(\sprintf('psql -U %s -c "CREATE DATABASE %s"', $user, $dbName), $dbService, '/'));
    uuid_extension_create($env, $user, $dbName);
}

#[AsTask(namespace: 'psql', description: 'Restore database from dump file')]
function db_restore(
    #[AsArgument] string $dump,
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= db_env_value('DB_USER', null, $env);
    $dbName ??= db_env_value('DB_NAME', null, $env);
    $dbService ??= db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    db_drop($env, $user);
    db_create($env, $user);
    run(\sprintf('cat %s | ', $dump) . dockerize(\sprintf('psql -U %s -d %s', $user, $dbName), $dbService, '/', true));
}

#[AsTask(name: 'dbbackup', namespace: 'psql', description: 'Backup the database')]
function db_backup(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= db_env_value('DB_USER', null, $env);
    $dbName ??= db_env_value('DB_NAME', null, $env);
    $dumpfile = date('Ymd') . '_' . db_env_value('DB_NAME', null, $env) . '.sql';
    $dbService ??= db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    $gzipAvailable = trim(capture(dockerize('which gzip'))) !== '';

    if ($gzipAvailable) {
        run(dockerize(\sprintf('pg_dump -U %s %s \| gzip \> %s.gz', $user, $dbName, $dumpfile), $dbService, '/'));
    } else {
        run(dockerize(\sprintf('pg_dump -U %s %s \> %s', $user, $dbName, $dumpfile), $dbService, '/'));
    }
}

#[AsTask(name: 'db-tune', namespace: 'psql', description: 'Tune database performance')]
function db_tune(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $dbHost = null,
    ?string $user = null,
    ?string $dbPass = null,
    ?string $dbName = null
): void {
    $dbHost ??= db_env_value('DB_HOST', null, $env);
    $user ??= db_env_value('DB_USER', null, $env);
    $dbPass ??= db_env_value('DB_PASS', null, $env);
    $dbName ??= db_env_value('DB_NAME', null, $env);
    $dbService ??= db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    run(dockerize(\sprintf(
        '/usr/local/bin/postgresqltuner.pl --host=%s --database=%s --user=%s --password=%s',
        $dbHost,
        $dbName,
        $user,
        $dbPass
    ), $dbService, '/'));
}
