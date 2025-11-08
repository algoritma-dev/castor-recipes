<?php

declare(strict_types=1);

namespace CastorRecipes\Recipe\Shopware;

use CastorRecipes\Base\AbstractRecipe;

/**
 * Recipe for Shopware setup and development.
 */
class ShopwareRecipe extends AbstractRecipe
{
    public function __construct()
    {
        parent::__construct(
            'shopware',
            'Shopware setup and development environment',
            'Shopware'
        );

        $this->requirements = [
            'php' => '8.1.0',
            'composer' => '2.0.0',
        ];
    }

    public function configure(): void
    {
        $this->config = array_merge($this->config, [
            'shopware_version' => $this->getConfig('shopware_version', '6.*'),
            'project_name' => $this->getConfig('project_name', 'shopware-app'),
            'project_dir' => $this->getConfig('project_dir', getcwd() . '/' . $this->getConfig('project_name', 'shopware-app')),
            'db_host' => $this->getConfig('db_host', '127.0.0.1'),
            'db_name' => $this->getConfig('db_name', 'shopware'),
            'db_user' => $this->getConfig('db_user', 'root'),
            'db_password' => $this->getConfig('db_password', 'root'),
        ]);
    }

    protected function runRecipe(): void
    {
        echo "Setting up Shopware environment...\n";

        $projectDir = (string) $this->getConfig('project_dir', getcwd() . '/' . $this->config['project_name']);

        // Create Shopware production template
        $this->runCommand([
            'composer',
            'create-project',
            'shopware/production:' . $this->config['shopware_version'],
            $projectDir,
        ]);

        echo "Shopware project created.\n";
        echo "Next steps (from project root):\n";
        echo "  bin/console system:install --create-database --basic-setup \\\n  --database-url=\"mysql://{$this->config['db_user']}:{$this->config['db_password']}@{$this->config['db_host']}:3306/{$this->config['db_name']}\"\n";
        echo "For admin UI and storefront build, Node.js and package manager (npm/yarn) are recommended.\n";
    }
}
