<?php

use Isolated\Symfony\Component\Finder\Finder;

function getWpExcludedSymbols(string $fileName): array
{
    $filePath = __DIR__.'/vendor/sniccowp/php-scoper-wordpress-excludes/generated/'.$fileName;

    return json_decode(
        file_get_contents($filePath),
        true,
    );
}

$wp_classes   = getWpExcludedSymbols('exclude-wordpress-classes.json');
$wp_functions = getWpExcludedSymbols('exclude-wordpress-functions.json');
$wp_constants = getWpExcludedSymbols('exclude-wordpress-constants.json');

return [
    'prefix' => 'RespectifyScoper', // Change this to a unique prefix for your plugin
    'finders' => [
        Finder::create()->files()->in('vendor'),
    ],
    'patchers' => [
        function (string $filePath, string $prefix, string $content): string {
            // Custom patching logic if needed
            return $content;
        },
    ],
    'exclude-classes' => $wp_classes,
    'exclude-constants' => $wp_constants,
    'exclude-functions' => $wp_functions,
];