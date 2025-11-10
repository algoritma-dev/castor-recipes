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
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    private string $tmpDir;

    private ?\Symfony\Component\Filesystem\Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/castor_recipes_plugin_test_' . bin2hex(random_bytes(4));
        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();
        // project root
        mkdir($this->tmpDir, 0777, true);
        // vendor dir inside project root
        mkdir($this->tmpDir . '/vendor', 0777, true);
        // ensure CWD is not polluted across tests
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
        $this->filesystem = null;
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
        // Select Laravel (index 1)
        [$plugin, , $messages] = $this->makeActivatedPlugin(ioSelect: 1);

        $packageEvent = $this->makePackageEventForInstall('algoritma/castor-recipes');
        $plugin->onPostPackageInstall($packageEvent);

        $castorFile = $this->tmpDir . '/castor.php';
        self::assertFileExists($castorFile);
        $content = file_get_contents($castorFile) ?: '';
        self::assertStringContainsString('/recipes/', $content);

        $all = $messages();
        // Has intro, created message and done message
        self::assertTrue($this->arrayAnyContains($all, 'Castor Recipes'), 'Expected intro message');
        self::assertTrue($this->arrayAnyContains($all, 'Created'), 'Expected created message');
        self::assertTrue($this->arrayAnyContains($all, 'laravel'), 'Expected recipe name in created message');
        self::assertTrue($this->arrayAnyContains($all, 'Done!'), 'Expected done message');
    }

    public function testOnPostPackageInstallDoesNotOverwriteExistingCastorFileAndPrintsManualInstructions(): void
    {
        // Pre-create castor.php
        $castorFile = $this->tmpDir . '/castor.php';
        file_put_contents($castorFile, '<?php echo "original";');
        $original = file_get_contents($castorFile);

        // Select Magento2 (index 4)
        [$plugin, , $messages] = $this->makeActivatedPlugin(ioSelect: 4);

        $packageEvent = $this->makePackageEventForInstall('algoritma/castor-recipes');
        $plugin->onPostPackageInstall($packageEvent);

        // File should not be overwritten
        self::assertFileExists($castorFile);
        self::assertSame($original, file_get_contents($castorFile));

        $all = $messages();
        self::assertTrue($this->arrayAnyContains($all, 'castor.php already exists'), 'Expected notice about existing file');
        self::assertTrue($this->arrayAnyContains($all, 'Manually add the following line'), 'Expected manual instruction');
        self::assertTrue($this->arrayAnyContains($all, "require __DIR__ . '/vendor/algoritma/castor-recipes/recipes/"), 'Expected require line for selected recipe');
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
        [$plugin, , ] = $this->makeActivatedPlugin(ioSelect: 8); // shopware6

        $packageEvent = $this->makePackageEventForUpdate('algoritma/castor-recipes');
        $plugin->onPostPackageUpdate($packageEvent);

        $castorFile = $this->tmpDir . '/castor.php';
        self::assertFileExists($castorFile);
        $content = file_get_contents($castorFile) ?: '';
        self::assertStringContainsString('/recipes/', $content);
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

    public function testRunInstallerDoesNotOverwriteExistingCastorFile(): void
    {
        $castorFile = $this->tmpDir . '/castor.php';
        file_put_contents($castorFile, "<?php\n\n// Pre-existing content");

        [$plugin, , $messages] = $this->makeActivatedPlugin(ioSelect: 2); // Shopware6

        $packageEvent = $this->makePackageEventForInstall('algoritma/castor-recipes');
        $plugin->onPostPackageInstall($packageEvent);

        $content = file_get_contents($castorFile);
        self::assertSame("<?php\n\n// Pre-existing content", $content, 'Expected castor.php content to remain unchanged');

        $all = $messages();
        self::assertTrue($this->arrayAnyContains($all, 'castor.php already exists'), 'Expected message about existing file');
        self::assertTrue($this->arrayAnyContains($all, 'Manually add the following lines'), 'Expected manual instruction to add recipes');
    }

    public function testCreatedMessageUsesPathRelativeToCwd(): void
    {
        // choose orocommerce (index 3) arbitrarily
        [$plugin, , $messages] = $this->makeActivatedPlugin(ioSelect: 3);

        $cwd = getcwd();
        chdir($this->tmpDir);

        try {
            $packageEvent = $this->makePackageEventForInstall('algoritma/castor-recipes');
            $plugin->onPostPackageInstall($packageEvent);
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }
        }

        $all = $messages();
        $createdMessages = array_values(array_filter($all, fn (string $m): bool => str_contains($m, 'Created') && str_contains($m, 'castor.php')));
        self::assertNotEmpty($createdMessages, 'Expected a created message mentioning castor.php');
        self::assertFalse(str_contains($createdMessages[0], $this->tmpDir), 'Path in created message should be relative, not absolute');
    }

    public function testUninstallRemovesRecipeRequire(): void
    {
        $castorFile = $this->tmpDir . '/castor.php';

        // Simulate existing castor.php with recipe require
        $this->filesystem->dumpFile($castorFile, "<?php\n\nrequire __DIR__ . '/vendor/algoritma/castor-recipes/recipes/symfony.php';");

        $composer = $this->createMock(Composer::class);
        $config = $this->getMockBuilder(ComposerConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $config->method('get')->with('vendor-dir')->willReturn($this->tmpDir . '/vendor');
        $composer->method('getConfig')->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects(self::once())->method('write')->with('<info>Removed</info> recipe requires from castor.php');

        $plugin = new Plugin();
        $plugin->uninstall($composer, $io);

        $content = file_get_contents($castorFile);
        self::assertStringNotContainsString('algoritma/castor-recipes/recipes/symfony.php', $content);
    }

    public function testUninstallDoesNothingIfCastorFileDoesNotExist(): void
    {
        $composer = $this->createMock(Composer::class);
        $config = $this->getMockBuilder(ComposerConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $config->method('get')->with('vendor-dir')->willReturn($this->tmpDir . '/vendor');
        $composer->method('getConfig')->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects(self::never())->method('write');

        $plugin = new Plugin();
        $plugin->uninstall($composer, $io);
    }

    public function testUninstallDoesNothingIfCastorFileUnchanged(): void
    {
        $castorFile = $this->tmpDir . '/castor.php';

        // Simulate an unrelated castor.php file
        $this->filesystem->dumpFile($castorFile, "<?php\n\n// No recipes");

        $composer = $this->createMock(Composer::class);
        $config = $this->getMockBuilder(ComposerConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $config->method('get')->with('vendor-dir')->willReturn($this->tmpDir . '/vendor');
        $composer->method('getConfig')->willReturn($config);

        $io = $this->createMock(IOInterface::class);
        $io->expects(self::never())->method('write');

        $plugin = new Plugin();
        $plugin->uninstall($composer, $io);

        $content = file_get_contents($castorFile);
        self::assertSame("<?php\n\n// No recipes", $content);
    }

    public function testRunInstallerFiltersRecipesStartingWithUnderscore(): void
    {
        [$plugin, $io, $messages] = $this->makeActivatedPlugin(ioSelect: 1); // Select publicRecipe

        // Run the installer
        $plugin->onPostPackageInstall($this->makePackageEventForInstall('algoritma/castor-recipes'));

        $allMessages = $messages();

        self::assertFalse(
            $this->arrayAnyContains($allMessages, '_common'),
            'Expected filtered out recipes starting with underscore'
        );
        self::assertTrue(
            $this->arrayAnyContains($allMessages, 'shopware6'),
            'Expected messages to include public recipes'
        );
    }

    /**
     * Helpers
     *
     * @return array{Plugin, IOInterface, callable(): array<string>}
     */
    private function makeActivatedPlugin(int $ioSelect): array
    {
        // Use BufferIO to capture all writes and feed the selection via setUserInputs()
        $bufferIO = new \Composer\IO\BufferIO();
        $bufferIO->setUserInputs([(string)$ioSelect]);

        // Mock Composer and Config to provide vendor-dir
        $config = $this->getMockBuilder(ComposerConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $config->method('get')->with('vendor-dir')->willReturn($this->tmpDir . '/vendor');

        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);

        $plugin = new Plugin();
        $plugin->activate($composer, $bufferIO);

        $messagesGetter = function () use ($bufferIO): array {
            $reflectionClass = new \ReflectionClass($bufferIO);
            $out = $reflectionClass->getMethod('getOutput')->invoke($bufferIO);
            $text = trim($out);
            if ($text === '') {
                return [];
            }

            return array_values(array_filter(array_map(trim(...), preg_split('/\r?\n/', $text))));
        };

        return [$plugin, $bufferIO, $messagesGetter];
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

    /**
     * @param array<string> $messages
     */
    private function arrayAnyContains(array $messages, string $needle): bool
    {
        foreach ($messages as $message) {
            if (str_contains((string) $message, $needle)) {
                return true;
            }
        }

        return false;
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
