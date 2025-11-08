<?php

declare(strict_types=1);

namespace CastorRecipes\Recipe\TYPO3;

use CastorRecipes\Base\AbstractRecipe;

/**
 * Recipe for TYPO3 setup and development.
 */
class Typo3Recipe extends AbstractRecipe
{
    public function __construct()
    {
        parent::__construct(
            'typo3',
            'TYPO3 setup and development environment',
            'TYPO3'
        );

        $this->requirements = [
            'php' => '8.1.0',
            'composer' => '2.0.0',
        ];
    }

    public function configure(): void
    {
        $this->config = array_merge($this->config, [
            'typo3_version' => $this->getConfig('typo3_version', '12.*'),
            'project_name' => $this->getConfig('project_name', 'typo3-app'),
            'project_dir' => $this->getConfig('project_dir', getcwd() . '/' . $this->getConfig('project_name', 'typo3-app')),
            'db_name' => $this->getConfig('db_name', 'typo3'),
            'db_user' => $this->getConfig('db_user', 'root'),
            'db_password' => $this->getConfig('db_password', 'root'),
            'db_host' => $this->getConfig('db_host', '127.0.0.1'),
            'db_port' => $this->getConfig('db_port', '3306'),
        ]);
    }

    protected function runRecipe(): void
    {
        echo "Setting up TYPO3 environment...\n";

        $projectDir = (string) $this->getConfig('project_dir', getcwd() . '/' . $this->config['project_name']);

        // Create a new TYPO3 project using the base distribution
        $this->runCommand([
            'composer',
            'create-project',
            'typo3/cms-base-distribution:' . $this->config['typo3_version'],
            $projectDir,
        ]);

        // Create .env.local with DB settings if possible (since TYPO3 uses env or LocalConfiguration)
        $envFile = $projectDir . '/.env.local';
        $env = '';
        $env .= 'DB_DRIVER=mysqli' . "\n";
        $env .= 'DB_HOST=' . $this->config['db_host'] . "\n";
        $env .= 'DB_PORT=' . $this->config['db_port'] . "\n";
        $env .= 'DB_NAME=' . $this->config['db_name'] . "\n";
        $env .= 'DB_USER=' . $this->config['db_user'] . "\n";
        $env .= 'DB_PASSWORD=' . $this->config['db_password'] . "\n";
        @file_put_contents($envFile, $env);

        echo "TYPO3 setup completed.\n";
        echo "Next steps:\n";
        echo "  - Configure web server to point to public/ directory\n";
        echo "  - Visit the installer in your browser to finalize setup\n";
    }
}
