<?php

declare(strict_types=1);

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Helper\PathHelper;

use function Castor\exit_code;
use function Castor\io;
use function Castor\run;

require_once __DIR__ . '/_common.php';

function sw_console_bin(): string
{
    return env_value('SW_CONSOLE', 'bin/console');
}

#[AsTask(namespace: 'sw', description: 'Install dependencies and prepare Shopware (system:install)', aliases: ['setup'])]
function system_install(
    #[AsRawTokens]
    array $args = ['--create-database --basic-setup --force']
): void {
    run(dockerize(\sprintf('%s %s system:install %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Rebuild cache and indexes', aliases: ['swb'])]
function build(
    bool $storefront = true,
    bool $admin = true,
    #[AsRawTokens]
    array $args = []
): void {
    cache_clear();
    run(dockerize(\sprintf('%s %s dal:refresh:index %s', php(), sw_console_bin(), implode(' ', $args)))); // data abstraction layer
    if ($storefront) {
        storefront_build();
    }
    if ($admin) {
        administration_build();
    }
}

#[AsTask(namespace: 'sw', description: 'Clear Shopware cache', aliases: ['cc', 'ccl'])]
function cache_clear(
    #[AsRawTokens]
    array $args = ['--no-debug']
): void {
    run(dockerize(\sprintf('%s %s cache:clear %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Build Storefront')]
function storefront_build(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s storefront:build %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Build Administration')]
function administration_build(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s administration:build %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Run PHP tests (PHPUnit)', aliases: ['swt'])]
function test(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s', phpunit_bin(), implode(' ', $args))));
}

#[AsTask(name: 'tests', namespace: 'qa', description: 'Run Shopware tests (PHPUnit) including custom plugins', aliases: ['t'])]
function sw_tests(
    #[AsRawTokens]
    array $args = [],
    string $pluginGlob = 'Algoritma*'
): int {
    $exitCode = exit_code(dockerize(\sprintf('%s %s', phpunit_bin(), implode(' ', $args))));

    // Run tests for each plugin matching the glob pattern
    $pluginsDir = PathHelper::getRoot() . '/custom/plugins';
    if (is_dir($pluginsDir)) {
        $plugins = glob($pluginsDir . '/' . $pluginGlob, \GLOB_ONLYDIR);
        foreach ($plugins as $plugin) {
            $phpunitConfig = $plugin . '/phpunit.xml.dist';
            if (! is_file($phpunitConfig)) {
                $phpunitConfig = $plugin . '/phpunit.xml';
            }

            if (is_file($phpunitConfig)) {
                io()->writeln(\sprintf('Running tests for plugin: %s', basename($plugin)));
                $pluginExitCode = exit_code(dockerize(\sprintf('%s --configuration=%s %s', phpunit_bin(), $phpunitConfig, implode(' ', $args))));
                if ($pluginExitCode !== 0) {
                    $exitCode = $pluginExitCode;
                }
            }
        }
    }

    return $exitCode;
}

#[AsTask(namespace: 'sw', description: 'Refresh plugins list', aliases: ['plugin-refresh'])]
function plugin_refresh(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s plugin:refresh %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Install and activate plugin(s). Set SW_PLUGIN_NAMES (comma/space) or SHOPWARE_PLUGIN.')]
function plugin_install_activate(
    string $plugins,
    #[AsRawTokens]
    array $args = []
): void {
    $plugins = preg_split('/[\s,]+/', trim($plugins)) ?: [];
    if ($plugins === [] || $plugins[0] === '') {
        run(dockerize(\sprintf('%s %s plugin:list', php(), sw_console_bin())));

        return;
    }

    foreach ($plugins as $plugin) {
        run(dockerize(\sprintf('%s %s plugin:install --activate %s %s', php(), sw_console_bin(), implode(' ', $args), $plugin)));
    }
}

#[AsTask(namespace: 'sw', description: 'Compile themes')]
function theme_compile(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s theme:compile %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Run DB migrations (non-destructive)', aliases: ['swm'])]
function migrate(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s database:migrate %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Run DB migrations (destructive)')]
function migrate_destructive(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s database:migrate-destructive %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Create admin user (env: SW_ADMIN_EMAIL, SW_ADMIN_PASSWORD, names/locale)')]
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

#[AsTask(namespace: 'sw', description: 'Proxy to bin/console with ARGS (env)', aliases: ['swc'])]
function console(
    #[AsRawTokens]
    array $args = []
): void {
    run(dockerize(\sprintf('%s %s %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Project setup (composite): composer install, system:install, migrate, plugin refresh/install, theme, optional admin')]
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

#[AsTask(namespace: 'sw', description: 'CI helper (build + tests) - composite')]
function ci(): void
{
    build();
    test();
}

#[AsTask(namespace: 'sw', description: 'Clear Symfony cache pool (cache:pool:clear --all --no-debug)', aliases: ['cp'])]
function cache_pool_clear(
    #[AsRawTokens]
    array $args = ['--all --no-debug']
): void {
    run(dockerize(\sprintf('%s %s cache:pool:clear %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Flush Redis (FLUSHALL) - docker service configurable or local redis-cli')]
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

#[AsTask(namespace: 'sw', description: 'Consume Symfony Messenger')]
function messenger_consume(
    #[AsRawTokens]
    array $args = ['--no-debug']
): void {
    run(dockerize(\sprintf('%s %s messenger:consume %s', php(), sw_console_bin(), implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Dump FOSJsRouting routes (JSON)')]
function js_routes_dump(
    string $format = 'json',
    #[AsRawTokens]
    array $args = []
): void {
    $formatArg = $format !== '' ? \sprintf('--format=%s', $format) : '';
    run(dockerize(\sprintf('%s %s fos:js-routing:dump %s %s', php(), sw_console_bin(), $formatArg, implode(' ', $args))));
}

#[AsTask(namespace: 'sw', description: 'Remove all node_modules folders (destructive)')]
function rm_node_modules(): void
{
    $root = PathHelper::getRoot();
    run(\sprintf('find %s -type d -name node_modules -prune -exec rm -rf {} +', $root));
}

#[AsTask(namespace: 'sw', description: 'Post-rebase (lite): vendor, cache clear, system update prepare/finish, DAL category index, plugin refresh/update all')]
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

#[AsTask(namespace: 'sw', description: 'Post-rebase: vendor, rm node_modules, cache, system update, DAL category index, plugin refresh/update, theme refresh, messenger transport setup, js routes, build js')]
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

#[AsTask(namespace: 'sw', description: 'System update workflow with Git commits and Composer recipes updates')]
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

#[AsTask(namespace: 'sw', description: 'Watch Storefront (./bin/watch-storefront.sh)')]
function watch_storefront(): void
{
    run(dockerize('./bin/watch-storefront.sh'));
}

#[AsTask(namespace: 'sw', description: 'Watch Administration (./bin/watch-administration.sh)')]
function watch_admin(): void
{
    run(dockerize('./bin/watch-administration.sh'));
}

#[AsTask(namespace: 'sw', description: 'Build Administration via script (./bin/build-administration.sh)')]
function build_admin_script(): void
{
    run(dockerize('./bin/build-administration.sh'));
}

#[AsTask(namespace: 'sw', description: 'Build Storefront via script (./bin/build-storefront.sh)')]
function build_storefront_script(): void
{
    run(dockerize('./bin/build-storefront.sh'));
}

#[AsTask(namespace: 'sw', description: 'Build all JS assets via script (./bin/build-js.sh)')]
function build_assets(): void
{
    run(dockerize('./bin/build-js.sh'));
}

#[AsTask(namespace: 'sw', description: 'Install and activate a plugin by name (alias of plugin_install_activate)', aliases: ['plugin-install'])]
function plugin_install(
    string $plugin,
    #[AsRawTokens]
    array $args = []
): void {
    plugin_install_activate($plugin, $args);
}

#[AsTask(namespace: 'sw', description: 'DB migrate all and refresh DAL category index')]
function db_migrate(string $env = 'dev'): void
{
    run(dockerize(\sprintf('%s %s database:migrate --all%s', php(), sw_console_bin(), $env)));
    run(dockerize(\sprintf('%s %s dal:refresh:index --only category.indexer --no-interaction%s', php(), sw_console_bin(), $env)));
}
