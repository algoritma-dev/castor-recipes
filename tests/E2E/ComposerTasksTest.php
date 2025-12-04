<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\E2E;

require_once __DIR__ . '/Support/Proc.php';

use Algoritma\CastorRecipes\Tests\E2E\Support\Proc;
use PHPUnit\Framework\TestCase;
use function exec;

final class ComposerTasksTest extends TestCase
{
    public function testComposerInstallUsesConfiguredBinAndArgs(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-composer-' . uniqid('', true) . '.log';

        $env = [
            'COMPOSER_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'composer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'composer:install',
            '--args=--prefer-dist',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('composer install --prefer-dist', $log);
    }

    public function testComposerRequireWithDevFlagAndPackage(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-composer-' . uniqid('', true) . '.log';

        $env = [
            'COMPOSER_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'composer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'composer:require',
            '--dev',
            '--args=vendor/package:^1.2',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($shimLog) ?: '';
        // Expect the --dev flag to be present before the package constraint
        self::assertStringContainsString('composer require --dev vendor/package:^1.2', $log);
    }

    public function testComposerUpdatePassesThroughArgs(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-composer-' . uniqid('', true) . '.log';

        $env = [
            'COMPOSER_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'composer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'composer:update',
            '--args=--with-all-dependencies',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('composer update --with-all-dependencies', $log);
    }

    public function testComposerRunScriptNames(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-composer-' . uniqid('', true) . '.log';

        $env = [
            'COMPOSER_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'composer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'composer:run',
            'lint',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('composer run lint', $log);
    }

    public function testComposerInstallIsDockerizedWhenEnabled(): void
    {
        if (getenv('CI')) {
            self::markTestSkipped('Skipping E2E Docker test in CI environment due to filesystem isolation.');
        }

        $tempLogDir = sys_get_temp_dir() . '/castor-recipes-logs-' . uniqid('', true);
        if (!mkdir($tempLogDir) && !is_dir($tempLogDir)) {
            self::fail('Unable to create temp log directory');
        }

        $toolShim = __DIR__ . '/fixtures/tool-shim.php';
        $dockerShimSrc = __DIR__ . '/fixtures/docker-shim.php';

        $binDir = $tempLogDir . '/bin'; // Use the new temp log dir for bin
        if (! mkdir($binDir) && ! is_dir($binDir)) {
            self::fail('Unable to create temp bin dir');
        }
        $dockerShim = $binDir . '/docker';
        $shimContent = file_get_contents($dockerShimSrc);
        if ($shimContent === false) {
            self::fail('Unable to read docker shim source');
        }
        file_put_contents($dockerShim, $shimContent);
        @chmod($dockerShim, 0o755);

        $shimLog = $tempLogDir . '/castor-recipes-shim-' . uniqid('', true) . '.log';
        $dockerLog = $tempLogDir . '/castor-recipes-docker-' . uniqid('', true) . '.log';

        $env = [
            'COMPOSER_BIN' => \PHP_BINARY . ' ' . $toolShim,
            'SHIM_TOOL' => 'composer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '1',
            'DOCKER_SERVICE' => 'php',
            'DOCKER_COMPOSE_FILE' => 'docker-compose.yml',
            'SHIM_DOCKER_LOG' => $dockerLog,
            'PATH' => $binDir . \PATH_SEPARATOR . (getenv('PATH') ?: ''),
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'composer:install',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);

        self::assertFileExists($dockerLog, 'Docker shim log not created');
        $dockerLogContent = file_get_contents($dockerLog) ?: '';
        self::assertStringContainsString('docker compose -f docker-compose.yml run --rm --workdir /app php', $dockerLogContent);

        self::assertFileExists($shimLog, 'Tool shim log not created');
        $shimLogContent = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('composer install', $shimLogContent);

        // Clean up
        exec('rm -rf ' . "$tempLogDir/*");
        exec('rm -rf ' . $binDir);
        exec('rm -rf ' . $tempLogDir);
    }
}
