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

## Supported Platforms/Frameworks
- Symfony (`recipes/symfony.php`)
- Laravel (`recipes/laravel.php`)
- Shopware6 (`recipes/shopware6.php`)
- OroCommerce (`recipes/orocommerce.php`)
- Magento 2 (`recipes/magento2.php`)
- WordPress (`recipes/wordpress.php`)

## Additional Recipes
- **Spell Checking** (`recipes/_aspell.php`) - Comprehensive spell checking for text files and PHP code
- **Quality Checks** (`recipes/quality-check.php`) - Code quality tools integration
- **Docker** (`recipes/docker.php`) - Docker container management tasks
- **MySQL/PostgreSQL** (`recipes/mysql.php`, `recipes/postgresql.php`) - Database management tasks

## Installation

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
- Docker Compose service to use: `DOCKER_SERVICE` (default: `workspace`).
- Docker Compose database service to use: `DOCKER_DB_SERVICE` (default: `database`).
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

## Spell Checking with Aspell

The `_aspell.php` recipe provides comprehensive spell checking for your project using [GNU Aspell](http://aspell.net/).

### Requirements
- GNU Aspell must be installed on your system:
  ```bash
  # Ubuntu/Debian
  sudo apt-get install aspell aspell-en aspell-it

  # macOS
  brew install aspell
  ```

### Features
- **Text file checking**: Markdown, text files, YAML/JSON translation files
- **PHP code checking**: Extracts and verifies identifiers (variables, classes, methods, comments) using PHP tokenizer
- **Multi-language support**: English, Italian, French, German, Spanish
- **Automatic language detection**: From filename patterns (e.g., `messages.it_IT.yaml`)
- **Personal dictionary**: Project-specific word lists (`.aspell.en.pws`)
- **Global dictionary**: Shared technical terms across all projects
- **Smart PHP processing**: Ignores built-in functions, splits camelCase/snake_case identifiers

### Available Tasks

```bash
# Check text files (markdown, yaml, json translations)
castor aspell:check

# Check PHP code identifiers
castor aspell:check-code

# Check all files (text + code)
castor aspell:check-all

# Check specific files
castor aspell:check-all --files="README.md src/Plugin.php"

# Check with different language
castor aspell:check --lang=it

# Add all errors to personal dictionary automatically
castor aspell:check-all --ignore-all

# Manage personal dictionary
castor aspell:init                    # Initialize personal dictionary
castor aspell:add-word myword         # Add a word to dictionary
castor aspell:show-dict               # Show all words in dictionary
```

### Personal Dictionary

The spell checker uses a project-specific personal dictionary stored in `.aspell.en.pws` at your project root. This file:
- Should be committed to version control
- Contains project-specific terminology, brand names, technical terms
- Is automatically loaded during spell checks
- Can be managed via `aspell:add-word` and `aspell:show-dict` tasks

### Integration with Git Hooks

Add spell checking to your pre-commit hook:

```bash
#!/bin/bash
# .git/hooks/pre-commit

# Get list of staged files
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM)

# Run spell check on staged files
castor aspell:check-all --files="$STAGED_FILES" || exit 1
```

## Quality Checks Recipe

The `quality-check.php` recipe provides comprehensive code quality tools and testing capabilities.

### Available Tasks

```bash
# Code formatting and analysis
castor qa:php-cs-fixer              # Run PHP CS Fixer
castor qa:rector                    # Run Rector for code refactoring
castor qa:phpstan                   # Run PHPStan static analysis

# Testing
castor qa:tests                     # Run PHPUnit tests
castor qa:test_watch                # 🔥 Run tests in interactive watch mode

# Spell checking
castor qa:aspell-check              # Check text files for typos
castor qa:aspell-check-code         # Check PHP code for typos
castor qa:aspell-check-all          # Check all files for typos

# Pre-commit checks
castor qa:pre-commit                # Run all quality checks (Rector, CS Fixer, PHPStan, Aspell, tests)
```

### ⭐ Interactive Test Watch Mode

The **`qa:test_watch`** task provides an interactive watch mode that automatically reruns your tests when files change:

```bash
castor qa:test_watch
```

**Features:**
- **Interactive menu** with options:
  - Run all tests
  - Filter by test name
  - Filter by file name
  - Pass custom arguments to PHPUnit
- **Auto-rerun** tests when source or test files change
- **Ctrl+C** returns to the menu (doesn't exit)
- Watches all PSR-4 autoload paths
- Smart debouncing to avoid multiple runs

**Requirements:**
- PHP `pcntl` extension for signal handling

### Typo Checking with Aspell

The quality-check recipe integrates spell checking for catching typos in your code:

```bash
# Check specific files
castor qa:aspell-check-all --files="README.md src/MyClass.php"

# Automatically add all errors to dictionary
castor qa:aspell-check-all --ignore-all

# Check with specific language
castor qa:aspell-check --lang=it
```

See the [Spell Checking with Aspell](#spell-checking-with-aspell) section for complete documentation.

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

GPL-3.0 License