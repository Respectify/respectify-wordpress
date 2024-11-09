<?php

use Isolated\Symfony\Component\Finder\Finder;

function getWpExcludedSymbols(string $fileName): array
{
    $filePath = __DIR__ . '/vendor/sniccowp/php-scoper-wordpress-excludes/generated/' . $fileName;

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
        Finder::create()
            ->files()
            ->in('vendor', 'respectify')
            ->name('*.php')
            ->exclude(['tests', 'Tests'])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true),
    ],

    'exclude-namespaces' => [
        'Composer\Autoload',
    ],

    'exclude-classes' => $wp_classes,
    'exclude-constants' => $wp_constants,
    'exclude-functions' => $wp_functions,

    // 'exclude-files' => [
    //     'vendor/autoload.php',
    //     'vendor/composer/autoload_real.php',
    //     'vendor/composer/ClassLoader.php',
    //     'vendor/composer/autoload_static.php',
    // ],

    'patchers' => [
        function (string $filePath, string $prefix, string $content): string {
            // Update composer.json autoload
            if (basename($filePath) === 'composer.json') {
                $json = json_decode($content, true);
                if (isset($json['autoload']['psr-4'])) {
                    $newAutoload = [];
                    foreach ($json['autoload']['psr-4'] as $namespace => $path) {
                        $newNamespace = $prefix . '\\' . trim($namespace, '\\') . '\\';
                        $newAutoload[$newNamespace] = $path;
                    }
                    $json['autoload']['psr-4'] = $newAutoload;
                    $content = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
            }
            return $content;
        },
    ],
];
