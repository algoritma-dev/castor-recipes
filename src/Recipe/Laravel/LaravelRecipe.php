<?php

declare(strict_types=1);

namespace CastorRecipes\Recipe\Laravel;

use CastorRecipes\Base\AbstractRecipe;
use Symfony\Component\Process\Process;

/**
 * Recipe for Laravel setup and development.
 */
class LaravelRecipe extends AbstractRecipe
{
    public function __construct()
    {
        parent::__construct(
            'laravel',
            'Laravel setup and development environment',
            'Laravel'
        );
        
        $this->requirements = [
            'php' => '8.1.0',
            'composer' => '2.0.0',
        ];
    }
    
    public function configure(): void
    {
        // Configure Laravel-specific settings
        $this->config = array_merge($this->config, [
            'laravel_version' => $this->getConfig('laravel_version', '10.x'),
            'project_name' => $this->getConfig('project_name', 'laravel-app'),
            'database_connection' => $this->getConfig('database_connection', 'mysql'),
            'database_host' => $this->getConfig('database_host', '127.0.0.1'),
            'database_port' => $this->getConfig('database_port', '3306'),
            'database_name' => $this->getConfig('database_name', 'laravel'),
            'database_user' => $this->getConfig('database_user', 'root'),
            'database_password' => $this->getConfig('database_password', 'root'),
            'packages' => $this->getConfig('packages', [
                'laravel/sanctum',
                'laravel/telescope',
                'spatie/laravel-permission',
            ]),
            'dev_packages' => $this->getConfig('dev_packages', [
                'barryvdh/laravel-debugbar',
                'barryvdh/laravel-ide-helper',
                'laravel/pint',
                'nunomaduro/collision',
                'nunomaduro/larastan',
            ]),
        ]);
    }
    
    protected function runRecipe(): void
    {
        echo "Setting up Laravel environment...\n";
        
        $projectDir = $this->getConfig('project_dir', getcwd() . '/' . $this->config['project_name']);
        
        // Create Laravel project
        $this->createLaravelProject($projectDir);
        
        // Install additional packages
        $this->installPackages($projectDir);
        
        // Configure environment
        $this->configureEnvironment($projectDir);
        
        echo "Laravel setup completed successfully!\n";
        echo "You can start the Laravel development server with:\n";
        echo "cd " . $projectDir . " && php artisan serve\n";
    }
    
    /**
     * Create a new Laravel project.
     */
    private function createLaravelProject(string $projectDir): void
    {
        echo "Creating Laravel project...\n";
        
        $command = [
            'composer',
            'create-project',
            'laravel/laravel:^' . $this->config['laravel_version'],
            $projectDir,
        ];
        
        $this->runCommand($command);
    }
    
    /**
     * Install additional packages.
     */
    private function installPackages(string $projectDir): void
    {
        echo "Installing additional packages...\n";
        
        // Install required packages
        if (!empty($this->config['packages'])) {
            $command = [
                'composer',
                'require',
                '--working-dir=' . $projectDir,
            ];
            
            $command = array_merge($command, $this->config['packages']);
            $this->runCommand($command);
        }
        
        // Install dev packages
        if (!empty($this->config['dev_packages'])) {
            $command = [
                'composer',
                'require',
                '--dev',
                '--working-dir=' . $projectDir,
            ];
            
            $command = array_merge($command, $this->config['dev_packages']);
            $this->runCommand($command);
        }
    }
    
    /**
     * Configure environment variables.
     */
    private function configureEnvironment(string $projectDir): void
    {
        echo "Configuring environment variables...\n";
        
        $envFile = $projectDir . '/.env';
        
        if ($this->filesystem->exists($envFile)) {
            $envContent = file_get_contents($envFile);
            
            // Update database configuration
            $envContent = preg_replace(
                '/DB_CONNECTION=.*/',
                'DB_CONNECTION=' . $this->config['database_connection'],
                $envContent
            );
            
            $envContent = preg_replace(
                '/DB_HOST=.*/',
                'DB_HOST=' . $this->config['database_host'],
                $envContent
            );
            
            $envContent = preg_replace(
                '/DB_PORT=.*/',
                'DB_PORT=' . $this->config['database_port'],
                $envContent
            );
            
            $envContent = preg_replace(
                '/DB_DATABASE=.*/',
                'DB_DATABASE=' . $this->config['database_name'],
                $envContent
            );
            
            $envContent = preg_replace(
                '/DB_USERNAME=.*/',
                'DB_USERNAME=' . $this->config['database_user'],
                $envContent
            );
            
            $envContent = preg_replace(
                '/DB_PASSWORD=.*/',
                'DB_PASSWORD=' . $this->config['database_password'],
                $envContent
            );
            
            // Set application environment
            $envContent = preg_replace(
                '/APP_ENV=.*/',
                'APP_ENV=' . $this->getConfig('env', 'local'),
                $envContent
            );
            
            // Set debug mode
            $envContent = preg_replace(
                '/APP_DEBUG=.*/',
                'APP_DEBUG=' . ($this->getConfig('debug', true) ? 'true' : 'false'),
                $envContent
            );
            
            // Write updated .env file
            file_put_contents($envFile, $envContent);
        }
    }
}