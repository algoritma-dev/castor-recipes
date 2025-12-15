<?php

declare(strict_types=1);

namespace Algoritma\CastorRecipes\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../recipes/_common.php';

final class DotenvPathTest extends TestCase
{
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        parent::tearDown();
    }

    public function testGetDotenvBasePathReturnsDefaultWhenComposerJsonNotFound(): void
    {
        // Create a temp directory without composer.json
        $tempDir = sys_get_temp_dir() . '/castor-test-no-composer-' . uniqid('', true);
        mkdir($tempDir);
        chdir($tempDir);

        try {
            $result = get_dotenv_base_path();
            self::assertSame('.env', $result);
        } finally {
            chdir($this->originalCwd);
            rmdir($tempDir);
        }
    }

    public function testGetDotenvBasePathReturnsDefaultWhenExtraRuntimeNotSet(): void
    {
        // Create a temp directory with basic composer.json
        $tempDir = sys_get_temp_dir() . '/castor-test-basic-composer-' . uniqid('', true);
        mkdir($tempDir);

        $composerJson = [
            'name' => 'test/project',
            'require' => [],
        ];

        file_put_contents($tempDir . '/composer.json', json_encode($composerJson, JSON_THROW_ON_ERROR));

        chdir($tempDir);

        try {
            $result = get_dotenv_base_path();
            self::assertSame('.env', $result);
        } finally {
            chdir($this->originalCwd);
            unlink($tempDir . '/composer.json');
            rmdir($tempDir);
        }
    }

    public function testGetDotenvBasePathReturnsCustomPathFromComposerJson(): void
    {
        // Create a temp directory with OroCommerce-style composer.json
        $tempDir = sys_get_temp_dir() . '/castor-test-oro-composer-' . uniqid('', true);
        mkdir($tempDir);

        $composerJson = [
            'name' => 'oro/commerce-application',
            'extra' => [
                'runtime' => [
                    'dotenv_path' => '.env-app',
                    'env_var_name' => 'ORO_ENV',
                    'debug_var_name' => 'ORO_DEBUG',
                ],
            ],
        ];

        file_put_contents($tempDir . '/composer.json', json_encode($composerJson, JSON_THROW_ON_ERROR));

        chdir($tempDir);

        try {
            $result = get_dotenv_base_path();
            self::assertSame('.env-app', $result);
        } finally {
            chdir($this->originalCwd);
            unlink($tempDir . '/composer.json');
            rmdir($tempDir);
        }
    }

    public function testGetDotenvBasePathCachesResultPerDirectory(): void
    {
        // Create two temp directories with different composer.json
        $tempDir1 = sys_get_temp_dir() . '/castor-test-cache1-' . uniqid('', true);
        $tempDir2 = sys_get_temp_dir() . '/castor-test-cache2-' . uniqid('', true);
        mkdir($tempDir1);
        mkdir($tempDir2);

        $composerJson1 = [
            'name' => 'test/project1',
            'extra' => [
                'runtime' => [
                    'dotenv_path' => '.env-custom1',
                ],
            ],
        ];

        $composerJson2 = [
            'name' => 'test/project2',
            'extra' => [
                'runtime' => [
                    'dotenv_path' => '.env-custom2',
                ],
            ],
        ];

        file_put_contents($tempDir1 . '/composer.json', json_encode($composerJson1, JSON_THROW_ON_ERROR));
        file_put_contents($tempDir2 . '/composer.json', json_encode($composerJson2, JSON_THROW_ON_ERROR));

        try {
            // First directory
            chdir($tempDir1);
            $result1 = get_dotenv_base_path();
            self::assertSame('.env-custom1', $result1);

            // Second directory
            chdir($tempDir2);
            $result2 = get_dotenv_base_path();
            self::assertSame('.env-custom2', $result2);

            // Back to first directory - should use cached value
            chdir($tempDir1);
            // Modify composer.json
            $composerJson1['extra']['runtime']['dotenv_path'] = '.env-modified';
            file_put_contents($tempDir1 . '/composer.json', json_encode($composerJson1, JSON_THROW_ON_ERROR));

            // Should still return cached value
            $result3 = get_dotenv_base_path();
            self::assertSame('.env-custom1', $result3, 'Should return cached value, not re-read composer.json');
        } finally {
            chdir($this->originalCwd);
            @unlink($tempDir1 . '/composer.json');
            @unlink($tempDir2 . '/composer.json');
            @rmdir($tempDir1);
            @rmdir($tempDir2);
        }
    }

    public function testGetDotenvBasePathHandlesInvalidJson(): void
    {
        // Create a temp directory with invalid composer.json
        $tempDir = sys_get_temp_dir() . '/castor-test-invalid-json-' . uniqid('', true);
        mkdir($tempDir);

        file_put_contents($tempDir . '/composer.json', '{ invalid json }');

        chdir($tempDir);

        try {
            $this->expectException(\JsonException::class);
            $result = get_dotenv_base_path();
            // If no exception is thrown, fail the test
            self::fail('Expected JsonException to be thrown, got result: ' . $result);
        } finally {
            chdir($this->originalCwd);
            @unlink($tempDir . '/composer.json');
            @rmdir($tempDir);
        }
    }
}
