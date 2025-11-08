<?php

declare(strict_types=1);

namespace CastorRecipes\Recipe\WordPress;

use CastorRecipes\Base\AbstractRecipe;
use Symfony\Component\Process\Process;

/**
 * Recipe for WordPress setup and development.
 */
class WordPressRecipe extends AbstractRecipe
{
    public function __construct()
    {
        parent::__construct(
            'wordpress',
            'WordPress setup and development environment',
            'WordPress'
        );
        
        $this->requirements = [
            'php' => '8.1.0',
            'composer' => '2.0.0',
        ];
    }
    
    public function configure(): void
    {
        // Configure WordPress-specific settings
        $this->config = array_merge($this->config, [
            'wp_version' => $this->getConfig('wp_version', 'latest'),
            'db_name' => $this->getConfig('db_name', 'wordpress'),
            'db_user' => $this->getConfig('db_user', 'wordpress'),
            'db_password' => $this->getConfig('db_password', 'wordpress'),
            'db_host' => $this->getConfig('db_host', 'localhost'),
            'wp_debug' => $this->getConfig('wp_debug', true),
            'site_url' => $this->getConfig('site_url', 'http://localhost:8080'),
            'admin_user' => $this->getConfig('admin_user', 'admin'),
            'admin_password' => $this->getConfig('admin_password', 'password'),
            'admin_email' => $this->getConfig('admin_email', 'admin@example.com'),
            'plugins' => $this->getConfig('plugins', [
                'advanced-custom-fields',
                'woocommerce',
                'wordpress-seo',
            ]),
            'themes' => $this->getConfig('themes', []),
        ]);
    }
    
    protected function runRecipe(): void
    {
        echo "Setting up WordPress environment...\n";
        
        // Create project directory if it doesn't exist
        $projectDir = $this->getConfig('project_dir', getcwd() . '/wordpress');
        if (!$this->filesystem->exists($projectDir)) {
            $this->filesystem->mkdir($projectDir);
        }
        
        // Download WordPress using WP-CLI if available, otherwise use direct download
        if ($this->isWpCliAvailable()) {
            $this->setupWithWpCli($projectDir);
        } else {
            $this->setupWithDirectDownload($projectDir);
        }
        
        echo "WordPress setup completed successfully!\n";
        echo "You can access your WordPress site at: " . $this->config['site_url'] . "\n";
        echo "Admin username: " . $this->config['admin_user'] . "\n";
        echo "Admin password: " . $this->config['admin_password'] . "\n";
    }
    
    /**
     * Check if WP-CLI is available.
     */
    private function isWpCliAvailable(): bool
    {
        $process = new Process(['wp', '--info']);
        $process->run();
        return $process->isSuccessful();
    }
    
    /**
     * Set up WordPress using WP-CLI.
     */
    private function setupWithWpCli(string $projectDir): void
    {
        echo "Using WP-CLI for WordPress setup...\n";
        
        // Download WordPress core
        $this->runCommand([
            'wp', 'core', 'download',
            '--version=' . $this->config['wp_version'],
            '--path=' . $projectDir,
        ]);
        
        // Create wp-config.php
        $this->runCommand([
            'wp', 'config', 'create',
            '--dbname=' . $this->config['db_name'],
            '--dbuser=' . $this->config['db_user'],
            '--dbpass=' . $this->config['db_password'],
            '--dbhost=' . $this->config['db_host'],
            '--path=' . $projectDir,
        ]);
        
        // Install WordPress
        $this->runCommand([
            'wp', 'core', 'install',
            '--url=' . $this->config['site_url'],
            '--title=WordPress Site',
            '--admin_user=' . $this->config['admin_user'],
            '--admin_password=' . $this->config['admin_password'],
            '--admin_email=' . $this->config['admin_email'],
            '--path=' . $projectDir,
        ]);
        
        // Install plugins
        foreach ($this->config['plugins'] as $plugin) {
            $this->runCommand([
                'wp', 'plugin', 'install', $plugin, '--activate',
                '--path=' . $projectDir,
            ]);
        }
        
        // Install themes
        foreach ($this->config['themes'] as $theme) {
            $this->runCommand([
                'wp', 'theme', 'install', $theme,
                '--path=' . $projectDir,
            ]);
        }
    }
    
    /**
     * Set up WordPress using direct download.
     */
    private function setupWithDirectDownload(string $projectDir): void
    {
        echo "Using direct download for WordPress setup...\n";
        
        // Download WordPress
        $wpZipUrl = 'https://wordpress.org/latest.zip';
        if ($this->config['wp_version'] !== 'latest') {
            $wpZipUrl = "https://wordpress.org/wordpress-{$this->config['wp_version']}.zip";
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'wp_');
        file_put_contents($tempFile, file_get_contents($wpZipUrl));
        
        // Extract WordPress
        $zip = new \ZipArchive();
        if ($zip->open($tempFile) === true) {
            $zip->extractTo(dirname($projectDir));
            $zip->close();
            
            // Move files if needed
            if (basename($projectDir) !== 'wordpress') {
                $this->filesystem->rename(
                    dirname($projectDir) . '/wordpress',
                    $projectDir
                );
            }
        }
        
        unlink($tempFile);
        
        // Create wp-config.php
        $wpConfigSample = $projectDir . '/wp-config-sample.php';
        $wpConfig = $projectDir . '/wp-config.php';
        
        if ($this->filesystem->exists($wpConfigSample)) {
            $configContent = file_get_contents($wpConfigSample);
            
            // Replace database settings
            $configContent = str_replace('database_name_here', $this->config['db_name'], $configContent);
            $configContent = str_replace('username_here', $this->config['db_user'], $configContent);
            $configContent = str_replace('password_here', $this->config['db_password'], $configContent);
            $configContent = str_replace('localhost', $this->config['db_host'], $configContent);
            
            // Add debug settings
            $debugMode = $this->config['wp_debug'] ? 'true' : 'false';
            $configContent = str_replace(
                "define( 'WP_DEBUG', false );",
                "define( 'WP_DEBUG', {$debugMode} );",
                $configContent
            );
            
            file_put_contents($wpConfig, $configContent);
        }
        
        echo "WordPress has been downloaded and configured.\n";
        echo "You will need to complete the installation by visiting your site in a browser.\n";
    }
}