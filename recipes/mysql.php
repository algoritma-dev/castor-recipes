<?php

declare(strict_types=1);

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\capture;
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

#[AsTask(namespace: 'mysql', description: 'Drop the database')]
function db_drop(?string $user = null, ?string $dbName = null): void
{
    $user ??= (string) env_value('DB_USER');
    $dbName ??= (string) env_value('DB_NAME');
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    $flags = mysql_flags($user);
    run(dockerize(\sprintf('mysql %s -e \"DROP DATABASE IF EXISTS \`%s\`\"', $flags, $dbName), $dbService, '/'));
}

#[AsTask(namespace: 'mysql', description: 'Create the database')]
function db_create(?string $user = null, ?string $dbName = null): void
{
    $user ??= (string) env_value('DB_USER');
    $dbName ??= (string) env_value('DB_NAME');
    $charset = (string) env_value('DB_CHARSET', 'utf8mb4');
    $collation = (string) env_value('DB_COLLATION', 'utf8mb4_unicode_ci');
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    $flags = mysql_flags($user);
    run(dockerize(\sprintf('mysql %s -e \"CREATE DATABASE \`%s\` CHARACTER SET %s COLLATE %s\"', $flags, $dbName, $charset, $collation), $dbService, '/'));
}

#[AsTask(namespace: 'mysql', description: 'Restore database from dump file')]
function db_restore(#[AsArgument] string $dump, ?string $user = null, ?string $dbName = null): void
{
    $user ??= (string) env_value('DB_USER');
    $dbName ??= (string) env_value('DB_NAME');
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    db_drop($user);
    db_create($user);

    $flags = mysql_flags($user);
    // Use cat | mysql to support gzcat/zcat upstream if needed by user
    run(\sprintf('cat %s | ', $dump) . dockerize(\sprintf('mysql %s %s', $flags, $dbName), $dbService, '/', true));
}

#[AsTask(name: 'dbbackup', namespace: 'mysql', description: 'Backup the database')]
function db_backup(?string $user = null, ?string $dbName = null): void
{
    $user ??= (string) env_value('DB_USER');
    $dbName ??= (string) env_value('DB_NAME');
    $dumpfile = date('Ymd') . '_' . env_value('DB_NAME') . '.sql';
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    $flags = mysql_flags($user);
    $gzipAvailable = trim(capture(dockerize('which gzip'))) !== '';

    if ($gzipAvailable) {
        run(dockerize(\sprintf('mysqldump %s %s \| gzip \> %s.gz', $flags, $dbName, $dumpfile), $dbService, '/'));
    } else {
        run(dockerize(\sprintf('mysqldump %s %s \> %s', $flags, $dbName, $dumpfile), $dbService, '/'));
    }
}

#[AsTask(name: 'db-tune', namespace: 'mysql', description: 'Tune database performance')]
function db_tune(?string $dbHost = null, ?string $user = null, ?string $dbPass = null, ?string $dbName = null): void
{
    $dbHost ??= (string) env_value('DB_HOST');
    $user ??= (string) env_value('DB_USER');
    $dbPass ??= (string) env_value('DB_PASS');
    $dbService = (string) env_value('DOCKER_DB_SERVICE', 'database');

    run(dockerize(\sprintf(
        '/usr/local/bin/mysqltuner.pl --host=%s --user=%s %s',
        $dbHost,
        $user,
        $dbPass !== '' ? '--pass=' . $dbPass : ''
    ), $dbService, '/'));
}
