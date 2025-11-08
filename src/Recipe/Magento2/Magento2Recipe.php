<?php

declare(strict_types=1);

namespace CastorRecipes\Recipe\Magento2;

use CastorRecipes\Base\AbstractRecipe;

/**
 * Recipe for Magento 2 setup and development.
 * Note: Installing Magento requires Composer auth tokens for repo.magento.com.
 */
class Magento2Recipe extends AbstractRecipe
{
    public function __construct()
    {
        parent::__construct(
            'magento2',
            'Magento 2 setup and development environment',
            'Magento 2'
        );

        $this->requirements = [
            'php' => '8.1.0',
            'composer' => '2.0.0',
        ];
    }

    public function configure(): void
    {
        $this->config = array_merge($this->config, [
            'magento_version' => $this->getConfig('magento_version', '2.4.*'),
            'project_name' => $this->getConfig('project_name', 'magento2-app'),
            'project_dir' => $this->getConfig('project_dir', getcwd() . '/' . $this->getConfig('project_name', 'magento2-app')),
            'base_url' => $this->getConfig('base_url', 'http://localhost/'),
            'db_host' => $this->getConfig('db_host', '127.0.0.1'),
            'db_name' => $this->getConfig('db_name', 'magento'),
            'db_user' => $this->getConfig('db_user', 'root'),
            'db_password' => $this->getConfig('db_password', 'root'),
            'db_prefix' => $this->getConfig('db_prefix', ''),
            'admin_user' => $this->getConfig('admin_user', 'admin'),
            'admin_password' => $this->getConfig('admin_password', 'Admin123!'),
            'admin_email' => $this->getConfig('admin_email', 'admin@example.com'),
            'admin_firstname' => $this->getConfig('admin_firstname', 'Admin'),
            'admin_lastname' => $this->getConfig('admin_lastname', 'User'),
        ]);
    }

    protected function runRecipe(): void
    {
        echo "Setting up Magento 2 environment...\n";

        $projectDir = (string) $this->getConfig('project_dir', getcwd() . '/' . $this->config['project_name']);

        // Check for Composer auth for repo.magento.com
        $hasAuth = $this->hasMagentoAuth();
        if (!$hasAuth) {
            echo "WARNING: Composer auth for repo.magento.com not found.\n";
            echo "Set credentials via COMPOSER_AUTH or ~/.composer/auth.json (http-basic repo.magento.com).\n";
        }

        // Create Magento project
        $this->runCommand([
            'composer',
            'create-project',
            'magento/project-community-edition:' . $this->config['magento_version'],
            $projectDir,
        ]);

        echo "Magento 2 project created.\n";
        echo "Next steps (from project root):\n";
        echo "  bin/magento setup:install \\\n  --base-url=\"{$this->config['base_url']}\" \\\n  --db-host=\"{$this->config['db_host']}\" \\\n  --db-name=\"{$this->config['db_name']}\" \\\n  --db-user=\"{$this->config['db_user']}\" \\\n  --db-password=\"{$this->config['db_password']}\" \\\n  --db-prefix=\"{$this->config['db_prefix']}\" \\\n  --admin-firstname=\"{$this->config['admin_firstname']}\" \\\n  --admin-lastname=\"{$this->config['admin_lastname']}\" \\\n  --admin-email=\"{$this->config['admin_email']}\" \\\n  --admin-user=\"{$this->config['admin_user']}\" \\\n  --admin-password=\"{$this->config['admin_password']}\" \\\n  --backend-frontname=admin \\\n  --language=en_US \\\n  --currency=USD \\\n  --timezone=UTC \\\n  --use-rewrites=1\n";
    }

    private function hasMagentoAuth(): bool
    {
        // Check COMPOSER_AUTH env
        if (!empty(getenv('COMPOSER_AUTH'))) {
            return true;
        }
        // Check typical auth.json locations
        $paths = [
            getenv('HOME') . '/.composer/auth.json',
            getenv('HOME') . '/.config/composer/auth.json',
            getcwd() . '/auth.json',
        ];
        foreach ($paths as $p) {
            if ($p && file_exists($p)) {
                $content = @file_get_contents($p) ?: '';
                if (str_contains($content, 'repo.magento.com')) {
                    return true;
                }
            }
        }
        return false;
    }
}
