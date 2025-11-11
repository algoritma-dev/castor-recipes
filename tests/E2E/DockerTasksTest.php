<?php

declare(strict_types=1);

namespace CastorRecipes\Tests\E2E;

require_once __DIR__ . '/Support/Proc.php';

use CastorRecipes\Tests\E2E\Support\Proc;
use PHPUnit\Framework\TestCase;

final class DockerTasksTest extends TestCase
{
    public function testComposeUpRemovesContainersAndStartsDetached(): void
    {
        $env = $this->prepareDockerShimEnv();

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'docker:composer-up',
            '--rm',
            '--detach',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $dockerLog = $env['SHIM_DOCKER_LOG'];
        self::assertFileExists($dockerLog, 'Docker shim log not created');
        $log = file_get_contents($dockerLog) ?: '';
        // Our shim logs invocations separately; assert the sequence components
        self::assertStringContainsString('docker ps -a -q', $log);
        self::assertStringContainsString('docker rm -f', $log);
        self::assertStringContainsString('docker compose up -d', $log);
    }

    public function testComposeDownWithVolumes(): void
    {
        $env = $this->prepareDockerShimEnv();

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'docker:compose-down',
            '--volumes',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($env['SHIM_DOCKER_LOG']) ?: '';
        self::assertStringContainsString('docker compose down --volumes', $log);
    }

    public function testComposeLogsWithTailAndContainer(): void
    {
        $env = $this->prepareDockerShimEnv();

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'docker:compose-logs',
            '--tail',
            '--container=php',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($env['SHIM_DOCKER_LOG']) ?: '';
        self::assertStringContainsString('docker compose logs --tail 100 php', $log);
    }

    public function testPsAndRestart(): void
    {
        $env = $this->prepareDockerShimEnv();

        $procPs = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'docker:ps',
        ], $env, getcwd());
        self::assertSame(0, $procPs->exitCode, $procPs->stdout . "\n" . $procPs->stderr);

        $procRestart = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'docker:composer-start',
            '--services=php',
        ], $env, getcwd());
        self::assertSame(0, $procRestart->exitCode, $procRestart->stdout . "\n" . $procRestart->stderr);

        $log = file_get_contents($env['SHIM_DOCKER_LOG']) ?: '';
        self::assertStringContainsString('docker compose ps', $log);
        self::assertStringContainsString('docker compose restart php', $log);
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
