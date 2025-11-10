<?php

declare(strict_types=1);

namespace CastorRecipes\Tests\E2E;

require_once __DIR__ . '/Support/Proc.php';

use PHPUnit\Framework\TestCase;
use Tests\E2E\Support\Proc;

final class QualityCheckTest extends TestCase
{
    public function testPhpCsFixerDryRun(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-shim-' . uniqid('', true) . '.log';

        // Build env to point recipe to our shim, and tell shim where to log
        $env = [
            'PHPCSFIXER_BIN' => PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'php-cs-fixer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        // Run the castor task from project root so castor can find castor.php
        $proc = Proc::run([
            PHP_BINARY,
            'vendor/bin/castor',
            'qa:php-cs-fixer',
            '--dry-run',
            'src/Foo.php',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        self::assertFileExists($shimLog, 'Shim log not created');

        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('php-cs-fixer fix --dry-run src/Foo.php', $log);
    }

    public function testPhpCsFixer(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-shim-' . uniqid('', true) . '.log';

        // Build env to point recipe to our shim, and tell shim where to log
        $env = [
            'PHPCSFIXER_BIN' => PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'php-cs-fixer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        // Run the castor task from project root so castor can find castor.php
        $proc = Proc::run([
            PHP_BINARY,
            'vendor/bin/castor',
            'qa:php-cs-fixer',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        self::assertFileExists($shimLog, 'Shim log not created');

        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('php-cs-fixer fix', $log);
    }
}
