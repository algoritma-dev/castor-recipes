<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\E2E;

require_once __DIR__ . '/Support/Proc.php';

use Algoritma\CastorRecipes\Tests\E2E\Support\Proc;
use PHPUnit\Framework\TestCase;

final class MySqlTasksTest extends TestCase
{
    public function testDbCreateBuildsExpectedDockerAndMysqlCommand(): void
    {
        $env = $this->prepareDockerShimEnv() + [
            'CASTOR_DOCKER' => '1',
            'DOCKER_DB_SERVICE' => 'mysql',
            'DOCKER_COMPOSE_FILE' => 'docker-compose.yml',
            'DB_USER' => 'app',
            'DB_NAME' => 'appdb',
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATION' => 'utf8mb4_unicode_ci',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'mysql:db-create',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($env['SHIM_DOCKER_LOG']) ?: '';
        self::assertStringContainsString('docker compose -f docker-compose.yml run --rm --workdir /app mysql', $log);
        self::assertStringContainsString('mysql', $log);
        self::assertStringContainsString('CREATE DATABASE `appdb`', $log);
        self::assertStringContainsString('CHARACTER SET utf8mb4', $log);
        self::assertStringContainsString('COLLATE utf8mb4_unicode_ci', $log);
    }

    public function testDbDropBuildsExpectedDockerAndMysqlCommand(): void
    {
        $env = $this->prepareDockerShimEnv() + [
            'CASTOR_DOCKER' => '1',
            'DOCKER_DB_SERVICE' => 'mysql',
            'DOCKER_COMPOSE_FILE' => 'docker-compose.yml',
            'DB_USER' => 'app',
            'DB_NAME' => 'appdb',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'mysql:db-drop',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($env['SHIM_DOCKER_LOG']) ?: '';
        self::assertStringContainsString('docker compose -f docker-compose.yml run --rm --workdir /app mysql', $log);
        self::assertStringContainsString('mysql -u app --host=127.0.0.1 --port=3306 -e "DROP DATABASE IF EXISTS `appdb`"', $log);
    }

    public function testDbRestoreBuildsExpectedSequence(): void
    {
        $dump = sys_get_temp_dir() . '/dump-' . uniqid('', true) . '.sql';
        file_put_contents($dump, '-- dummy');

        $env = $this->prepareDockerShimEnv() + [
            'CASTOR_DOCKER' => '1',
            'DOCKER_DB_SERVICE' => 'mysql',
            'DOCKER_COMPOSE_FILE' => 'docker-compose.yml',
            'DB_USER' => 'app',
            'DB_NAME' => 'appdb',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'mysql:db-restore',
            $dump,
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($env['SHIM_DOCKER_LOG']) ?: '';
        // Drop, Create, then restore via cat | mysql
        self::assertStringContainsString('mysql -u app --host=127.0.0.1 --port=3306 -e "DROP DATABASE IF EXISTS `appdb`"', $log);
        self::assertStringContainsString('mysql -u app --host=127.0.0.1 --port=3306 -e "CREATE DATABASE `appdb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"', $log);
        self::assertStringContainsString('-T --workdir /app mysql mysql -u app --host=127.0.0.1 --port=3306 appdb', $log);
    }

    public function testDbBackupBuildsExpectedCommand(): void
    {
        $env = $this->prepareDockerShimEnv() + [
            'CASTOR_DOCKER' => '1',
            'DOCKER_DB_SERVICE' => 'mysql',
            'DOCKER_COMPOSE_FILE' => 'docker-compose.yml',
            'DB_USER' => 'app',
            'DB_NAME' => 'appdb',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'mysql:dbbackup',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($env['SHIM_DOCKER_LOG']) ?: '';
        self::assertStringContainsString('docker compose -f docker-compose.yml run --rm --workdir /app mysql', $log);
        self::assertStringContainsString(' mysqldump -u app --host=127.0.0.1 --port=3306 appdb > ' . date('Ymd') . '_appdb.sql', $log);
    }

    public function testDbTuneBuildsExpectedCommand(): void
    {
        $env = $this->prepareDockerShimEnv() + [
            'CASTOR_DOCKER' => '1',
            'DOCKER_DB_SERVICE' => 'mysql',
            'DOCKER_COMPOSE_FILE' => 'docker-compose.yml',
            'DB_HOST' => '127.0.0.1',
            'DB_USER' => 'app',
            'DB_PASS' => 'secret',
            'DB_NAME' => 'appdb',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'mysql:db-tune',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($env['SHIM_DOCKER_LOG']) ?: '';
        self::assertStringContainsString('/usr/local/bin/mysqltuner.pl --host=127.0.0.1 --user=app --pass=secret', $log);
    }

    /**
     * @return array{ SHIM_DOCKER_LOG: string, PATH: string }
     */
    private function prepareDockerShimEnv(): array
    {
        $binDir = sys_get_temp_dir() . '/castor-recipes-bin-' . uniqid('', true);
        if (! mkdir($binDir) && ! is_dir($binDir)) {
            self::fail('Unable to create temp bin dir');
        }
        $dockerShimSrc = __DIR__ . '/fixtures/docker-shim.php';
        $dockerShim = $binDir . '/docker';
        $shimContent = file_get_contents($dockerShimSrc);
        if ($shimContent === false) {
            self::fail('Unable to read docker shim source');
        }
        file_put_contents($dockerShim, $shimContent);
        @chmod($dockerShim, 0o755);

        $dockerLog = sys_get_temp_dir() . '/castor-recipes-docker-' . uniqid('', true) . '.log';

        return [
            'SHIM_DOCKER_LOG' => $dockerLog,
            'PATH' => $binDir . \PATH_SEPARATOR . (getenv('PATH') ?: ''),
        ];
    }
}
