<?php

declare(strict_types=1);

namespace CastorRecipes\Tests\E2E;

require_once __DIR__ . '/Support/Proc.php';

use CastorRecipes\Tests\E2E\Support\Proc;
use PHPUnit\Framework\TestCase;

final class SymfonyMoreTasksTest extends TestCase
{
    public function testDbCreateAndDrop(): void
    {
        $env = $this->baseEnv();

        $p1 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:db-create'], $env, getcwd());
        self::assertSame(0, $p1->exitCode, $p1->stdout . "\n" . $p1->stderr);
        $p2 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:db-drop'], $env, getcwd());
        self::assertSame(0, $p2->exitCode, $p2->stdout . "\n" . $p2->stderr);

        $log = $this->getLog($env);
        self::assertStringContainsString('console doctrine:database:create --if-not-exists', $log);
        self::assertStringContainsString('console doctrine:database:drop --force --if-exists', $log);
    }

    public function testMigrations(): void
    {
        $env = $this->baseEnv();

        $p1 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:migrate'], $env, getcwd());
        self::assertSame(0, $p1->exitCode, $p1->stdout . "\n" . $p1->stderr);
        $p2 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:migrate-diff'], $env, getcwd());
        self::assertSame(0, $p2->exitCode, $p2->stdout . "\n" . $p2->stderr);
        $p3 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:migrate-fresh'], $env, getcwd());
        self::assertSame(0, $p3->exitCode, $p3->stdout . "\n" . $p3->stderr);

        $log = $this->getLog($env);
        self::assertStringContainsString('console doctrine:migrations:migrate --no-interaction', $log);
        self::assertStringContainsString('console doctrine:migrations:diff --no-interaction', $log);
        self::assertStringContainsString('console doctrine:schema:drop --force --full-database', $log);
    }

    public function testFixturesAndAssets(): void
    {
        $env = $this->baseEnv();

        $p1 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:fixtures-load'], $env, getcwd());
        self::assertSame(0, $p1->exitCode, $p1->stdout . "\n" . $p1->stderr);
        $p2 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:assets-install'], $env, getcwd());
        self::assertSame(0, $p2->exitCode, $p2->stdout . "\n" . $p2->stderr);

        $log = $this->getLog($env);
        self::assertStringContainsString('console doctrine:fixtures:load --no-interaction --purge-with-truncate', $log);
        self::assertStringContainsString('console assets:install --symlink --relative public', $log);
    }

    public function testMessengerConsumeAndLogsTail(): void
    {
        $env = $this->baseEnv();

        $p1 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:messenger-consume'], $env, getcwd());
        self::assertSame(0, $p1->exitCode);

        // logs_tail uses tail binary, not console; but we only need it to run without error
        $p2 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:logs-tail', '--lines=50'], $env, getcwd());
        self::assertSame(0, $p2->exitCode);
    }

    public function testConsoleProxy(): void
    {
        $env = $this->baseEnv();

        $p = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:console', '--args=about'], $env, getcwd());
        self::assertSame(0, $p->exitCode, $p->stdout . "\n" . $p->stderr);
        $log = $this->getLog($env);
        self::assertStringContainsString('console about', $log);
    }

    public function testSfTestTaskAndCi(): void
    {
        // For sf:test and sf:ci, phpunit is used; point it to shim so we can assert
        $env = $this->baseEnv();
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $env['PHPUNIT_BIN'] = \PHP_BINARY . ' ' . $shim;
        $env['SHIM_TOOL'] = 'phpunit';

        $p1 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:test'], $env, getcwd());
        self::assertSame(0, $p1->exitCode, $p1->stdout . "\n" . $p1->stderr);

        // Reset tool back to console for lint_all inside ci
        $env['SHIM_TOOL'] = 'console';
        $p2 = Proc::run([\PHP_BINARY, 'vendor/bin/castor', 'sf:ci'], $env, getcwd());
        self::assertSame(0, $p2->exitCode, $p2->stdout . "\n" . $p2->stderr);
    }

    /**
     * @return array{ SF_CONSOLE: string, SHIM_TOOL: string, SHIM_LOG: string, CASTOR_DOCKER: string, PHP_BIN: string }
     */
    private function baseEnv(string $tool = 'console'): array
    {
        $shim = __DIR__ . '/fixtures/tool-shim.php';
        $shimLog = sys_get_temp_dir() . '/castor-recipes-sf-' . uniqid('', true) . '.log';

        return [
            'SF_CONSOLE' => $shim,
            'SHIM_TOOL' => $tool,
            'SHIM_LOG' => $shimLog,
            'CASTOR_DOCKER' => '0',
            'PHP_BIN' => \PHP_BINARY,
        ];
    }

    /**
     * @param array<string, string> $env
     */
    private function getLog(array $env): string
    {
        return file_get_contents($env['SHIM_LOG']) ?: '';
    }
}
