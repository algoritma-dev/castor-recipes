<?php

declare(strict_types=1);

namespace CastorRecipes\Recipe\OroCommerce;

use CastorRecipes\Base\AbstractRecipe;

/**
 * Recipe for OroCommerce setup and development.
 */
class OroCommerceRecipe extends AbstractRecipe
{
    public function __construct()
    {
        parent::__construct(
            'orocommerce',
            'OroCommerce setup and development environment',
            'OroCommerce'
        );

        $this->requirements = [
            'php' => '8.1.0',
            'composer' => '2.0.0',
        ];
    }

    public function configure(): void
    {
        $this->config = array_merge($this->config, [
            'orocommerce_version' => $this->getConfig('orocommerce_version', '5.*'),
            'project_name' => $this->getConfig('project_name', 'orocommerce-app'),
            'project_dir' => $this->getConfig('project_dir', getcwd() . '/' . $this->getConfig('project_name', 'orocommerce-app')),
            'db_host' => $this->getConfig('db_host', '127.0.0.1'),
            'db_name' => $this->getConfig('db_name', 'oro'),
            'db_user' => $this->getConfig('db_user', 'root'),
            'db_password' => $this->getConfig('db_password', 'root'),
            'db_port' => $this->getConfig('db_port', '3306'),
        ]);
    }

    protected function runRecipe(): void
    {
        echo "Setting up OroCommerce environment...\n";

        $projectDir = (string) $this->getConfig('project_dir', getcwd() . '/' . $this->config['project_name']);

        // Create OroCommerce platform application
        $this->runCommand([
            'composer',
            'create-project',
            'oro/platform-application:' . $this->config['orocommerce_version'],
            $projectDir,
        ]);

        echo "OroCommerce project created.\n";
        echo "Next steps (from project root):\n";
        echo "  php bin/console oro:install --env=prod \\\n  --application-url=\"http://localhost/\" \\\n  --database-host=\"{$this->config['db_host']}\" \\\n  --database-port=\"{$this->config['db_port']}\" \\\n  --database-name=\"{$this->config['db_name']}\" \\\n  --database-user=\"{$this->config['db_user']}\" \\\n  --database-password=\"{$this->config['db_password']}\" \\\n  --user-email=admin@example.com --user-firstname=Admin --user-lastname=User --user-name=admin --user-password=Admin123!\n";
    }
}
