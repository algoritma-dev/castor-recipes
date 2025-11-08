<?php

declare(strict_types=1);

namespace CastorRecipes\Tests;

use CastorRecipes\Plugin;
use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/castor_recipes_plugin_test_' . bin2hex(random_bytes(4));
        // project root
        mkdir($this->tmpDir, 0777, true);
        // vendor dir inside project root
        mkdir($this->tmpDir . '/vendor', 0777, true);
        // ensure CWD is not polluted across tests
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
        parent::tearDown();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = Plugin::getSubscribedEvents();
        self::assertArrayHasKey('post-package-install', $events);
        self::assertArrayHasKey('post-package-update', $events);
        self::assertSame('onPostPackageInstall', $events['post-package-install']);
        self::assertSame('onPostPackageUpdate', $events['post-package-update']);
    }

    public function testOnPostPackageInstallCreatesCastorFileWithSelectedRecipeAndOutputsMessage(): void
    {
        // TODO
        self::markTestSkipped('Not implemented yet');
    }

    public function testOnPostPackageInstallDoesNotOverwriteExistingCastorFileAndPrintsManualInstructions(): void
    {
        // TODO
        self::markTestSkipped('Not implemented yet');
    }

    public function testOnPostPackageInstallIgnoresDifferentPackage(): void
    {
        [$plugin, , $messages] = $this->makeActivatedPlugin(ioSelect: 0);

        $packageEvent = $this->makePackageEventForInstall('some/other-package');
        $plugin->onPostPackageInstall($packageEvent);

        $all = implode("\n", $messages());
        self::assertSame('', trim($all), 'No output expected for other packages');
        self::assertFileDoesNotExist($this->tmpDir . '/castor.php');
    }

    public function testOnPostPackageInstallWithOperationWithoutGetPackageDoesNothing(): void
    {
        [$plugin, , $messages] = $this->makeActivatedPlugin(ioSelect: 0);

        $event = $this->createMock(PackageEvent::class);
        // Return an object without getPackage()
        $event->method('getOperation')->willReturn(new class () implements OperationInterface {
            public function getOperationType()
            {
                return '';
            }

            public function show(bool $lock)
            {
                return '';
            }

            public function __toString(): string
            {
                return '';
            }
        });

        $plugin->onPostPackageInstall($event);

        self::assertFileDoesNotExist($this->tmpDir . '/castor.php');
        $all = implode("\n", $messages());
        self::assertSame('', trim($all));
    }

    public function testOnPostPackageUpdateCreatesCastorFileToo(): void
    {
        [$plugin, , ] = $this->makeActivatedPlugin(ioSelect: 2); // shopware6

        $packageEvent = $this->makePackageEventForUpdate('raffaelecarelle/castor-recipes');
        $plugin->onPostPackageUpdate($packageEvent);

        $castorFile = $this->tmpDir . '/castor.php';
        self::assertFileExists($castorFile);
        $content = file_get_contents($castorFile) ?: '';
        self::assertStringContainsString('/recipes/shopware6.php', $content);
    }

    public function testOnPostPackageUpdateIgnoresDifferentPackage(): void
    {
        [$plugin, , $messages] = $this->makeActivatedPlugin(ioSelect: 0);

        $packageEvent = $this->makePackageEventForUpdate('different/package');
        $plugin->onPostPackageUpdate($packageEvent);

        self::assertFileDoesNotExist($this->tmpDir . '/castor.php');
        $all = implode("\n", $messages());
        self::assertSame('', trim($all));
    }

    public function testOnPostPackageUpdateWithOperationWithoutGetTargetPackageDoesNothing(): void
    {
        [$plugin, , $messages] = $this->makeActivatedPlugin(ioSelect: 0);

        $event = $this->createMock(PackageEvent::class);
        $event->method('getOperation')->willReturn(new class () implements OperationInterface {
            // no getTargetPackage method
            public function getOperationType()
            {
                return '';
            }

            public function show(bool $lock)
            {
                return '';
            }

            public function __toString(): string
            {
                return '';
            }
        });

        $plugin->onPostPackageUpdate($event);

        self::assertFileDoesNotExist($this->tmpDir . '/castor.php');
        $all = implode("\n", $messages());
        self::assertSame('', trim($all));
    }

    public function testInvalidChoiceFallsBackToSymfonyRecipe(): void
    {
        // Force IO->select to return an out-of-range index
        [$plugin, , ] = $this->makeActivatedPlugin(ioSelect: 999);

        $packageEvent = $this->makePackageEventForInstall('raffaelecarelle/castor-recipes');
        $plugin->onPostPackageInstall($packageEvent);

        $castorFile = $this->tmpDir . '/castor.php';
        $content = file_get_contents($castorFile) ?: '';
        self::assertStringContainsString('/recipes/symfony.php', $content);
    }

    public function testCreatedMessageUsesPathRelativeToCwd(): void
    {
        // TODO
        self::markTestSkipped('Not implemented yet');
    }

    /**
     * Helpers
     *
     * @return array{Plugin, IOInterface, callable(): array<string>}
     */
    private function makeActivatedPlugin(int $ioSelect): array
    {
        // Mock IO to capture writes and control select()
        /** @var IOInterface&MockObject $io */
        $io = $this->createMock(IOInterface::class);

        $writes = [];
        $io->method('write')
            ->willReturnCallback(function ($messages) use (&$writes): void {
                if (is_array($messages)) {
                    foreach ($messages as $message) {
                        $writes[] = (string) $message;
                    }
                } else {
                    $writes[] = (string) $messages;
                }
            });
        $io->method('select')->willReturn($ioSelect);

        // Mock Composer and Config to provide vendor-dir
        $config = $this->getMockBuilder(ComposerConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $config->method('get')->with('vendor-dir')->willReturn($this->tmpDir . '/vendor');

        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);

        $plugin = new Plugin();
        $plugin->activate($composer, $io);

        $messagesGetter = fn (): array => $writes;

        return [$plugin, $io, $messagesGetter];
    }

    private function makePackageEventForInstall(string $packageName): PackageEvent
    {
        $package = new Package($packageName, '1.0.0.0', '1.0.0');

        $operation = new class ($package) implements OperationInterface {
            public function __construct(private readonly Package $package)
            {
            }

            public function getPackage(): Package
            {
                return $this->package;
            }

            public function getOperationType()
            {
                return '';
            }

            public function show(bool $lock)
            {
                return '';
            }

            public function __toString(): string
            {
                return '';
            }
        };

        $event = $this->createMock(PackageEvent::class);
        $event->method('getOperation')->willReturn($operation);

        return $event;
    }

    private function makePackageEventForUpdate(string $packageName): PackageEvent
    {
        $package = new Package($packageName, '1.0.0.0', '1.0.0');

        $operation = new class ($package) implements OperationInterface {
            public function __construct(private readonly Package $package)
            {
            }

            public function getTargetPackage(): Package
            {
                return $this->package;
            }

            public function getOperationType()
            {
                return '';
            }

            public function show(bool $lock)
            {
                return '';
            }

            public function __toString(): string
            {
                return '';
            }
        };

        $event = $this->createMock(PackageEvent::class);
        $event->method('getOperation')->willReturn($operation);

        return $event;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }
}
