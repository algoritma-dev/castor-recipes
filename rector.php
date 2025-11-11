<?php

use Rector\Config\RectorConfig;

$additionalRules = [];
$rulesProvider = new Algoritma\CodingStandards\Rules\CompositeRulesProvider([
    new Algoritma\CodingStandards\Rules\RectorRulesProvider(),
    new Algoritma\CodingStandards\Rules\ArrayRulesProvider($additionalRules),
]);

$autoloadPathProvider = new Algoritma\CodingStandards\AutoloadPathProvider();

$setsProvider = new Algoritma\CodingStandards\Sets\RectorSetsProvider();

return RectorConfig::configure()
    ->withFileExtensions(['php'])
    ->withImportNames(importShortClasses: false)
    ->withParallel()
    ->withPaths($autoloadPathProvider->getPaths())
    ->withSkip([
        '**/vendor/*',
        '**/node_modules/*',
    ])
    ->withPhpSets()
    ->withSets(array_merge($setsProvider->getSets(), [\Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_82]))
    ->withRules(array_merge($rulesProvider->getRules(), [/* custom rules */]));
