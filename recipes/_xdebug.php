<?php

declare(strict_types=1);

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\io;
use function Castor\run;

#[AsTask(name: 'toggle', namespace: 'xdebug', description: 'Enable or disable Xdebug', aliases: ['xt'])]
function xdebug_toggle(
    #[AsOption(description: 'The name of the php-fpm service')]
    string $fpmServiceName = 'php-fpm',
    #[AsOption(description: 'The name of the webserver service')]
    string $webServerServiceName = 'webserver'
): void {
    $iniPath = find_xdebug_ini();

    if ($iniPath === null) {
        io()->error('Could not find xdebug.ini path.');

        return;
    }

    $isDocker = env_value('CASTOR_DOCKER') === 'true' || env_value('CASTOR_DOCKER') === '1';
    $phpService = $isDocker ? $fpmServiceName : null;

    $enabledPath = $iniPath;
    $disabledPath = "{$iniPath}.disabled";

    // Check if Xdebug is currently disabled by checking if the .disabled file exists
    $checkCommand = $isDocker
        ? dockerize(\sprintf('test -f %s && echo "disabled" || echo "enabled"', $disabledPath), $phpService)
        : \sprintf('test -f %s && echo "disabled" || echo "enabled"', $disabledPath);

    $currentState = trim(capture($checkCommand));
    $enable = $currentState === 'disabled';

    $source = $enable ? $disabledPath : $enabledPath;
    $target = $enable ? $enabledPath : $disabledPath;
    $inverseAction = $enable ? 'enabled' : 'disabled';

    try {
        run(dockerize(\sprintf('sudo mv %s %s', $source, $target), $phpService));
    } catch (Throwable) {
        io()->warning("Xdebug is already {$inverseAction}.");

        return;
    }

    if ($isDocker) {
        docker_compose_restart($fpmServiceName);
        docker_compose_restart($webServerServiceName);
    }

    io()->success("Xdebug is {$inverseAction}. Run `castor xdebug:toggle` to toggle it again.");
}

function find_xdebug_ini(): ?string
{
    $isDocker = env_value('CASTOR_DOCKER') === 'true' || env_value('CASTOR_DOCKER') === '1';
    $phpService = $isDocker ? env_value('DOCKER_SERVICE', 'workspace') : null;

    $phpIniOutput = capture(dockerize('php --ini', $phpService));
    $lines = explode("\n", $phpIniOutput);
    $scanDir = null;

    // Find the directory of .ini files
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), 'Scan for additional .ini files in:')) {
            $scanDir = trim(str_replace('Scan for additional .ini files in:', '', $line));
            break;
        }
    }

    if ($scanDir === null) {
        io()->error('Could not find PHP scan dir for ini files.');

        return null;
    }

    // Find a file that contains "xdebug" in its name, with or without .disabled extension
    $command = \sprintf("find %s -name '*xdebug.ini*' -print -quit", $scanDir);
    $iniPath = capture(dockerize($command, $phpService));
    $iniPath = trim($iniPath);

    if ($iniPath === '' || $iniPath === '0') {
        // Fallback for older PHP versions from original script
        foreach ($lines as $line) {
            if (str_contains($line, 'Loaded Configuration File:')) {
                $phpIniPath = trim(str_replace('Loaded Configuration File:', '', $line));
                // This is a common pattern, but it's a guess.
                $iniPath = \dirname($phpIniPath) . '/conf.d/docker-php-ext-xdebug.ini';

                if ($isDocker) {
                    $checkFileExists = capture(dockerize("test -f {$iniPath} && echo 'true'", $phpService));
                    if (trim($checkFileExists) === 'true') {
                        break;
                    }
                    $checkFileExists = capture(dockerize("test -f {$iniPath}.disabled && echo 'true'", $phpService));
                    if (trim($checkFileExists) === 'true') {
                        break;
                    }
                } elseif (file_exists($iniPath) || file_exists($iniPath . '.disabled')) {
                    break;
                }
                $iniPath = null;
            }
        }
    }

    if (\in_array($iniPath, [null, '', '0'], true)) {
        return null;
    }

    // The logic to enable/disable renames the file, so we should return the path without .disabled
    if (str_ends_with($iniPath, '.disabled')) {
        return substr($iniPath, 0, -9);
    }

    return $iniPath;
}
