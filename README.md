# Castor Recipes (Composer Plugin)

Composer plugin that installs ready-to-use recipes for [Castor](https://castor.jolicode.com) for various PHP frameworks/platforms.
During installation it asks which recipe to use and:
- creates a `castor.php` file in your project root with a `require` to the selected recipe if it does not exist;
- if `castor.php` already exists, it shows the instructions to add the `require` manually.

## Requirements
- PHP >= 8.2
- Composer 2
- [jolicode/castor](https://github.com/jolicode/Castor) (installed as a dependency of this plugin)
- Optional Docker (to run tasks in containers)

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
composer require --dev raffaelecarelle/castor-recipes
```

During installation you will be asked to choose a recipe. If `castor.php` does not exist, it will be created automatically with the correct `require`, for example:

```php
<?php
require __DIR__ . '/vendor/raffaelecarelle/castor-recipes/recipes/symfony.php';
```

If the file already exists, instructions will be printed to add the `require` line manually.

## Usage
List available tasks:

```bash
vendor/bin/castor
```

Examples of included tasks (not exhaustive):
- Symfony: `sf_install`, `sf_serve`, `sf_serve_stop`, `sf_migrate`, `sf_migrate_diff`, `sf_migrate_fresh`, `sf_db_create`, `sf_db_drop`, `sf_db_reset`, `sf_fixtures_load`, `sf_cache_clear`, `sf_cache_warmup`, `sf_cache_clear_warmup`, `sf_assets_install`, `sf_lint_yaml`, `sf_lint_twig`, `sf_lint_container`, `sf_lint_all`, `sf_messenger_consume`, `sf_logs_tail`, `sf_test`, `sf_console`, `sf_setup`, `sf_ci`
- Laravel: `laravel_install`, `laravel_serve`, `laravel_migrate`, `laravel_seed`, `laravel_migrate_seed`, `laravel_migrate_fresh`, `laravel_key_generate`, `laravel_cache`, `laravel_cache_clear_all`, `laravel_config_cache`, `laravel_route_cache`, `laravel_event_cache`, `laravel_test`, `laravel_queue`, `laravel_queue_restart`, `laravel_queue_listen`, `laravel_schedule_run`, `laravel_storage_link`, `laravel_tinker`, `laravel_logs_tail`, `laravel_artisan`, `laravel_setup`, `laravel_ci`
- Shopware: `shopware_install`, `shopware_system_install` (alias: `shopware_setup`), `shopware_cache_clear`, `shopware_build`, `shopware_storefront_build`, `shopware_migrate`, `shopware_migrate_destructive`, `shopware_plugin_refresh`, `shopware_plugin_install_activate`, `shopware_theme_compile`, `shopware_admin_create`, `shopware_test`, `shopware_console`, `shopware_setup_full`, `shopware_ci`
- OroCommerce: `oro_setup`, `oro_build`, `oro_update`, `oro_cache_clear`, `oro_assets_build`, `oro_search_reindex`, `oro_mq_consume`, `oro_test`, `oro_logs_tail`, `oro_console`, `oro_ci`
- Magento 2: `magento2_setup`, `magento2_setup_upgrade`, `magento2_dev`, `magento2_di_compile`, `magento2_static_deploy`, `magento2_cache_clean`, `magento2_cache_flush`, `magento2_indexer_reindex`, `magento2_indexer_status`, `magento2_module_enable`, `magento2_module_disable`, `magento2_maintenance_enable`, `magento2_maintenance_disable`, `magento2_cron_run`, `magento2_console`, `magento2_mode_production`, `magento2_sampledata_deploy`, `magento2_sampledata_upgrade`, `magento2_config_set`, `magento2_config_get`, `magento2_logs_tail`, `magento2_setup_full`, `magento2_ci`, `magento2_test`
- WordPress: `wp_setup`, `wp_update_all`, `wp_build`, `wp_plugin_install_activate`, `wp_theme_install_activate`, `wp_permalinks_flush`, `wp_user_create_admin`, `wp_db_export`, `wp_db_import`, `wp_search_replace`, `wp_cache_flush`, `wp_cli`, `wp_ci`

Run a task, for example:

```bash
vendor/bin/castor sf_install
```

## Running in Docker (optional)
Recipes can run locally or inside a Docker container depending on environment variables.

- Set `CASTOR_DOCKER=1` to use Docker.
- Docker Compose service to use: `DOCKER_SERVICE` (default: `php`).
- Compose file: `DOCKER_COMPOSE_FILE` (default: `docker-compose.yml`).

Examples:

```bash
# Local execution (default)
vendor/bin/castor sf_test

# Using Docker Compose (service "php")
CASTOR_DOCKER=1 DOCKER_SERVICE=php vendor/bin/castor sf_test

# Using an alternative compose file
CASTOR_DOCKER=1 DOCKER_COMPOSE_FILE=docker/docker-compose.yml vendor/bin/castor laravel_test
```

## Symfony recipe: parameterization
All Symfony tasks avoid hardcoded binaries and allow customization via environment variables:

- Binaries
  - `PHP_BIN` (default: `php`)
  - `SF_CONSOLE` (default: `bin/console`)
  - `PHPUNIT_BIN` (default: `vendor/bin/phpunit` if present, otherwise `bin/phpunit`)
  - `SYMFONY_BIN` (default: `symfony`)
  - `COMPOSER_BIN` (default: `composer`)

- Generic args (optional)
  - `COMPOSER_ARGS` (default: `install`)
  - `PHPUNIT_ARGS`
  - `SF_SERVER_FLAGS` (default: `-d`)
  - `SF_MIGRATE_ARGS` (default: `--no-interaction`)
  - `SF_MIGRATE_DIFF_ARGS` (default: `--no-interaction`)
  - `SF_MIGRATE_FRESH_ARGS` (default: `--no-interaction`)
  - `SF_DB_CREATE_ARGS`
  - `SF_DB_DROP_ARGS`
  - `SF_FIXTURES_ARGS` (default: `--no-interaction --purge-with-truncate`)
  - `SF_ASSETS_TARGET` (default: `public`)
  - `SF_ASSETS_FLAGS` (default: `--symlink --relative`)
  - `SF_LINT_YAML_PATHS` (default: `config`)
  - `SF_LINT_YAML_ARGS` (default: `--parse-tags`)
  - `SF_LINT_TWIG_PATHS` (default: `templates`)
  - `SF_LINT_TWIG_ARGS`
  - `SF_LINT_CONTAINER_ARGS`
  - `SF_TRANSPORTS` (default: `async` for Messenger)
  - `SF_MESSENGER_ARGS` (default: `--time-limit=3600 --memory-limit=256M`)
  - `SF_LOG_FILE` (default: `var/log/$APP_ENV.log`)
  - `SF_LOG_LINES` (default: `200`)
  - `ARGS` (used by `sf_console` to forward arbitrary console args)

- Composite tasks toggles
  - `SF_SETUP_WITH_FIXTURES` = `1|true`
  - `SF_RESET_WITH_FIXTURES` = `1|true`

### Examples
```bash
# Use a custom console path
SF_CONSOLE=bin/cli vendor/bin/castor sf_cache_clear

# Forward options to phpunit
PHPUNIT_ARGS="-c phpunit.xml.dist --testsuite unit" vendor/bin/castor sf_test

# Run messenger with custom transports and workers
SF_TRANSPORTS=async_priority SF_MESSENGER_ARGS="--time-limit=600 --limit=50" vendor/bin/castor sf_messenger_consume

# One-shot full setup with fixtures
SF_SETUP_WITH_FIXTURES=1 vendor/bin/castor sf_setup

# Reset database with fixtures and re-run migrations
SF_RESET_WITH_FIXTURES=true vendor/bin/castor sf_db_reset

# Fresh migrations from scratch
vendor/bin/castor sf_migrate_fresh

# All lints at once
vendor/bin/castor sf_lint_all
```

## Shopware 6 recipe: parameterization
All Shopware tasks avoid hardcoded binaries and allow customization via environment variables:

- Binaries
  - `PHP_BIN` (default: `php`)
  - `SW_CONSOLE` (default: `bin/console`)
  - `PHPUNIT_BIN` (default: `vendor/bin/phpunit` if present, otherwise `bin/phpunit`)
  - `COMPOSER_BIN` (default: `composer`)

- Generic args (optional)
  - `COMPOSER_ARGS` (default: `install`)
  - `PHPUNIT_ARGS`
  - `SW_INSTALL_ARGS` (default: `--create-database --basic-setup --force`)
  - `SW_CACHE_CLEAR_ARGS`
  - `SW_DAL_REFRESH_ARGS`
  - `SW_STOREFRONT_BUILD_ARGS`
  - `SW_MIGRATE_ARGS`
  - `SW_MIGRATE_DESTRUCTIVE_ARGS`
  - `SW_PLUGIN_REFRESH_ARGS`
  - `SW_PLUGIN_INSTALL_ARGS`
  - `SW_THEME_COMPILE_ARGS`
  - `ARGS` (used by `shopware_console` to forward arbitrary console args)

- Plugins / setup variables
  - `SW_PLUGIN_NAMES` (comma/space separated list). Backward compat: `SHOPWARE_PLUGIN` for a single plugin
  - `SW_SETUP_WITH_ADMIN` = `1|true` to create an admin user at the end of `shopware_setup_full`
  - `SW_BUILD_WITH_STOREFRONT_BUILD` = `1|true` to run `storefront:build` inside `shopware_build`

- Admin user fields (for `shopware_admin_create`)
  - `SW_ADMIN_EMAIL` (default: `admin@example.com`)
  - `SW_ADMIN_PASSWORD` (default: `admin`)
  - `SW_ADMIN_FIRSTNAME` (default: `Admin`)
  - `SW_ADMIN_LASTNAME` (default: `User`)
  - `SW_ADMIN_LOCALE` (optional)

### Examples
```bash
# Install dependencies (custom Composer args)
COMPOSER_ARGS="install --no-dev" vendor/bin/castor shopware_install

# System install with custom flags
SW_INSTALL_ARGS="--create-database --force --basic-setup --drop-database" vendor/bin/castor shopware_system_install

# Build with storefront build enabled
SW_BUILD_WITH_STOREFRONT_BUILD=1 vendor/bin/castor shopware_build

# Install and activate multiple plugins
SW_PLUGIN_NAMES="SwagPayPal, SwagLanguagePack" vendor/bin/castor shopware_plugin_install_activate

# Create admin user with locale
SW_ADMIN_EMAIL=admin@example.com SW_ADMIN_PASSWORD=secret SW_ADMIN_LOCALE=it-IT vendor/bin/castor shopware_admin_create

# One-shot full setup
SW_SETUP_WITH_ADMIN=1 SW_PLUGIN_NAMES="MyPlugin" vendor/bin/castor shopware_setup_full

# Proxy arbitrary console command
ARGS="cache:clear --no-warmup" vendor/bin/castor shopware_console
```

## Adding multiple recipes
You can include multiple recipes at the same time by editing `castor.php` and adding more `require` lines:

```php
<?php
require __DIR__ . '/vendor/raffaelecarelle/castor-recipes/recipes/symfony.php';
require __DIR__ . '/vendor/raffaelecarelle/castor-recipes/recipes/laravel.php';
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


## Laravel recipe: parameterization
All Laravel tasks avoid hardcoded binaries and allow customization via environment variables:

- Binaries
  - `PHP_BIN` (default: `php`)
  - `PHPUNIT_BIN` (default: `vendor/bin/phpunit` if present, otherwise `bin/phpunit`)
  - `COMPOSER_BIN` (default: `composer`)
  - `LARAVEL_ARTISAN` (default: `artisan`)

- Generic args and variables
  - `PHPUNIT_ARGS`
  - `LARAVEL_LOG_FILE` (default: `storage/logs/laravel.log`)
  - `LARAVEL_SETUP_WITH_SEED` = `1|true`

- Helpers
  - Use `laravel_artisan` to proxy arbitrary artisan commands with `ARGS` if desired.

### Examples
```bash
# Proxy arbitrary artisan command
ARGS="cache:clear" vendor/bin/castor laravel_artisan

# One-shot setup with seeding
LARAVEL_SETUP_WITH_SEED=1 vendor/bin/castor laravel_setup

# Tail logs
vendor/bin/castor laravel_logs_tail
```

## Magento 2 recipe: parameterization

- Binaries
  - `PHP_BIN` (default: `php`)
  - `M2_BIN` (default: `bin/magento`)
  - `PHPUNIT_BIN` (default: `vendor/bin/phpunit` if present, otherwise `bin/phpunit`)
  - `COMPOSER_BIN` (default: `composer`)

- Generic args and variables
  - `M2_LOCALES` (default: `en_US` for static deploy)
  - `M2_MODULE` (used by enable/disable tasks)
  - `M2_LOG_FILE` (default: `var/log/system.log`)

### Examples
```bash
# Console proxy
ARGS="cache:status" vendor/bin/castor magento2_console

# Production mode helper
M2_LOCALES="en_US it_IT" vendor/bin/castor magento2_mode_production

# Sample data then upgrade
vendor/bin/castor magento2_sampledata_deploy
vendor/bin/castor magento2_sampledata_upgrade
```

## OroCommerce recipe: parameterization

- Binaries
  - `PHP_BIN` (default: `php`)
  - `ORO_CONSOLE` (default: `bin/console`)

- Generic args and variables
  - `ORO_LOG_FILE` (default: `var/log/$APP_ENV.log`)

### Examples
```bash
# Proxy bin/console
ARGS="oro:check-requirements" vendor/bin/castor oro_console

# Full setup with demo data
vendor/bin/castor oro_setup --withDemoData=1
```

## WordPress recipe: parameterization

- Binaries
  - `WP_BIN` (default: `wp`)

- Generic args and variables
  - `WP_DB_NAME`, `WP_DB_USER`, `WP_DB_PASSWORD`, `WP_DB_HOST` for config creation
  - `WP_URL`, `WP_ADMIN_PASSWORD` for installation

### Examples
```bash
# Proxy wp-cli
ARGS="plugin list" vendor/bin/castor wp_cli

# Update everything and build assets
vendor/bin/castor wp_ci
```