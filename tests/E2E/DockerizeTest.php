<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\E2E;

require_once __DIR__ . '/Support/Proc.php';

use Algoritma\CastorRecipes\Tests\E2E\Support\Proc;
use PHPUnit\Framework\TestCase;

final class DockerizeTest extends TestCase
{
    public function testDockerWrapsQaPhpCsFixer(): void
    {
        if (getenv('CI')) {
            self::markTestSkipped('Skipping E2E Docker test in CI environment due to filesystem isolation.');
        }

        $tempLogDir = sys_get_temp_dir() . '/castor-recipes-logs-' . uniqid('', true);
        if (! mkdir($tempLogDir) && ! is_dir($tempLogDir)) {
            self::fail('Unable to create temp log directory');
        }

        $toolShim = __DIR__ . '/fixtures/tool-shim.php';
        $dockerShimSrc = __DIR__ . '/fixtures/docker-shim.php';

        // Prepare a temporary bin dir with a `docker` shim executable in PATH
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

        // Build env: enable docker, point to our docker shim via PATH, set service and compose file
        $env = [
            'PHPCSFIXER_BIN' => \PHP_BINARY . ' ' . $toolShim,
            'SHIM_TOOL' => 'php-cs-fixer',
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
            'qa:php-cs-fixer',
            '--dry-run',
            'src/Foo.php',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        self::assertFileExists($dockerLog, 'Docker shim log not created');

        $dockerLogContent = file_get_contents($dockerLog) ?: '';
        // First call is a ps check, second is the run --rm invocation
        self::assertStringContainsString('docker compose -f docker-compose.yml run --rm --workdir /app php', $dockerLogContent);

        // The inner command arguments are recorded by the tool shim, not the docker shim
        self::assertFileExists($shimLog, 'Tool shim log not created');
        $shimLogContent = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('php-cs-fixer fix --config=.php-cs-fixer.dist.php -- --dry-run src/Foo.php', $shimLogContent);

        // Clean up
        exec('rm -rf ' . "{$tempLogDir}/*");
        exec('rm -rf ' . $binDir);
        exec('rm -rf ' . $tempLogDir);
    }
}
