<?php

declare(strict_types=1);

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\run;

require_once __DIR__ . '/_common.php';

/**
 * Helper to get database environment value with proper environment detection.
 */
function db_env_value(string $key, mixed $default = null, ?string $environment = null): mixed
{
    return env_value($key, $default, null, $environment);
}

/**
 * Build MySQL CLI auth/connection flags from environment values.
 */
function mysql_flags(?string $user = null, ?string $pass = null, ?string $host = null, ?string $port = null, ?string $environment = null): string
{
    $user ??= (string) db_env_value('DB_USER', null, $environment);
    $pass ??= (string) db_env_value('DB_PASS', null, $environment);
    $host ??= (string) db_env_value('DB_HOST', '127.0.0.1', $environment);
    $port ??= (string) db_env_value('DB_PORT', '3306', $environment);

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
function db_drop(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= (string) db_env_value('DB_USER', null, $env);
    $dbName ??= (string) db_env_value('DB_NAME', null, $env);
    $dbService = (string) db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    $flags = mysql_flags($user, null, null, null, $env);
    run(dockerize(\sprintf('mysql %s -e \"DROP DATABASE IF EXISTS \`%s\`\"', $flags, $dbName), $dbService, '/'));
}

#[AsTask(namespace: 'mysql', description: 'Create the database')]
function db_create(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= (string) db_env_value('DB_USER', null, $env);
    $dbName ??= (string) db_env_value('DB_NAME', null, $env);
    $charset = (string) db_env_value('DB_CHARSET', 'utf8mb4', $env);
    $collation = (string) db_env_value('DB_COLLATION', 'utf8mb4_unicode_ci', $env);
    $dbService = (string) db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    $flags = mysql_flags($user, null, null, null, $env);
    run(dockerize(\sprintf('mysql %s -e \"CREATE DATABASE \`%s\` CHARACTER SET %s COLLATE %s\"', $flags, $dbName, $charset, $collation), $dbService, '/'));
}

#[AsTask(namespace: 'mysql', description: 'Restore database from dump file')]
function db_restore(
    #[AsArgument] string $dump,
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= (string) db_env_value('DB_USER', null, $env);
    $dbName ??= (string) db_env_value('DB_NAME', null, $env);
    $dbService = (string) db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    db_drop($env, $user);
    db_create($env, $user);

    $flags = mysql_flags($user, null, null, null, $env);
    // Use cat | mysql to support gzcat/zcat upstream if needed by user
    run(\sprintf('cat %s | ', $dump) . dockerize(\sprintf('mysql %s %s', $flags, $dbName), $dbService, '/', true));
}

#[AsTask(name: 'dbbackup', namespace: 'mysql', description: 'Backup the database')]
function db_backup(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $user = null,
    ?string $dbName = null
): void {
    $user ??= (string) db_env_value('DB_USER', null, $env);
    $dbName ??= (string) db_env_value('DB_NAME', null, $env);
    $dumpfile = date('Ymd') . '_' . db_env_value('DB_NAME', null, $env) . '.sql';
    $dbService = (string) db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    $flags = mysql_flags($user, null, null, null, $env);
    $gzipAvailable = trim(capture(dockerize('which gzip'))) !== '';

    if ($gzipAvailable) {
        run(dockerize(\sprintf('mysqldump %s %s \| gzip \> %s.gz', $flags, $dbName, $dumpfile), $dbService, '/'));
    } else {
        run(dockerize(\sprintf('mysqldump %s %s \> %s', $flags, $dbName, $dumpfile), $dbService, '/'));
    }
}

#[AsTask(name: 'db-tune', namespace: 'mysql', description: 'Tune database performance')]
function db_tune(
    #[AsOption(description: 'Environment to use (e.g., test, prod). Defaults to local')]
    ?string $env = null,
    ?string $dbHost = null,
    ?string $user = null,
    ?string $dbPass = null,
    ?string $dbName = null
): void {
    $dbHost ??= (string) db_env_value('DB_HOST', null, $env);
    $user ??= (string) db_env_value('DB_USER', null, $env);
    $dbPass ??= (string) db_env_value('DB_PASS', null, $env);
    $dbService = (string) db_env_value('DOCKER_DB_SERVICE', 'database', $env);

    run(dockerize(\sprintf(
        '/usr/local/bin/mysqltuner.pl --host=%s --user=%s %s',
        $dbHost,
        $user,
        $dbPass !== '' ? '--pass=' . $dbPass : ''
    ), $dbService, '/'));
}
