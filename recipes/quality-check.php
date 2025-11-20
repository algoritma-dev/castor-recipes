<?php

declare(strict_types=1);

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;
use Symfony\Component\Filesystem\Path;

use function Castor\capture;
use function Castor\run;
use function Castor\exit_code;

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

#[AsTask(description: 'Pre commit code analysis', namespace: 'qa')]
function pre_commit(): void
{
    $captured = capture('git diff --name-only --diff-filter=ACMR | xargs -n1 --no-run-if-empty realpath');
    $modifiedFiles = array_filter(explode("\n", trim($captured)));
    $filesArg = trim(implode(' ', array_map(fn (string $file): string => Path::makeRelative($file, getcwd()), $modifiedFiles)));

    rector(false, $filesArg);
    php_cs_fixer(false, $filesArg);
    phpstan('analyse --memory-limit=-1');
    aspell_check_all(files: $filesArg);
    tests();
}

#[AsTask(description: 'PHP CS Fixer', namespace: 'qa')]
function php_cs_fixer(bool $dryRun = false, #[AsArgument] string $files = ''): void
{
    if ($files !== '') {
        $config = (string) env_value('PHPCSFIXER_CONFIG', '.php-cs-fixer.dist.php');
        $files = '--config=' . $config . ' -- ' . $files;
    }

    exit_code(dockerize(\sprintf(phpcsfixer_bin() . ' fix %s %s', $dryRun ? '--dry-run' : '', $files)));
}

#[AsTask(description: 'PHP Rector', namespace: 'qa')]
function rector(bool $dryRun = false, string $args = ''): void
{
    exit_code(dockerize(\sprintf('%s %s %s', rector_bin(), $dryRun ? '--dry-run' : '', $args)));
}

#[AsTask(description: 'PHP Rector', namespace: 'qa')]
function phpstan(string $args = ''): void
{
    exit_code(dockerize(\sprintf('%s %s', phpstan_bin(), $args)));
}

#[AsTask(description: 'Debug phpunit test', namespace: 'qa')]
function test_debug(
    ?string $config = null,
    ?string $filter = null,
    ?string $testsuite = null,
    bool $stopOnFailure = false,
    bool $debug = true
): void {
    $params = build_phpunit_params($config, $filter, $testsuite, $stopOnFailure, $debug);
    exit_code(dockerize(\sprintf('%s %s', phpunit_bin(), $params)));
}

#[AsTask(description: 'Run all tests (PHPUnit)', namespace: 'qa')]
function tests(string $args = ''): void
{
    exit_code(dockerize(\sprintf('%s %s', phpunit_bin(), $args)));
}

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
