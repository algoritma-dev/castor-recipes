<?php

declare(strict_types=1);

namespace CastorRecipes\Tests\E2E\Support;

final class Proc
{
    private function __construct(public readonly int $exitCode, public readonly string $stdout, public readonly string $stderr)
    {
    }

    /**
     * Run a command as a child process.
     *
     * @param list<string> $cmd
     * @param array<string,string> $env
     */
    public static function run(array $cmd, array $env = [], ?string $cwd = null): self
    {
        $descriptors = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        // Build env
        $fullEnv = array_merge(self::baseEnv(), $env);

        $proc = proc_open($cmd, $descriptors, $pipes, $cwd ?? getcwd(), $fullEnv);
        if (! \is_resource($proc)) {
            throw new \RuntimeException('Unable to start process');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            if (\is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $status = proc_get_status($proc);
        $exitCode = proc_close($proc);
        if ($exitCode === -1) {
            $exitCode = $status['exitcode'];
        }

        return new self($exitCode, $stdout ?: '', $stderr ?: '');
    }

    /**
     * Minimal, deterministic env for child processes.
     * Keep PATH unless explicitly overridden.
     * Ensure C locale to avoid localized outputs in assertions.
     *
     * @return array<string,string>
     */
    private static function baseEnv(): array
    {
        $base = [];
        foreach ([
            'PATH', 'HOME', 'USER', 'TMPDIR', 'TEMP', 'TMP',
            'COMPOSER_HOME',
        ] as $k) {
            if (isset($_ENV[$k])) {
                $base[$k] = (string) $_ENV[$k];
            } elseif (isset($_SERVER[$k])) {
                $base[$k] = (string) $_SERVER[$k];
            }
        }
        $base['LC_ALL'] = 'C';
        $base['LANG'] = 'C';

        return $base;
    }
}
