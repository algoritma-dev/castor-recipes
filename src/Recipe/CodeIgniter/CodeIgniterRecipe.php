<?php

declare(strict_types=1);

namespace CastorRecipes\Recipe\CodeIgniter;

use CastorRecipes\Base\AbstractRecipe;

/**
 * Recipe for CodeIgniter 4 setup and development.
 */
class CodeIgniterRecipe extends AbstractRecipe
{
    public function __construct()
    {
        parent::__construct(
            'codeigniter',
            'CodeIgniter 4 setup and development environment',
            'CodeIgniter'
        );

        $this->requirements = [
            'php' => '8.1.0',
            'composer' => '2.0.0',
        ];
    }

    public function configure(): void
    {
        $this->config = array_merge($this->config, [
            'codeigniter_version' => $this->getConfig('codeigniter_version', '4.*'),
            'project_name' => $this->getConfig('project_name', 'ci4-app'),
            'project_dir' => $this->getConfig('project_dir', getcwd() . '/' . $this->getConfig('project_name', 'ci4-app')),
            'base_url' => $this->getConfig('base_url', 'http://localhost:8080/'),
            'env' => $this->getConfig('env', 'development'),
        ]);
    }

    protected function runRecipe(): void
    {
        echo "Setting up CodeIgniter 4 environment...\n";

        $projectDir = (string) $this->getConfig('project_dir', getcwd() . '/' . $this->config['project_name']);

        // Create project from appstarter
        $this->runCommand([
            'composer',
            'create-project',
            'codeigniter4/appstarter:' . $this->config['codeigniter_version'],
            $projectDir,
        ]);

        // Prepare .env file
        $envFile = $projectDir . '/.env';
        $env = "CI_ENVIRONMENT=" . $this->config['env'] . "\n";
        $env .= 'app.baseURL="' . rtrim((string) $this->config['base_url'], '/') . '/"' . "\n";
        @file_put_contents($envFile, $env);

        echo "CodeIgniter 4 setup completed.\n";
        echo "Next steps:\n";
        echo "  - Start the built-in server: php spark serve\n";
    }
}
