<?php

use Isolated\Symfony\Component\Finder\Finder;

return [
    'prefix' => 'RespectifyScoper', 
    'finders' => [
        Finder::create()->files()->in('vendor'),
    ],
    'patchers' => [
        function (string $filePath, string $prefix, string $content): string {
            // Custom patching logic if needed
            return $content;
        },
    ],
];