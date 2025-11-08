<?php

declare(strict_types=1);

namespace CastorRecipes\Tests\Base;

use CastorRecipes\Base\RecipeInterface;
use CastorRecipes\Base\RecipeRegistry;
use PHPUnit\Framework\TestCase;

class RecipeRegistryTest extends TestCase
{
    private RecipeRegistry $registry;
    
    protected function setUp(): void
    {
        $this->registry = new RecipeRegistry();
    }
    
    public function testRegisterAndGet(): void
    {
        $recipe = $this->createMock(RecipeInterface::class);
        $recipe->method('getName')->willReturn('test-recipe');
        
        $this->registry->register($recipe);
        
        $this->assertSame($recipe, $this->registry->get('test-recipe'));
    }
    
    public function testGetNonExistentRecipe(): void
    {
        $this->assertNull($this->registry->get('non-existent'));
    }
    
    public function testAll(): void
    {
        $recipe1 = $this->createMock(RecipeInterface::class);
        $recipe1->method('getName')->willReturn('recipe1');
        
        $recipe2 = $this->createMock(RecipeInterface::class);
        $recipe2->method('getName')->willReturn('recipe2');
        
        $this->registry->register($recipe1);
        $this->registry->register($recipe2);
        
        $all = $this->registry->all();
        
        $this->assertCount(2, $all);
        $this->assertSame($recipe1, $all['recipe1']);
        $this->assertSame($recipe2, $all['recipe2']);
    }
    
    public function testForPlatform(): void
    {
        $recipe1 = $this->createMock(RecipeInterface::class);
        $recipe1->method('getName')->willReturn('recipe1');
        $recipe1->method('getPlatform')->willReturn('platform1');
        
        $recipe2 = $this->createMock(RecipeInterface::class);
        $recipe2->method('getName')->willReturn('recipe2');
        $recipe2->method('getPlatform')->willReturn('platform2');
        
        $recipe3 = $this->createMock(RecipeInterface::class);
        $recipe3->method('getName')->willReturn('recipe3');
        $recipe3->method('getPlatform')->willReturn('platform1');
        
        $this->registry->register($recipe1);
        $this->registry->register($recipe2);
        $this->registry->register($recipe3);
        
        $platform1Recipes = $this->registry->forPlatform('platform1');
        
        $this->assertCount(2, $platform1Recipes);
        $this->assertSame($recipe1, $platform1Recipes['recipe1']);
        $this->assertSame($recipe3, $platform1Recipes['recipe3']);
    }
    
    public function testGetPlatforms(): void
    {
        $recipe1 = $this->createMock(RecipeInterface::class);
        $recipe1->method('getName')->willReturn('recipe1');
        $recipe1->method('getPlatform')->willReturn('platform1');
        
        $recipe2 = $this->createMock(RecipeInterface::class);
        $recipe2->method('getName')->willReturn('recipe2');
        $recipe2->method('getPlatform')->willReturn('platform2');
        
        $recipe3 = $this->createMock(RecipeInterface::class);
        $recipe3->method('getName')->willReturn('recipe3');
        $recipe3->method('getPlatform')->willReturn('platform1');
        
        $this->registry->register($recipe1);
        $this->registry->register($recipe2);
        $this->registry->register($recipe3);
        
        $platforms = $this->registry->getPlatforms();
        
        $this->assertCount(2, $platforms);
        $this->assertContains('platform1', $platforms);
        $this->assertContains('platform2', $platforms);
    }
    
    public function testGetInstance(): void
    {
        $instance1 = RecipeRegistry::getInstance();
        $instance2 = RecipeRegistry::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }
}