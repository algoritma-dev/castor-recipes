<?php

declare(strict_types=1);

namespace CastorRecipes;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Symfony\Component\Filesystem\Filesystem;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;

    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // no-op
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();

        if ($localRepo->getDevMode() === false || $localRepo->getDevMode() === null) {
            return;
        }

        $vendorDir = $composer->getConfig()->get('vendor-dir');
        $projectRoot = \dirname((string) $vendorDir);
        $castorFile = $projectRoot . '/castor.php';

        if (! file_exists($castorFile)) {
            return;
        }

        $content = file_get_contents($castorFile);
        $originalContent = $content;

        // Rimuovi tutte le righe che contengono il require del plugin
        $lines = explode("\n", $content);
        $filteredLines = array_filter($lines, fn (string $line): bool => ! str_contains($line, 'algoritma/castor-recipes/recipes/'));

        $newContent = implode("\n", $filteredLines);

        if ($newContent !== $originalContent) {
            file_put_contents($castorFile, $newContent);
            $io->write('<info>Removed</info> recipe requires from castor.php');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageUpdate',
        ];
    }

    public function onPostPackageInstall(PackageEvent $packageEvent): void
    {
        if (! $packageEvent->isDevMode()) {
            // Do nothing in production mode.
            return;
        }

        $operation = $packageEvent->getOperation();
        \assert($operation instanceof InstallOperation);

        $package = $operation->getPackage();

        if ($package->getName() !== 'algoritma/castor-recipes') {
            return; // only act when this package gets installed
        }

        $this->runInstaller();
    }

    public function onPostPackageUpdate(PackageEvent $packageEvent): void
    {
        if (! $packageEvent->isDevMode()) {
            // Do nothing in production mode.
            return;
        }

        $operation = $packageEvent->getOperation();
        \assert($operation instanceof UpdateOperation);

        $package = $operation->getTargetPackage();

        if ($package->getName() !== 'algoritma/castor-recipes') {
            return; // only act when this package gets updated (freshly required)
        }

        $this->runInstaller();
    }

    private function runInstaller(): void
    {
        $recipes = glob(\dirname(__DIR__) . '/recipes/*.php');
        $recipes = array_map(fn (string $path): string => pathinfo($path, \PATHINFO_FILENAME), $recipes);
        $recipes = array_filter($recipes, fn (string $recipe): bool => ! str_starts_with($recipe, '_'));

        asort($recipes);

        $vendorDir = $this->composer->getConfig()->get('vendor-dir');
        $projectRoot = \dirname((string) $vendorDir);
        $castorFile = $projectRoot . '/castor.php';
        $filesystem = new Filesystem();

        // Leggi le ricette già presenti nel file castor.php
        $existingRecipes = [];
        if ($filesystem->exists($castorFile)) {
            $content = file_get_contents($castorFile);
            foreach ($recipes as $recipe) {
                if (str_contains($content, "vendor/algoritma/castor-recipes/recipes/{$recipe}.php")) {
                    $existingRecipes[] = $recipe;
                }
            }
        }

        if ($existingRecipes !== []) {
            $this->io->write(\sprintf(
                '<info>Found %d recipe(s) already installed:</info> <comment>%s</comment>',
                \count($existingRecipes),
                implode(', ', $existingRecipes)
            ));
        }

        $this->io->write('<info>Castor Recipes</info>: choose one or more recipes to install.');

        $selectedRecipes = [];

        while (true) {
            $availableRecipes = array_values(array_diff($recipes, $selectedRecipes, $existingRecipes));

            if ($availableRecipes === []) {
                $this->io->write('<info>All recipes have been selected.</info>');

                break;
            }

            $choices = array_merge([''], $availableRecipes);
            $choice = $this->io->select(
                \sprintf('Select a recipe to add (%d selected, empty to finish):', \count($selectedRecipes)),
                $choices,
                ''
            );

            // Blank choice means finish
            if ($choices[$choice] === '') {
                // Check if any recipe was selected
                if ($selectedRecipes === [] && $existingRecipes === []) {
                    $this->io->write('<error>You must select at least one recipe.</error>');

                    continue;
                }

                break;
            }

            $selectedRecipes[] = $choices[$choice];
            $this->io->write(\sprintf('<comment>Added:</comment> %s', $choices[$choice]));
        }

        if (! $filesystem->exists($castorFile)) {
            $requireStatements = '';
            foreach ($selectedRecipes as $selectedRecipe) {
                $relativeRequirePath = 'vendor/algoritma/castor-recipes/recipes/' . $selectedRecipe . '.php';
                $requireStatements .= "require __DIR__ . '/{$relativeRequirePath}';\n";
            }

            $content = <<<PHP
                <?php

                {$requireStatements}
                PHP;
            $filesystem->dumpFile($castorFile, $content);
            $this->io->write(\sprintf(
                '<info>Created</info> %s with %d recipe(s): <comment>%s</comment>.',
                $this->relativeToCwd($castorFile),
                \count($selectedRecipes),
                implode(', ', $selectedRecipes)
            ));
        } else {
            $this->io->write('<comment>castor.php already exists in your project.</comment>');

            $content = file_get_contents($castorFile);
            $requireStatements = '';

            foreach ($selectedRecipes as $selectedRecipe) {
                $relativeRequirePath = 'vendor/algoritma/castor-recipes/recipes/' . $selectedRecipe . '.php';
                $requireLine = "require __DIR__ . '/{$relativeRequirePath}';\n";

                // is require already present?
                if (str_contains($content, $requireLine)) {
                    $this->io->write("<info>Skipped:</info> {$selectedRecipe} (already present)");
                    continue;
                }

                $requireStatements .= $requireLine . "\n";
            }

            if ($requireStatements !== '') {
                // find the first <?php statement
                if (preg_match('/^<\?php\s*\n/m', $content, $matches, \PREG_OFFSET_CAPTURE)) {
                    $insertPosition = $matches[0][1] + \strlen($matches[0][0]);
                } else {
                    // if no <?php statement is found, add one at the beginning of the file
                    $content = "<?php\n\n" . $content;
                    $insertPosition = 6; // "<?php\n" length
                }

                // insert the require statements
                $newContent = substr($content, 0, $insertPosition)
                    . "\n" . $requireStatements
                    . substr($content, $insertPosition);

                $filesystem->dumpFile($castorFile, $newContent);

                $this->io->write(\sprintf(
                    '<info>Added</info> %d recipe(s) to %s: <comment>%s</comment>.',
                    \count($selectedRecipes),
                    $this->relativeToCwd($castorFile),
                    implode(', ', $selectedRecipes)
                ));
            } else {
                $this->io->write('<info>All selected recipes are already present in castor.php</info>');
            }

            $this->io->write('');
        }

        $this->io->write('');
        $this->io->write('<info>Done!</info> You can now list the available tasks with: <comment>vendor/bin/castor</comment>');
    }

    private function relativeToCwd(string $path): string
    {
        $cwd = getcwd() ?: '';

        return str_starts_with($path, $cwd) ? ltrim(substr($path, \strlen($cwd)), \DIRECTORY_SEPARATOR) : $path;
    }
}
