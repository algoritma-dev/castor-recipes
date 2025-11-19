<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\E2E;

require_once __DIR__ . '/Support/Proc.php';

use Algoritma\CastorRecipes\Tests\E2E\Support\Proc;
use PHPUnit\Framework\TestCase;

final class QualityCheckTest extends TestCase
{
    public function testPhpCsFixerDryRun(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-shim-' . uniqid('', true) . '.log';

        // Build env to point recipe to our shim, and tell shim where to log
        $env = [
            'PHPCSFIXER_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'php-cs-fixer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        // Run the castor task from project root so castor can find castor.php
        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'qa:php-cs-fixer',
            '--dry-run',
            'src/Foo.php',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        self::assertFileExists($shimLog, 'Shim log not created');

        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('php-cs-fixer fix --dry-run --config=.php-cs-fixer.dist.php -- src/Foo.php', $log);
    }

    public function testPhpCsFixer(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-shim-' . uniqid('', true) . '.log';

        // Build env to point recipe to our shim, and tell shim where to log
        $env = [
            'PHPCSFIXER_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'php-cs-fixer',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        // Run the castor task from project root so castor can find castor.php
        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'qa:php-cs-fixer',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        self::assertFileExists($shimLog, 'Shim log not created');

        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('php-cs-fixer fix', $log);
    }

    public function testRectorDryRun(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-rector-' . uniqid('', true) . '.log';

        $env = [
            'RECTOR_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'rector',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'qa:rector',
            '--dry-run',
            '--args=--config=rector.php',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('rector --dry-run --config=rector.php', $log);
    }

    public function testPhpStanAnalyse(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-phpstan-' . uniqid('', true) . '.log';

        $env = [
            'PHPSTAN_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'phpstan',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'qa:phpstan',
            '--args=analyse src',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('phpstan analyse src', $log);
    }

    public function testTestsTaskUsesPhpunitBin(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-phpunit-' . uniqid('', true) . '.log';

        $env = [
            'PHPUNIT_BIN' => \PHP_BINARY . ' ' . $shim,
            'SHIM_TOOL' => 'phpunit',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'qa:tests',
            '--args=--filter=ExampleTest',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('phpunit --filter=ExampleTest', $log);
    }
}
