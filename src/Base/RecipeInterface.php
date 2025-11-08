<?php

declare(strict_types=1);

namespace CastorRecipes\Base;

/**
 * Interface for all Castor recipes.
 */
interface RecipeInterface
{
    /**
     * Get the name of the recipe.
     */
    public function getName(): string;

    /**
     * Get the description of the recipe.
     */
    public function getDescription(): string;

    /**
     * Configure the recipe.
     */
    public function configure(): void;

    /**
     * Execute the recipe.
     */
    public function execute(): int;

    /**
     * Check if the recipe can be executed in the current environment.
     */
    public function canExecute(): bool;

    /**
     * Get the platform this recipe is for.
     */
    public function getPlatform(): string;

    /**
     * Get the requirements for this recipe.
     *
     * @return array<string, string>
     */
    public function getRequirements(): array;
}