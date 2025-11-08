<?php

declare(strict_types=1);

namespace CastorRecipes\Installer;

use Composer\Script\Event;
use CastorRecipes\Base\RecipeRegistry;
use CastorRecipes\Recipe\WordPress\WordPressRecipe;
use CastorRecipes\Recipe\Symfony\SymfonyRecipe;
use CastorRecipes\Recipe\Laravel\LaravelRecipe;
use CastorRecipes\Recipe\TYPO3\Typo3Recipe;
use CastorRecipes\Recipe\CodeIgniter\CodeIgniterRecipe;
use CastorRecipes\Recipe\Magento2\Magento2Recipe;
use CastorRecipes\Recipe\Shopware\ShopwareRecipe;
use CastorRecipes\Recipe\OroCommerce\OroCommerceRecipe;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Composer installer script for Castor recipes.
 */
class ComposerInstaller
{
    /**
     * Run the interactive installer as a Composer script.
     */
    public static function installRecipe(Event $event): void
    {
        $io = $event->getIO();
        $io->write('<info>Running Castor Recipes installer...</info>');

        // Register recipes
        $registry = RecipeRegistry::getInstance();
        $registry->register(new WordPressRecipe());
        $registry->register(new SymfonyRecipe());
        $registry->register(new LaravelRecipe());
        $registry->register(new Typo3Recipe());
        $registry->register(new CodeIgniterRecipe());
        $registry->register(new Magento2Recipe());
        $registry->register(new ShopwareRecipe());
        $registry->register(new OroCommerceRecipe());

        // Create installer
        $input = new ArgvInput();
        $output = new ConsoleOutput();
        $installer = new RecipeInstaller($registry, $input, $output);

        // Run installer
        $result = $installer->run();

        if ($result === 0) {
            $io->write('<info>Recipe installed successfully!</info>');
        } else {
            $io->writeError('<error>Recipe installation failed.</error>');
            exit($result);
        }
    }
}
