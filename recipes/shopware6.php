<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\run;

require_once __DIR__ . '/_common.php';

function sw_console_bin(): string
{
    return env_value('SW_CONSOLE', 'bin/console');
}

#[AsTask(description: 'Install dependencies and prepare Shopware (system:install)', aliases: ['setup'], namespace: 'sw')]
function system_install(string $args = '--create-database --basic-setup --force'): void
{
    run(dockerize(\sprintf('%s %s system:install %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Rebuild cache and indexes', namespace: 'sw')]
function build(bool $storefront = true, bool $admin = true, string $args = ''): void
{
    cache_clear();
    run(dockerize(\sprintf('%s %s dal:refresh:index %s', php(), sw_console_bin(), $args))); // data abstraction layer
    if ($storefront) {
        storefront_build();
    }
    if ($admin) {
        administration_build();
    }
}

#[AsTask(description: 'Clear Shopware cache', aliases: ['cc', 'ccl'], namespace: 'sw')]
function cache_clear(string $args = '--no-debug'): void
{
    run(dockerize(\sprintf('%s %s cache:clear %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Build Storefront (if present)', namespace: 'sw')]
function storefront_build(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s storefront:build %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Build Administration (if present)', namespace: 'sw')]
function administration_build(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s administration:build %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Run PHP tests (PHPUnit)', namespace: 'sw')]
function test(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s', phpunit_bin(), $args)));
}

#[AsTask(description: 'Refresh plugins list', aliases: ['plugin-refresh'], namespace: 'sw')]
function plugin_refresh(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s plugin:refresh %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Install and activate plugin(s). Set SW_PLUGIN_NAMES (comma/space) or SHOPWARE_PLUGIN.', namespace: 'sw')]
function plugin_install_activate(string $plugins, string $args = ''): void
{
    $plugins = preg_split('/[\s,]+/', trim($plugins)) ?: [];
    if ($plugins === [] || $plugins[0] === '') {
        run(dockerize(\sprintf('%s %s plugin:list', php(), sw_console_bin())));

        return;
    }

    foreach ($plugins as $plugin) {
        run(dockerize(\sprintf('%s %s plugin:install --activate %s %s', php(), sw_console_bin(), $args, $plugin)));
    }
}

#[AsTask(description: 'Compile themes', namespace: 'sw')]
function theme_compile(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s theme:compile %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Run DB migrations (non-destructive)', namespace: 'sw')]
function migrate(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s database:migrate %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Run DB migrations (destructive)', namespace: 'sw')]
function migrate_destructive(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s database:migrate-destructive %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Create admin user (env: SW_ADMIN_EMAIL, SW_ADMIN_PASSWORD, names/locale)', namespace: 'sw')]
function admin_create(string $email = 'admin@shopware.com', string $password = 'shopware', string $firstname = 'Admin', string $lastname = 'User', string $locale = 'en'): void
{
    $localeArg = $locale !== '' ? \sprintf('--locale=%s', $locale) : '';

    run(dockerize(\sprintf(
        '%s %s user:create %s --admin --password=%s --firstName=%s --lastName=%s %s',
        php(),
        sw_console_bin(),
        $email,
        $password,
        $firstname,
        $lastname,
        $localeArg
    )));
}

#[AsTask(description: 'Proxy to bin/console with ARGS (env)', namespace: 'sw')]
function console(string $args = ''): void
{
    run(dockerize(\sprintf('%s %s %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Project setup (composite): composer install, system:install, migrate, plugin refresh/install, theme, optional admin', namespace: 'sw')]
function setup_full(string $plugins, bool $withAdmin): void
{
    composer_install();
    system_install();
    migrate();
    migrate_destructive();
    plugin_refresh();

    if ($plugins !== '') {
        plugin_install_activate($plugins);
    }

    theme_compile();

    if ($withAdmin) {
        admin_create();
    }
}

#[AsTask(description: 'CI helper (build + tests) - composite', namespace: 'sw')]
function ci(): void
{
    build();
    test();
}

#[AsTask(description: 'Clear Symfony cache pool (cache:pool:clear --all --no-debug)', aliases: ['cp'], namespace: 'sw')]
function cache_pool_clear(string $args = '--all --no-debug'): void
{
    run(dockerize(\sprintf('%s %s cache:pool:clear %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Flush Redis (FLUSHALL) - docker service configurable or local redis-cli', namespace: 'sw')]
function redis_flush(string $service = 'redis'): void
{
    if (env_value('CASTOR_DOCKER') === 'true' || env_value('CASTOR_DOCKER') !== '1') {
        set_env('DOCKER_SERVICE', $service);
    }

    run(dockerize('redis-cli FLUSHALL'));

    if (env_value('CASTOR_DOCKER') === 'true' || env_value('CASTOR_DOCKER') !== '1') {
        restore_env('DOCKER_SERVICE');
    }
}

#[AsTask(description: 'Consume Symfony Messenger', namespace: 'sw')]
function messenger_consume(string $args = '--no-debug'): void
{
    run(dockerize(\sprintf('%s %s messenger:consume %s', php(), sw_console_bin(), $args)));
}

#[AsTask(description: 'Dump FOSJsRouting routes (JSON)', namespace: 'sw')]
function js_routes_dump(string $format = 'json', string $args = ''): void
{
    $formatArg = $format !== '' ? \sprintf('--format=%s', $format) : '';
    run(dockerize(\sprintf('%s %s fos:js-routing:dump %s %s', php(), sw_console_bin(), $formatArg, $args)));
}

#[AsTask(description: 'Remove all node_modules folders (destructive)', namespace: 'sw')]
function rm_node_modules(): void
{
    $root = getcwd();
    run(\sprintf('find %s -type d -name node_modules -prune -exec rm -rf {} +', $root));
}

#[AsTask(description: 'Post-rebase (lite): vendor, cache clear, system update prepare/finish, DAL category index, plugin refresh/update all', namespace: 'sw')]
function post_rebase_lite(): void
{
    // composer install (vendor)
    composer_install();

    // clear cache
    cache_clear();

    // system update prepare/finish
    run(dockerize(\sprintf('%s %s system:update:prepare', php(), sw_console_bin())));
    run(dockerize(\sprintf('%s %s system:update:finish', php(), sw_console_bin())));

    // Only DAL refresh for category index per request
    run(dockerize(\sprintf('%s %s dal:refresh:index --only category.indexer --no-interaction', php(), sw_console_bin())));

    // plugins
    plugin_refresh();
    run(dockerize(\sprintf('%s %s plugin:update:all', php(), sw_console_bin())));
}

#[AsTask(description: 'Post-rebase: vendor, rm node_modules, cache, system update, DAL category index, plugin refresh/update, theme refresh, messenger transport setup, js routes, build js', namespace: 'sw')]
function post_rebase(): void
{
    composer_install();
    rm_node_modules();
    cache_clear();

    run(dockerize(\sprintf('%s %s system:update:prepare', php(), sw_console_bin())));
    run(dockerize(\sprintf('%s %s system:update:finish', php(), sw_console_bin())));

    run(dockerize(\sprintf('%s %s dal:refresh:index --only category.indexer --no-interaction', php(), sw_console_bin())));

    plugin_refresh();
    run(dockerize(\sprintf('%s %s plugin:update:all', php(), sw_console_bin())));

    run(dockerize(\sprintf('%s %s theme:refresh', php(), sw_console_bin())));
    run(dockerize(\sprintf('%s %s messenger:setup-transports', php(), sw_console_bin())));

    js_routes_dump();

    build_assets();
}

#[AsTask(description: 'System update workflow with Git commits and Composer recipes updates', namespace: 'sw')]
function system_update(): void
{
    // Stash current changes
    run('git stash push -m "Stashed before system update"');

    composer_update();

    run(dockerize(\sprintf('%s %s system:update:prepare', php(), sw_console_bin())));

    // Commit changes if any
    run('git add .');
    run('bash -lc "git diff-index --quiet HEAD || git commit -m \"Updated shopware with dependencies\""');

    // Finish system update
    run(dockerize(\sprintf('%s %s system:update:finish', php(), sw_console_bin())));

    // Recipes updates list
    $packages = [
        'shopware/administration',
        'shopware/core',
        'shopware/elasticsearch',
        'shopware/storefront',
        'symfony/framework-bundle',
        'symfony/phpunit-bridge',
    ];

    foreach ($packages as $package) {
        run(dockerize(\sprintf('%s recipes:update %s -v', composer_bin(), $package)));
        run('git add .');
        $msg = \sprintf('Updated %s recipe', $package === 'shopware/administration' ? 'Administration' : ($package === 'shopware/storefront' ? 'Storefront' : ($package === 'shopware/elasticsearch' ? 'Elasticsearch' : ($package === 'symfony/framework-bundle' ? 'Symfony Framework' : ($package === 'symfony/phpunit-bridge' ? 'PHPUnit' : 'Core')))));
        run(\sprintf('bash -lc %s', \sprintf('git diff-index --quiet HEAD || git commit -m %s', $msg)));
    }
}

#[AsTask(description: 'Watch Storefront (./bin/watch-storefront.sh)', namespace: 'sw')]
function watch_storefront(): void
{
    run(dockerize('./bin/watch-storefront.sh'));
}

#[AsTask(description: 'Watch Administration (./bin/watch-administration.sh)', namespace: 'sw')]
function watch_admin(): void
{
    run(dockerize('./bin/watch-administration.sh'));
}

#[AsTask(description: 'Build Administration via script (./bin/build-administration.sh)', namespace: 'sw')]
function build_admin_script(): void
{
    run(dockerize('./bin/build-administration.sh'));
}

#[AsTask(description: 'Build Storefront via script (./bin/build-storefront.sh)', namespace: 'sw')]
function build_storefront_script(): void
{
    run(dockerize('./bin/build-storefront.sh'));
}

#[AsTask(description: 'Build all JS assets via script (./bin/build-js.sh)', namespace: 'sw')]
function build_assets(): void
{
    run(dockerize('./bin/build-js.sh'));
}

#[AsTask(description: 'Install and activate a plugin by name (alias of plugin_install_activate)', aliases: ['plugin-install'], namespace: 'sw')]
function plugin_install(string $plugin, string $args = ''): void
{
    plugin_install_activate($plugin, $args);
}

#[AsTask(description: 'DB migrate all and refresh DAL category index', namespace: 'sw')]
function db_migrate(string $env = 'dev'): void
{
    run(dockerize(\sprintf('%s %s database:migrate --all%s', php(), sw_console_bin(), $env)));
    run(dockerize(\sprintf('%s %s dal:refresh:index --only category.indexer --no-interaction%s', php(), sw_console_bin(), $env)));
}
