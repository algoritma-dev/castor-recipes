<?php

declare(strict_types=1);

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

/**
 * Build MySQL CLI auth/connection flags from environment values.
 */
function mysql_flags(?string $user = null, ?string $pass = null, ?string $host = null, ?string $port = null): string
{
    $user ??= (string) env_value('DB_USER');
    $pass ??= (string) env_value('DB_PASS');
    $host ??= (string) env_value('DB_HOST', '127.0.0.1');
    $port ??= (string) env_value('DB_PORT', '3306');

    $flags = [];
    if ($user !== '') {
        $flags[] = '-u ' . $user;
    }
    if ($pass !== '') {
        // Using long option with equals to avoid interactive prompt and spacing issues
        $flags[] = '--password=' . $pass;
    }
    if ($host !== '') {
        $flags[] = '--host=' . $host;
    }
    if ($port !== '') {
        $flags[] = '--port=' . $port;
    }

    return implode(' ', $flags);
}

#[AsTask(description: 'Drop the database', namespace: 'mysql')]
function db_drop(?string $user = null, ?string $dbName = null): void
{
    $user ??= (string) env_value('DB_USER');
    $dbName ??= (string) env_value('DB_NAME');
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    set_env('DOCKER_SERVICE', $dbService);

    $flags = mysql_flags($user);
    run(dockerize(sprintf('mysql %s -e \"DROP DATABASE IF EXISTS \`%s\`\"', $flags, $dbName)));

    restore_env('DOCKER_SERVICE');
}

#[AsTask(description: 'Create the database', namespace: 'mysql')]
function db_create(?string $user = null, ?string $dbName = null): void
{
    $user ??= (string) env_value('DB_USER');
    $dbName ??= (string) env_value('DB_NAME');
    $charset = (string) env_value('DB_CHARSET', 'utf8mb4');
    $collation = (string) env_value('DB_COLLATION', 'utf8mb4_unicode_ci');
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    set_env('DOCKER_SERVICE', $dbService);

    $flags = mysql_flags($user);
    run(dockerize(sprintf('mysql %s -e \"CREATE DATABASE \`%s\` CHARACTER SET %s COLLATE %s\"', $flags, $dbName, $charset, $collation)));

    restore_env('DOCKER_SERVICE');
}

#[AsTask(description: 'Restore database from dump file', namespace: 'mysql')]
function db_restore(#[AsArgument]string $dump, ?string $user = null, ?string $dbName = null): void
{
    $user ??= (string) env_value('DB_USER');
    $dbName ??= (string) env_value('DB_NAME');
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    set_env('DOCKER_SERVICE', $dbService);

    db_drop($user);
    db_create($user);

    $flags = mysql_flags($user);
    // Use cat | mysql to support gzcat/zcat upstream if needed by user
    run(dockerize(sprintf('cat %s | mysql %s %s', $dump, $flags, $dbName)));

    restore_env('DOCKER_SERVICE');
}

#[AsTask(name: 'dbbackup', description: 'Backup the database', namespace: 'mysql')]
function db_backup(?string $user = null, ?string $dbName = null): void
{
    $user ??= (string) env_value('DB_USER');
    $dbName ??= (string) env_value('DB_NAME');
    $dumpfile = date('Ymd') . '_' . (string) env_value('DB_NAME') . '.sql';
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    set_env('DOCKER_SERVICE', $dbService);

    $flags = mysql_flags($user);
    $gzipAvailable = trim(\Castor\capture(dockerize('which gzip'))) !== '';

    if ($gzipAvailable) {
        run(dockerize(sprintf('mysqldump %s %s \| gzip \> %s.gz', $flags, $dbName, $dumpfile)));
    } else {
        run(dockerize(sprintf('mysqldump %s %s \> %s', $flags, $dbName, $dumpfile)));
    }
    restore_env('DOCKER_SERVICE');
}

#[AsTask(name: 'db-tune', description: 'Tune database performance', namespace: 'mysql')]
function db_tune(?string $dbHost = null, ?string $user = null, ?string $dbPass = null, ?string $dbName = null): void
{
    $dbHost ??= (string) env_value('DB_HOST');
    $user ??= (string) env_value('DB_USER');
    $dbPass ??= (string) env_value('DB_PASS');
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    set_env('DOCKER_SERVICE', $dbService);

    run(dockerize(sprintf(
        '/usr/local/bin/mysqltuner.pl --host=%s --user=%s %s',
        $dbHost,
        $user,
        $dbPass !== '' ? '--pass=' . $dbPass : ''
    )));

    restore_env('DOCKER_SERVICE');
}
