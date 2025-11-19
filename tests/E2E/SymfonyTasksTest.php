<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\E2E;

require_once __DIR__ . '/Support/Proc.php';

use CastorRecipes\Tests\E2E\Support\Proc;
use PHPUnit\Framework\TestCase;

final class SymfonyTasksTest extends TestCase
{
    public function testCacheClear(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-sf-' . uniqid('', true) . '.log';

        $env = [
            'SF_CONSOLE' => $shim,
            'SHIM_TOOL' => 'console',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0', // force local execution
            'PHP_BIN' => \PHP_BINARY,
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'sf:cache-clear',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        self::assertFileExists($shimLog);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('console cache:clear --no-debug', $log);
    }

    public function testCacheWarmup(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-sf-' . uniqid('', true) . '.log';

        $env = [
            'SF_CONSOLE' => $shim,
            'SHIM_TOOL' => 'console',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
            'PHP_BIN' => \PHP_BINARY,
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'sf:cache-warmup',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        self::assertFileExists($shimLog);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('console cache:warmup --no-debug', $log);
    }

    public function testLintAllComposite(): void
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-sf-' . uniqid('', true) . '.log';

        $env = [
            'SF_CONSOLE' => $shim,
            'SHIM_TOOL' => 'console',
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
            'PHP_BIN' => \PHP_BINARY,
        ];

        $proc = Proc::run([
            \PHP_BINARY,
            'vendor/bin/castor',
            'sf:lint-all',
        ], $env, getcwd());

        self::assertSame(0, $proc->exitCode, $proc->stdout . "\n" . $proc->stderr);
        self::assertFileExists($shimLog);
        $log = file_get_contents($shimLog) ?: '';
        self::assertStringContainsString('console lint:yaml config --parse-tags', $log);
        self::assertStringContainsString('console lint:twig templates', $log);
        self::assertStringContainsString('console lint:container', $log);
    }
}
