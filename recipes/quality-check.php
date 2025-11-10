<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/common.php';

#[AsTask(description: 'Pre commit code analysis')]
function pre_commit($file = 'bin/precommit'): void
{
    $file = \Castor\Helper\PathHelper::getRoot() . '/' . $file;
    if (is_file($file) && is_executable($file)) {
        run(dockerize($file));
    } else {
        // Get modified and added files from git
        $process = run('git diff --cached --name-only --diff-filter=AM');
        $modifiedFiles = array_filter(explode("\n", trim($process->getOutput())));

        if ($modifiedFiles === []) {
            echo "No modified files found.\n";

            return;
        }

        $filesArg = implode(' ', array_map(escapeshellarg(...), $modifiedFiles));

        php_cs_fixer(false, $filesArg);
        rector(false, $filesArg);
        phpstan("analyse $filesArg");
        tests();
    }
}

#[AsTask(description: 'PHP CS Fixer')]
function php_cs_fixer(bool $dryRun = false, string $files = ''): void
{
    run(dockerize(sprintf('./bin/php-cs-fixer fix %s %s', $dryRun ? '--dry-run' : '', $files)));
}

#[AsTask(description: 'PHP Rector')]
function rector(bool $dryRun = false, string $args = ''): void
{
    run(dockerize(sprintf('./bin/rector %s %s', $dryRun ? '--dry-run' : '', $args)));
}

#[AsTask(description: 'PHP Rector')]
function phpstan(string $args = ''): void
{
    run(dockerize(sprintf('./bin/phpstan %s', $args)));
}

#[AsTask(description: 'Debug phpunit test')]
function test_debug(
    ?string $config = null,
    ?string $filter = null,
    ?string $testsuite = null,
    bool $stopOnFailure = false,
    bool $debug = true
): void {
    $params = build_phpunit_params($config, $filter, $testsuite, $stopOnFailure, $debug);
    run(dockerize(sprintf('bin/simple-phpunit %s', $params)));
}

#[AsTask(description: 'Exec JS tests in watch mode')]
function test_watch(string $prefix = ''): void
{
    $command = 'npm run test-watch';
    if ($prefix !== '') {
        $command .= ' -- --prefix ' . escapeshellarg($prefix);
    }

    run(dockerize($command));
}

#[AsTask(description: 'Run all tests (PHPUnit and JS)')]
function tests(string $args = ''): void
{
    run(dockerize(sprintf('bin/phpunit %s', $args)));
}

/**
 * Costruisce i parametri per PHPUnit
 */
function build_phpunit_params(
    ?string $config = null,
    ?string $filter = null,
    ?string $testsuite = null,
    bool $stopOnFailure = false,
    bool $debug = true
): string {
    $params = [];

    if ($config !== null) {
        $params[] = '--configuration=' . $config;
    }

    if ($filter !== null) {
        $params[] = '--filter=' . $filter;
    }

    if ($testsuite !== null) {
        $params[] = '--testsuite=' . $testsuite;
    }

    if ($stopOnFailure) {
        $params[] = '--stop-on-failure';
        $params[] = '--stop-on-error';
    }

    if ($debug) {
        $params[] = '--debug';
    }

    return implode(' ', $params);
}
