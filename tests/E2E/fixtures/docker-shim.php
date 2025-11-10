#!/usr/bin/env php
<?php

// Docker CLI shim to capture dockerize() calls.
// Behaviour:
// - Logs every invocation to SHIM_DOCKER_LOG (or cwd/docker-shim.log)
// - For `docker compose ... ps` prints nothing and exits 0 (so dockerize() treats service as not running)
// - For `docker compose run|exec` it will also execute the inner command so that tool shims can log their calls
//   and still exit 0 to keep tests deterministic.
// This allows asserting both the docker wrapper command and the inner tool invocation constructed by dockerize().

$log = getenv('SHIM_DOCKER_LOG') ?: (getcwd().'/docker-shim.log');
$args = array_slice($argv, 1);
$line = 'docker '.implode(' ', array_map(static fn(string $a): string => $a, $args));
file_put_contents($log, $line."\n", FILE_APPEND);

// If not a docker compose call, just succeed
if (! isset($args[0]) || $args[0] !== 'compose') {
    exit(0);
}

// Handle `docker compose ... ps` → print nothing and exit 0
if (in_array('ps', $args, true)) {
    // no output ensures Castor\capture() sees an empty string
    exit(0);
}

// Try to execute inner command for `run` or `exec`
$cmdIndex = null;
foreach ($args as $i => $tok) {
    if ($tok === 'run' || $tok === 'exec') {
        $cmdIndex = $i;

        break;
    }
}

if ($cmdIndex === null) {
    // Nothing to do
    exit(0);
}

$mode = $args[$cmdIndex];
$i = $cmdIndex + 1;

// Skip optional flags for run (e.g., --rm) which may appear immediately after 'run'
while ($i < count($args) && str_starts_with($args[$i], '-')) {
    // stop skipping flags when we hit the service name for exec (exec usually doesn't have flags here)
    if ($mode === 'exec' && $args[$i] !== '--workdir') {
        break;
    }
    // For both run and exec, allow --workdir to appear before service or after, be permissive
    $i++;
}

// The service name should be next (if present). If the token looks like an option, skip it.
$service = null;
if ($i < count($args) && ! str_starts_with($args[$i], '-')) {
    $service = $args[$i];
    $i++;
}

// Optional workdir: `--workdir <dir>`
if ($i < count($args) && $args[$i] === '--workdir') {
    $i += 2; // skip flag and its value
}

// Remaining tokens are the inner command
$inner = array_slice($args, $i);

if ($inner !== []) {
    // Execute the inner command so that tool shims can log their invocation
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    // Use array form to avoid shell parsing issues
    $proc = proc_open($inner, $descriptors, $pipes, getcwd(), $_ENV + $_SERVER);
    if (is_resource($proc)) {
        // consume and close pipes to avoid zombies
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        // do not propagate exit code; this shim always exits 0
        proc_close($proc);
    }
}

exit(0);
