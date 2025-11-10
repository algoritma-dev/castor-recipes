# Castor Recipes (Composer Plugin)

Composer plugin that installs ready-to-use recipes for [Castor](https://castor.jolicode.com) for various PHP frameworks/platforms.
During installation it asks which recipe to use and:
- creates a `castor.php` file in your project root with a `require` to the selected recipe if it does not exist;
- if `castor.php` already exists, it shows the instructions to add the `require` manually.

## Requirements
- PHP >= 8.2
- Composer 2
- [jolicode/castor](https://github.com/jolicode/Castor) (Below for quick installation)
- Optional Docker (to run tasks in containers)

## Castor installation

```bash
 curl "https://castor.jolicode.com/install" | bash -s -- --static
```

## Castor enable autocompletion

To show how to enable autocompletion, run:

```bash
castor completion --help
```

Then reload your shell.

## Supported Platforms/Frameworks
- Symfony (`recipes/symfony.php`)
- Laravel (`recipes/laravel.php`)
- Shopware6 (`recipes/shopware6.php`)
- OroCommerce (`recipes/orocommerce.php`)
- Magento 2 (`recipes/magento2.php`)
- WordPress (`recipes/wordpress.php`)

## Installation
In your existing project:

```bash
composer require --dev algoritma/castor-recipes
```

During installation you will be asked to choose a recipe. If `castor.php` does not exist, it will be created automatically with the correct `require`, for example:

```php
<?php
require __DIR__ . '/vendor/algoritma/castor-recipes/recipes/symfony.php';
```

If the file already exists, instructions will be printed to add the `require` line manually.

## Usage
List available tasks:

```bash
castor list
```

## Running in Docker (optional)
Recipes can run locally or inside a Docker container depending on environment variables.

- Set `CASTOR_DOCKER=1` to use Docker.
- Docker Compose service to use: `DOCKER_SERVICE` (default: `php`).
- Compose file: `DOCKER_COMPOSE_FILE` (default: `docker-compose.yml`).

Examples:

```bash
# Local execution (default)
castor sf_test

# Using Docker Compose (service "php")
CASTOR_DOCKER=1 DOCKER_SERVICE=php castor sf_test

# Using an alternative compose file
CASTOR_DOCKER=1 DOCKER_COMPOSE_FILE=docker/docker-compose.yml castor laravel_test
```

## Adding multiple recipes
You can include multiple recipes at the same time by editing `castor.php` and adding more `require` lines:

```php
<?php
require __DIR__ . '/vendor/algoritma/castor-recipes/recipes/symfony.php';
require __DIR__ . '/vendor/algoritma/castor-recipes/recipes/laravel.php';
```

## Notes
- Specific commands (e.g., `bin/console`, `php artisan`, `bin/magento`, `wp`) must be available in the project/in the container.
- The recipes are basic examples: adapt the tasks to your project's needs.

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