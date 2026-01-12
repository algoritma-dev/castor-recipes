<?php

declare(strict_types=1);

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;
use Symfony\Component\Filesystem\Path;

use function Castor\capture;
use function Castor\exit_code;
use function Castor\io;
use function Castor\watch;

require_once __DIR__ . '/_common.php';

function phpcsfixer_bin(): string
{
    return (string) env_value('PHPCSFIXER_BIN', is_file('vendor/bin/php-cs-fixer') ? 'vendor/bin/php-cs-fixer' : 'bin/php-cs-fixer');
}

function rector_bin(): string
{
    return (string) env_value('RECTOR_BIN', is_file('vendor/bin/rector') ? 'vendor/bin/rector' : 'bin/rector');
}

function phpstan_bin(): string
{
    return (string) env_value('PHPSTAN_BIN', is_file('vendor/bin/phpstan') ? 'vendor/bin/phpstan' : 'bin/phpstan');
}

#[AsTask(namespace: 'qa', description: 'Pre commit code analysis', aliases: ['pc'])]
function pre_commit(bool $ignoreChanges = false): int
{
    $filesArg = [];

    if (! $ignoreChanges) {
        $captured = capture('git diff --cached --name-only --diff-filter=ACMR | xargs -n1 --no-run-if-empty realpath');
        $modifiedFiles = array_filter(explode("\n", trim($captured)));
        $filesArg = array_map(fn (string $file): string => Path::makeRelative($file, PathHelper::getRoot()), $modifiedFiles);
    }

    if (rector($filesArg) !== 0) {
        return 1;
    }

    if (php_cs_fixer($filesArg) !== 0) {
        return 1;
    }

    if (phpstan(['analyse --memory-limit=-1']) !== 0) {
        return 1;
    }

    if (aspell_check_all(files: $filesArg) !== 0) {
        return 1;
    }

    if (tests() !== 0) {
        return 1;
    }

    return 0;
}

#[AsTask(namespace: 'qa', description: 'PHP CS Fixer', aliases: ['csf', 'fix'])]
function php_cs_fixer(#[AsRawTokens] array $files = []): int
{
    $files = implode(' ', $files);

    if ($files !== '') {
        $config = (string) env_value('PHPCSFIXER_CONFIG', '.php-cs-fixer.dist.php');
        $files = '--config=' . $config . ' -- ' . $files;
    }

    return exit_code(dockerize(\sprintf(phpcsfixer_bin() . ' fix %s', $files)));
}

#[AsTask(namespace: 'qa', description: 'PHP Rector', aliases: ['rec'])]
function rector(
    #[AsRawTokens]
    array $args = []
): int {
    return exit_code(dockerize(\sprintf('%s %s', rector_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'qa', description: 'PHPStan Static Analysis', aliases: ['stan'])]
function phpstan(
    #[AsRawTokens]
    array $args = []
): int {
    return exit_code(dockerize(\sprintf('%s %s --memory-limit=-1', phpstan_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'qa', description: 'Run PHPUnit tests in watch mode', aliases: ['tw', 'watch'])]
function test_watch(): void
{
    // Enable async signals to catch Ctrl+C immediately
    if (\function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
    }

    $autoloadPaths = get_psr4_paths();
    $autoloadPaths = array_map(fn (string $path): string => $path . '...', $autoloadPaths);

    while (true) {
        io()->writeln('');

        $answer = io()->choice('What do you want to do?', [
            'a' => 'Run all tests',
            't' => 'Filter by test name',
            'f' => 'Filter by failing tests',
            'p' => 'Filter by file name',
            'x' => 'Pass anyone arguments you want to PHPUnit',
            'q' => 'Quit',
        ], 'a');

        if ($answer === 'q') {
            return;
        }

        $args = [];
        $args[] = '--cache-result';
        if ($answer === 't') {
            $testName = io()->ask('Enter the test name');
            $args[] = '--filter ' . $testName;
        }
        if ($answer === 'p') {
            $filename = io()->ask('Enter the file name');
            $args[] = $filename;
        }
        if ($answer === 'x') {
            $args = io()->ask('Enter the plain arguments');
            $args[] = $args;
        }
        if ($answer === 'f') {
            $args[] = '--order-by=depends,defects --stop-on-failure --stop-on-defect --stop-on-error';
        }

        $runTests = function () use ($args): void {
            system('clear');
            tests($args);
            io()->info('Watching... (Press Ctrl+C to change settings, or press SPACE to rerun tests)');
        };

        // Run immediately once
        $runTests();

        // Fork a child process for watching
        $pid = pcntl_fork();

        if ($pid === -1) {
            io()->error('Could not fork process');

            return;
        }

        if ($pid !== 0) {
            // Parent process: ignore SIGINT and wait for child
            pcntl_signal(\SIGINT, \SIG_IGN);

            pcntl_waitpid($pid, $status);

            // Restore default signal handler
            pcntl_signal(\SIGINT, \SIG_DFL);

            // Restore terminal settings in case child process left it in non-canonical mode
            system('stty icanon echo');
            stream_set_blocking(\STDIN, true);

            // Child was interrupted, reload menu
            system('clear');
            io()->writeln('');
            io()->writeln('Reloading configuration...');
            continue;
        }

        // Child process: do the watching
        try {
            // Reset signal handler for child
            pcntl_signal(\SIGINT, function (): void {
                exit(0); // Exit child cleanly on Ctrl+C
            });

            // Set stdin to non-blocking mode
            stream_set_blocking(\STDIN, false);

            // Disable canonical mode and echo for immediate key detection
            system('stty -icanon -echo');

            $lastRun = 0;
            $lastManualRun = 0;

            // Fork another process to handle keyboard input
            $keyboardPid = pcntl_fork();

            if ($keyboardPid === -1) {
                io()->error('Could not fork keyboard listener process');
                exit(1);
            }

            if ($keyboardPid === 0) {
                // Keyboard listener child process
                while (true) {
                    $input = fread(\STDIN, 1);

                    if ($input === ' ') { // Space bar pressed
                        $now = microtime(true);

                        if (($now - $lastManualRun) >= 0.5) {
                            $lastManualRun = $now;
                            $runTests();
                        }
                    }

                    usleep(100000); // Sleep 100ms to avoid busy waiting
                }
            }

            // Main watch process
            watch($autoloadPaths, function (string $file) use ($runTests, &$lastRun): void {
                if (str_ends_with($file, '~')) {
                    return;
                }

                $now = microtime(true);

                if (($now - $lastRun) < 1.5) {
                    return;
                }

                $lastRun = $now;
                $runTests();
            });

            // Kill keyboard listener when watch ends
            posix_kill($keyboardPid, \SIGTERM);
            pcntl_waitpid($keyboardPid, $status);
        } catch (Throwable) {
            // Restore terminal settings before exiting
            system('stty icanon echo');

            // Kill keyboard listener if it exists
            if (isset($keyboardPid) && $keyboardPid > 0) {
                posix_kill($keyboardPid, \SIGTERM);
            }

            exit(0);
        } finally {
            // Ensure terminal settings are restored
            system('stty icanon echo');
        }

        exit(0);
    }
}

#[AsTask(namespace: 'qa', description: 'Run all tests (PHPUnit)', aliases: ['t'])]
function tests(
    #[AsRawTokens]
    array $args = []
): int {
    return exit_code(dockerize(\sprintf('%s %s', phpunit_bin(), implode(' ', $args))));
}
