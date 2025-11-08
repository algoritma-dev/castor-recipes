<?php

declare(strict_types=1);

namespace CastorRecipes\Base;

/**
 * Registry for all available recipes.
 */
class RecipeRegistry
{
    /**
     * @var array<string, RecipeInterface>
     */
    private array $recipes = [];

    /**
     * Register a recipe.
     */
    public function register(RecipeInterface $recipe): self
    {
        $this->recipes[$recipe->getName()] = $recipe;
        return $this;
    }

    /**
     * Get a recipe by name.
     */
    public function get(string $name): ?RecipeInterface
    {
        return $this->recipes[$name] ?? null;
    }

    /**
     * Get all recipes.
     *
     * @return array<string, RecipeInterface>
     */
    public function all(): array
    {
        return $this->recipes;
    }

    /**
     * Get recipes for a specific platform.
     *
     * @return array<string, RecipeInterface>
     */
    public function forPlatform(string $platform): array
    {
        return array_filter(
            $this->recipes,
            fn(RecipeInterface $recipe) => $recipe->getPlatform() === $platform
        );
    }

    /**
     * Get all available platforms.
     *
     * @return array<string>
     */
    public function getPlatforms(): array
    {
        $platforms = [];
        foreach ($this->recipes as $recipe) {
            $platforms[$recipe->getPlatform()] = true;
        }
        
        return array_keys($platforms);
    }

    /**
     * Execute a recipe by name.
     */
    public function execute(string $name): int
    {
        $recipe = $this->get($name);
        if ($recipe === null) {
            echo "Recipe not found: $name\n";
            return 1;
        }

        return $recipe->execute();
    }

    /**
     * Get a singleton instance of the registry.
     */
    public static function getInstance(): self
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        
        return $instance;
    }
}