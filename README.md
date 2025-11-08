# Castor Recipes

A collection of ready-to-use Castor recipes for various platforms and frameworks. This project provides a standardized way to create, share, and execute Castor recipes for different PHP platforms and frameworks.

## Supported Platforms

Currently available:
- WordPress
- Symfony
- Laravel
- TYPO3
- CodeIgniter
- Magento 2
- Shopware
- OroCommerce

Planned (not yet implemented):
- (none for now)

## Requirements

- PHP 8.1 or higher
- Composer 2.0 or higher
- Docker (optional, for containerized execution)

## Features

- Ready-to-use recipes for popular PHP platforms and frameworks
- Interactive recipe installer (Composer script and Castor task)
- Support for both Docker and direct shell execution
- Extensible architecture for custom recipes

## Installation

```bash
composer create-project raffaelecarelle/castor-recipes
cd castor-recipes
composer install
```

Interactive installer (Composer script):

```bash
composer run-script install-recipe
```

## Usage

### List available recipes

```bash
vendor/bin/castor recipes:list
```

### Run a recipe (host shell)

```bash
# WordPress
vendor/bin/castor run:wordpress

# Symfony
vendor/bin/castor run:symfony

# Laravel
vendor/bin/castor run:laravel

# TYPO3
vendor/bin/castor run:typo3

# CodeIgniter
vendor/bin/castor run:codeigniter

# Magento 2
vendor/bin/castor run:magento2

# Shopware
vendor/bin/castor run:shopware

# OroCommerce
vendor/bin/castor run:orocommerce
```

### Interactive installer

You can run the interactive installer either via Castor or via Composer script/bin:

```bash
# Using Castor
composer install  # ensure dependencies are installed first
vendor/bin/castor install:interactive

# Using Composer script
composer run-script install-recipe

# Using the bin helper
./bin/install-recipe
```

### Using Docker

```bash
# Start the Docker environment
docker-compose up -d

# List recipes
docker-compose exec app vendor/bin/castor recipes:list

# Run a recipe
docker-compose exec app vendor/bin/castor run:wordpress
```

## Configuration

Recipes can be configured using environment variables or a YAML configuration file. Configuration is read in this order:
1) Environment variables with prefix `CASTOR_`
2) YAML file specified by env var `CASTOR_CONFIG_FILE`
3) `castor.yaml` in the current working directory

Examples:

```bash
# Using environment variables (prefix CASTOR_)
CASTOR_ENV=dev vendor/bin/castor run:wordpress
CASTOR_PROJECT_NAME=my-app vendor/bin/castor run:symfony

# Using a configuration file (YAML)
export CASTOR_CONFIG_FILE=my-config.yaml
vendor/bin/castor run:laravel
```

Example `castor.yaml` (global defaults):

```yaml
# Global defaults
env: dev
project_dir: ./my-project
```

Symfony recipe example overrides:

```yaml
symfony_version: "6.3"
project_name: "symfony-app"
database_url: "mysql://root:root@127.0.0.1:3306/symfony?serverVersion=8.0"
mailer_dsn: "smtp://localhost:1025"
```

Laravel recipe example overrides:

```yaml
laravel_version: "10.x"
project_name: "laravel-app"
database_connection: mysql
database_host: 127.0.0.1
database_port: "3306"
database_name: laravel
database_user: root
database_password: root
```

WordPress recipe example overrides:

```yaml
wp_version: latest
db_name: wordpress
db_user: wordpress
db_password: wordpress
db_host: localhost
site_url: http://localhost:8080
admin_user: admin
admin_password: password
admin_email: admin@example.com
```

## Creating Custom Recipes

You can extend existing recipes or create new ones:

```php
// custom/MyCustomRecipe.php
namespace Custom;

use CastorRecipes\Base\AbstractRecipe;

class MyCustomRecipe extends AbstractRecipe
{
    public function configure(): void
    {
        // Your custom configuration
    }
}
```

## Recipe Architecture

Recipes in this project follow a standardized architecture:

- **RecipeInterface**: Defines the contract that all recipes must implement
- **AbstractRecipe**: Provides common functionality for all recipes
- **RecipeRegistry**: Manages recipe registration and discovery

Each recipe consists of:
1. **Configuration**: Define parameters and requirements
2. **Execution**: Implement the actual recipe logic
3. **Validation**: Check if the recipe can be executed in the current environment

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run code quality tools
composer quality
```

## Testing

Run the test suite and, optionally, generate a coverage report:

```bash
# Run tests
composer test

# Run tests with coverage report (requires Xdebug or PCOV)
vendor/bin/phpunit --coverage-html coverage

# View the coverage report (macOS)
open coverage/index.html
```

## Continuous Integration

Planned: GitHub Actions workflows to test on multiple PHP versions (8.1–8.5) with the following steps:
- PHPUnit tests
- PHPStan static analysis
- PHP CS Fixer code style checks
- Optional code coverage report

Note: Workflows are not yet included in this repository. Contributions are welcome.

## License

MIT
