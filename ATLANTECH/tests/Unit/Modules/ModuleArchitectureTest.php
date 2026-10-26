<?php

/**
 * Dependency rules for the domain modules in app/Modules (backend architecture:
 * one folder each, one-way dependencies, modules at the same level never import each other).
 *
 * A module in a layer may use modules from earlier layers and the integration modules.
 * It may not use modules from later layers or its peers in the same layer.
 * Integration modules use only the shared kernel; any other module may use them.
 */
$layers = [
    ['Shared'],
    ['Access'],
    ['License'],
    ['MasterData'],
    ['Environment', 'Berthing'],
    ['Baplie'],
    ['Recommendation'],
    ['Plan'],
    ['Yard', 'Dispatch'],
    ['Execution'],
    ['Learning'],
];
$integrationModules = ['AiBridge', 'Realtime'];

$namespace = fn (string $module): string => 'App\\Modules\\'.$module;

foreach ($layers as $index => $layer) {
    $laterAndSameLayer = array_merge(...array_slice($layers, $index));

    foreach ($layer as $module) {
        $forbidden = array_values(array_diff($laterAndSameLayer, [$module]));

        if ($module === 'Shared') {
            $forbidden = array_merge($forbidden, $integrationModules);
        }

        if ($forbidden === []) {
            continue;
        }

        arch("{$module} does not use later modules or its peers")
            ->expect($namespace($module))
            ->not->toUse(array_map($namespace, $forbidden));
    }
}

foreach ($integrationModules as $module) {
    $forbidden = array_values(array_diff(
        array_merge(array_merge(...$layers), $integrationModules),
        ['Shared', $module],
    ));

    arch("{$module} uses only the shared kernel")
        ->expect($namespace($module))
        ->not->toUse(array_map($namespace, $forbidden));
}

test('every module folder has a place in the dependency rules', function () use ($layers, $integrationModules) {
    $folders = array_map('basename', glob(__DIR__.'/../../../app/Modules/*', GLOB_ONLYDIR));
    $declared = array_merge(array_merge(...$layers), $integrationModules);

    sort($folders);
    sort($declared);

    expect($folders)->toBe($declared);
});
