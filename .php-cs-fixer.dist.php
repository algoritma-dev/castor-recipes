<?php

/*
 * Additional rules or rules to override.
 * These rules will be added to default rules or will override them if the same key already exists.
 */

// Force PHPUnit assertions to use `self::` (not `static::`)
$additionalRules = [
    'php_unit_test_case_static_method_calls' => [
        'call_type' => 'self',
    ]
];
$rulesProvider = new Algoritma\CodingStandards\Shared\Rules\CompositeRulesProvider([
    new Algoritma\CodingStandards\PhpCsFixer\Rules\DefaultRulesProvider(),
    new Algoritma\CodingStandards\PhpCsFixer\Rules\RiskyRulesProvider(),
    new Algoritma\CodingStandards\Shared\Rules\ArrayRulesProvider($additionalRules),
]);

$config = new PhpCsFixer\Config();
$config->setRules($rulesProvider->getRules());
$config->setRiskyAllowed(true);

$finder = new PhpCsFixer\Finder();

/*
 * You can set manually these paths:
 */
$autoloadPathProvider = new Algoritma\CodingStandards\AutoloadPathProvider();
$finder
    ->in($autoloadPathProvider->getPaths())
    ->exclude(['node_modules', '*/vendor/*']);

$config->setFinder($finder);

return $config;
