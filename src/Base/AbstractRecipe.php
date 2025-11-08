<?php

declare(strict_types=1);

namespace CastorRecipes\Base;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Abstract base class for all Castor recipes.
 */
abstract class AbstractRecipe implements RecipeInterface
{
    protected string $name;
    protected string $description;
    protected string $platform;
    
    /**
     * @var array<string, string>
     */
    protected array $requirements = [];
    
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];
    
    protected Filesystem $filesystem;
    protected Finder $finder;
    
    public function __construct(string $name, string $description, string $platform)
    {
        $this->name = $name;
        $this->description = $description;
        $this->platform = $platform;
        $this->filesystem = new Filesystem();
        $this->finder = new Finder();
        
        $this->loadConfig();
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getDescription(): string
    {
        return $this->description;
    }
    
    public function getPlatform(): string
    {
        return $this->platform;
    }
    
    public function getRequirements(): array
    {
        return $this->requirements;
    }
    
    public function canExecute(): bool
    {
        // Check if all requirements are met
        foreach ($this->requirements as $requirement => $version) {
            if (!$this->checkRequirement($requirement, $version)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function execute(): int
    {
        if (!$this->canExecute()) {
            echo "Cannot execute recipe: requirements not met.\n";
            return 1;
        }
        
        try {
            $this->configure();
            $this->runRecipe();
            return 0;
        } catch (\Exception $e) {
            echo "Error executing recipe: " . $e->getMessage() . "\n";
            return 1;
        }
    }
    
    /**
     * Run the recipe implementation.
     */
    abstract protected function runRecipe(): void;
    
    /**
     * Check if a specific requirement is met.
     */
    protected function checkRequirement(string $requirement, string $version): bool
    {
        // Implementation for checking requirements
        // This is a simplified version, real implementation would check actual versions
        
        switch ($requirement) {
            case 'php':
                return version_compare(PHP_VERSION, $version, '>=');
            
            case 'docker':
                $process = new Process(['docker', '--version']);
                $process->run();
                return $process->isSuccessful();
            
            case 'composer':
                $process = new Process(['composer', '--version']);
                $process->run();
                return $process->isSuccessful();
            
            default:
                // For other requirements, assume they are met
                return true;
        }
    }
    
    /**
     * Load configuration from environment variables or config file.
     */
    protected function loadConfig(): void
    {
        // Load from environment variables
        $this->loadConfigFromEnv();
        
        // Load from config file if it exists
        $configFile = getenv('CASTOR_CONFIG_FILE') ?: 'castor.yaml';
        if (file_exists($configFile)) {
            $this->loadConfigFromFile($configFile);
        }
    }
    
    /**
     * Load configuration from environment variables.
     */
    protected function loadConfigFromEnv(): void
    {
        $prefix = 'CASTOR_';
        foreach ($_ENV as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $configKey = strtolower(substr($key, strlen($prefix)));
                $this->config[$configKey] = $value;
            }
        }
    }
    
    /**
     * Load configuration from a file.
     */
    protected function loadConfigFromFile(string $file): void
    {
        if (!file_exists($file)) {
            return;
        }
        
        $config = Yaml::parseFile($file);
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }
    
    /**
     * Get a configuration value.
     *
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Run a shell command.
     */
    protected function runCommand(array $command, string $cwd = null): Process
    {
        $process = new Process($command, $cwd);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        
        return $process;
    }
}