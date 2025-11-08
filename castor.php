<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use Castor\Context;
use CastorRecipes\Base\RecipeRegistry;
use CastorRecipes\Installer\RecipeInstaller;
use CastorRecipes\Recipe\Laravel\LaravelRecipe;
use CastorRecipes\Recipe\Symfony\SymfonyRecipe;
use CastorRecipes\Recipe\WordPress\WordPressRecipe;
use CastorRecipes\Recipe\TYPO3\Typo3Recipe;
use CastorRecipes\Recipe\CodeIgniter\CodeIgniterRecipe;
use CastorRecipes\Recipe\Magento2\Magento2Recipe;
use CastorRecipes\Recipe\Shopware\ShopwareRecipe;
use CastorRecipes\Recipe\OroCommerce\OroCommerceRecipe;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require __DIR__ . '/vendor/autoload.php';

function bootstrapRegistry(): RecipeRegistry
{
    $registry = RecipeRegistry::getInstance();

    // Ensure clean state in case tasks are run multiple times in the same process
    // (RecipeRegistry currently has no reset; re-registering same names is idempotent)
    $registry->register(new WordPressRecipe());
    $registry->register(new SymfonyRecipe());
    $registry->register(new LaravelRecipe());
    $registry->register(new Typo3Recipe());
    $registry->register(new CodeIgniterRecipe());
    $registry->register(new Magento2Recipe());
    $registry->register(new ShopwareRecipe());
    $registry->register(new OroCommerceRecipe());

    return $registry;
}

#[AsTask(name: 'recipes:list', description: 'List all available recipes')]
function list_recipes(Context $context): void
{
    $registry = bootstrapRegistry();
    $recipes = $registry->all();

    if (empty($recipes)) {
        fwrite(STDOUT, "No recipes available.\n");
        return;
    }

    fwrite(STDOUT, "Available recipes:\n");
    foreach ($recipes as $name => $recipe) {
        fwrite(STDOUT, sprintf("- %s (%s): %s\n", $name, $recipe->getPlatform(), $recipe->getDescription()));
    }
}

#[AsTask(name: 'run:wordpress', description: 'Run the WordPress recipe')]
function run_wordpress(Context $context): int
{
    $registry = bootstrapRegistry();
    return $registry->execute('wordpress');
}

#[AsTask(name: 'run:symfony', description: 'Run the Symfony recipe')]
function run_symfony(Context $context): int
{
    $registry = bootstrapRegistry();
    return $registry->execute('symfony');
}

#[AsTask(name: 'run:laravel', description: 'Run the Laravel recipe')]
function run_laravel(Context $context): int
{
    $registry = bootstrapRegistry();
    return $registry->execute('laravel');
}

#[AsTask(name: 'run:typo3', description: 'Run the TYPO3 recipe')]
function run_typo3(Context $context): int
{
    $registry = bootstrapRegistry();
    return $registry->execute('typo3');
}

#[AsTask(name: 'run:codeigniter', description: 'Run the CodeIgniter recipe')]
function run_codeigniter(Context $context): int
{
    $registry = bootstrapRegistry();
    return $registry->execute('codeigniter');
}

#[AsTask(name: 'run:magento2', description: 'Run the Magento 2 recipe')]
function run_magento2(Context $context): int
{
    $registry = bootstrapRegistry();
    return $registry->execute('magento2');
}

#[AsTask(name: 'run:shopware', description: 'Run the Shopware recipe')]
function run_shopware(Context $context): int
{
    $registry = bootstrapRegistry();
    return $registry->execute('shopware');
}

#[AsTask(name: 'run:orocommerce', description: 'Run the OroCommerce recipe')]
function run_orocommerce(Context $context): int
{
    $registry = bootstrapRegistry();
    return $registry->execute('orocommerce');
}

#[AsTask(name: 'install:interactive', description: 'Run the interactive recipe installer')]
function install_interactive(Context $context): int
{
    $registry = bootstrapRegistry();

    $input = new ArgvInput();
    $output = new ConsoleOutput();

    $installer = new RecipeInstaller($registry, $input, $output);
    return $installer->run();
}
