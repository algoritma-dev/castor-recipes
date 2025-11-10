#!/usr/bin/env php
<?php

// Generic PHP shim for external tools. It records invocations to a log file.
// Config via ENV:
// - SHIM_LOG: absolute path to the log file (required in tests)
// - SHIM_TOOL: tool name to display in logs (optional)

$tool = getenv('SHIM_TOOL') ?: basename($argv[0]);
$log = getenv('SHIM_LOG') ?: (getcwd().'/shim.log');
$args = implode(' ', array_slice($argv, 1));

$line = sprintf('%s %s', $tool, $args);
file_put_contents($log, $line."\n", FILE_APPEND);

echo "[shim:$tool] $args\n";
// exit success
exit(0);
