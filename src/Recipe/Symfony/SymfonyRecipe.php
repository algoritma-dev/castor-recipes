<?php

declare(strict_types=1);

namespace CastorRecipes\Recipe\Symfony;

use CastorRecipes\Base\AbstractRecipe;
use Symfony\Component\Process\Process;

/**
 * Recipe for Symfony setup and development.
 */
class SymfonyRecipe extends AbstractRecipe
{
    public function __construct()
    {
        parent::__construct(
            'symfony',
            'Symfony setup and development environment',
            'Symfony'
        );
        
        $this->requirements = [
            'php' => '8.1.0',
            'composer' => '2.0.0',
        ];
    }
    
    public function configure(): void
    {
        // Configure Symfony-specific settings
        $this->config = array_merge($this->config, [
            'symfony_version' => $this->getConfig('symfony_version', '6.3'),
            'project_name' => $this->getConfig('project_name', 'symfony-app'),
            'database_url' => $this->getConfig('database_url', 'mysql://root:root@127.0.0.1:3306/symfony?serverVersion=8.0'),
            'mailer_dsn' => $this->getConfig('mailer_dsn', 'smtp://localhost:1025'),
            'packages' => $this->getConfig('packages', [
                'symfony/orm-pack',
                'symfony/security-bundle',
                'symfony/mailer',
                'symfony/twig-bundle',
                'symfony/form',
                'symfony/validator',
                'symfony/asset',
                'symfony/webpack-encore-bundle',
            ]),
            'dev_packages' => $this->getConfig('dev_packages', [
                'symfony/debug-bundle',
                'symfony/maker-bundle',
                'symfony/profiler-pack',
                'symfony/test-pack',
            ]),
        ]);
    }
    
    protected function runRecipe(): void
    {
        echo "Setting up Symfony environment...\n";
        
        $projectDir = $this->getConfig('project_dir', getcwd() . '/' . $this->config['project_name']);
        
        // Check if Symfony CLI is available
        if ($this->isSymfonyCliAvailable()) {
            $this->setupWithSymfonyCli($projectDir);
        } else {
            $this->setupWithComposer($projectDir);
        }
        
        // Install additional packages
        $this->installPackages($projectDir);
        
        // Configure environment variables
        $this->configureEnvironment($projectDir);
        
        echo "Symfony setup completed successfully!\n";
        echo "You can start the Symfony development server with:\n";
        echo "cd " . $projectDir . " && symfony server:start\n";
        echo "Or if you don't have the Symfony CLI:\n";
        echo "cd " . $projectDir . " && php -S localhost:8000 -t public\n";
    }
    
    /**
     * Check if Symfony CLI is available.
     */
    private function isSymfonyCliAvailable(): bool
    {
        $process = new Process(['symfony', '--version']);
        $process->run();
        return $process->isSuccessful();
    }
    
    /**
     * Set up Symfony using Symfony CLI.
     */
    private function setupWithSymfonyCli(string $projectDir): void
    {
        echo "Using Symfony CLI for setup...\n";
        
        $command = [
            'symfony',
            'new',
            '--full',
            '--version=' . $this->config['symfony_version'],
        ];
        
        // If the directory already exists, we need to specify --dir
        if ($this->filesystem->exists($projectDir)) {
            $command[] = '--dir=' . $projectDir;
        } else {
            $command[] = $this->config['project_name'];
        }
        
        $this->runCommand($command);
    }
    
    /**
     * Set up Symfony using Composer.
     */
    private function setupWithComposer(string $projectDir): void
    {
        echo "Using Composer for setup...\n";
        
        $command = [
            'composer',
            'create-project',
            'symfony/skeleton:^' . $this->config['symfony_version'],
            $projectDir,
        ];
        
        $this->runCommand($command);
        
        // Install the webapp recipe for a full-stack application
        $this->runCommand([
            'composer',
            'require',
            'symfony/webapp-pack',
            '--working-dir=' . $projectDir,
        ]);
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
        
        $envFile = $projectDir . '/.env.local';
        $envContent = '';
        
        // Add database URL
        $envContent .= 'DATABASE_URL="' . $this->config['database_url'] . "\"\n";
        
        // Add mailer DSN
        $envContent .= 'MAILER_DSN="' . $this->config['mailer_dsn'] . "\"\n";
        
        // Add APP_ENV if not in production
        if ($this->getConfig('env', 'dev') !== 'prod') {
            $envContent .= "APP_ENV=dev\n";
        }
        
        // Write to .env.local
        file_put_contents($envFile, $envContent);
    }
}