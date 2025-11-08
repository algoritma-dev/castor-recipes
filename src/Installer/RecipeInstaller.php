<?php

declare(strict_types=1);

namespace CastorRecipes\Installer;

use CastorRecipes\Base\RecipeInterface;
use CastorRecipes\Base\RecipeRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Interactive installer for Castor recipes.
 */
class RecipeInstaller
{
    private RecipeRegistry $registry;
    private InputInterface $input;
    private OutputInterface $output;
    private SymfonyStyle $io;
    
    /**
     * @var array<string, mixed>
     */
    private array $config = [];
    
    public function __construct(
        RecipeRegistry $registry,
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->registry = $registry;
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
    }
    
    /**
     * Run the interactive installer.
     */
    public function run(): int
    {
        $this->io->title('Castor Recipes Installer');
        $this->io->text([
            'This installer will help you set up a Castor recipe for your project.',
            'You will be asked to select a platform and configure the recipe.',
        ]);
        
        // Select platform
        $platform = $this->selectPlatform();
        if ($platform === null) {
            $this->io->error('No platforms available.');
            return 1;
        }
        
        // Select recipe
        $recipe = $this->selectRecipe($platform);
        if ($recipe === null) {
            $this->io->error('No recipes available for the selected platform.');
            return 1;
        }
        
        // Configure recipe
        $this->configureRecipe($recipe);
        
        // Confirm installation
        if (!$this->confirmInstallation($recipe)) {
            $this->io->warning('Installation cancelled.');
            return 0;
        }
        
        // Execute recipe
        $this->io->section('Installing ' . $recipe->getName());
        $result = $recipe->execute();
        
        if ($result === 0) {
            $this->io->success('Recipe installed successfully!');
        } else {
            $this->io->error('Recipe installation failed.');
        }
        
        return $result;
    }
    
    /**
     * Select a platform.
     */
    private function selectPlatform(): ?string
    {
        $platforms = $this->registry->getPlatforms();
        if (empty($platforms)) {
            return null;
        }
        
        $question = new ChoiceQuestion(
            'Please select a platform',
            $platforms
        );
        
        return $this->io->askQuestion($question);
    }
    
    /**
     * Select a recipe for the given platform.
     */
    private function selectRecipe(string $platform): ?RecipeInterface
    {
        $recipes = $this->registry->forPlatform($platform);
        if (empty($recipes)) {
            return null;
        }
        
        $recipeNames = array_map(
            fn(RecipeInterface $recipe) => $recipe->getName() . ' - ' . $recipe->getDescription(),
            $recipes
        );
        
        $question = new ChoiceQuestion(
            'Please select a recipe',
            array_keys($recipeNames)
        );
        
        $recipeName = $this->io->askQuestion($question);
        return $this->registry->get($recipeName);
    }
    
    /**
     * Configure the recipe.
     */
    private function configureRecipe(RecipeInterface $recipe): void
    {
        $this->io->section('Configure ' . $recipe->getName());
        
        // Ask for project directory
        $projectDir = $this->io->ask(
            'Project directory',
            getcwd() . '/' . $recipe->getName(),
            function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('Project directory cannot be empty.');
                }
                return $value;
            }
        );
        
        $this->config['project_dir'] = $projectDir;
        
        // Platform-specific configuration
        switch ($recipe->getPlatform()) {
            case 'WordPress':
                $this->configureWordPress();
                break;
                
            case 'Symfony':
                $this->configureSymfony();
                break;
                
            // Add more platform-specific configurations here
                
            default:
                // No specific configuration
                break;
        }
        
        // Set the configuration on the recipe
        foreach ($this->config as $key => $value) {
            $_ENV['CASTOR_' . strtoupper($key)] = $value;
        }
    }
    
    /**
     * Configure WordPress-specific settings.
     */
    private function configureWordPress(): void
    {
        $this->config['wp_version'] = $this->io->ask('WordPress version', 'latest');
        $this->config['db_name'] = $this->io->ask('Database name', 'wordpress');
        $this->config['db_user'] = $this->io->ask('Database user', 'wordpress');
        $this->config['db_password'] = $this->io->askHidden('Database password', function ($value) {
            return $value;
        }) ?: 'wordpress';
        $this->config['db_host'] = $this->io->ask('Database host', 'localhost');
        $this->config['site_url'] = $this->io->ask('Site URL', 'http://localhost:8080');
        $this->config['admin_user'] = $this->io->ask('Admin username', 'admin');
        $this->config['admin_password'] = $this->io->askHidden('Admin password', function ($value) {
            return $value;
        }) ?: 'password';
        $this->config['admin_email'] = $this->io->ask('Admin email', 'admin@example.com');
    }
    
    /**
     * Configure Symfony-specific settings.
     */
    private function configureSymfony(): void
    {
        $this->config['symfony_version'] = $this->io->ask('Symfony version', '6.3');
        $this->config['project_name'] = $this->io->ask('Project name', 'symfony-app');
        $this->config['database_url'] = $this->io->ask(
            'Database URL',
            'mysql://root:root@127.0.0.1:3306/symfony?serverVersion=8.0'
        );
        $this->config['mailer_dsn'] = $this->io->ask('Mailer DSN', 'smtp://localhost:1025');
    }
    
    /**
     * Confirm the installation.
     */
    private function confirmInstallation(RecipeInterface $recipe): bool
    {
        $this->io->section('Installation Summary');
        $this->io->text([
            'Platform: ' . $recipe->getPlatform(),
            'Recipe: ' . $recipe->getName(),
            'Project directory: ' . $this->config['project_dir'],
        ]);
        
        // Display platform-specific configuration
        switch ($recipe->getPlatform()) {
            case 'WordPress':
                $this->io->text([
                    'WordPress version: ' . $this->config['wp_version'],
                    'Database: ' . $this->config['db_name'] . ' (' . $this->config['db_user'] . '@' . $this->config['db_host'] . ')',
                    'Site URL: ' . $this->config['site_url'],
                ]);
                break;
                
            case 'Symfony':
                $this->io->text([
                    'Symfony version: ' . $this->config['symfony_version'],
                    'Project name: ' . $this->config['project_name'],
                    'Database URL: ' . $this->config['database_url'],
                ]);
                break;
                
            default:
                // No specific summary
                break;
        }
        
        return $this->io->confirm('Do you want to install this recipe?', true);
    }
}