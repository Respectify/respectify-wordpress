<?php

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the scoped Composer autoloader
if (file_exists(__DIR__ . '/build/autoload.php')) {
    error_log('Loading classloader.');
    echo "Loading classloader.\n";

    require __DIR__ . '/build/composer/ClassLoader.php';

    error_log('Loading autoload.');
    echo "Loading autoload.\n";

    require __DIR__ . '/build/autoload.php';
	error_log('Scoper: Composer autoloader included successfully.');
    echo "Scoper: Composer autoloader included successfully.\n";
} else {
    error_log('Scoper: Composer autoloader not found.');
    echo "Scoper: Composer autoloader not found.\n";
}

// Manually invoke the autoloader logic
$vendorDir = dirname(__DIR__);
$baseDir = dirname($vendorDir);

$ret = array(
    'Twig\\' => array($vendorDir . '/twig/twig/src'),
    'Tests\\' => array($vendorDir . '/respectify/respectify-php/tests'),
    'Symfony\\Polyfill\\Php81\\' => array($vendorDir . '/symfony/polyfill-php81'),
    'Symfony\\Polyfill\\Mbstring\\' => array($vendorDir . '/symfony/polyfill-mbstring'),
    'Symfony\\Polyfill\\Ctype\\' => array($vendorDir . '/symfony/polyfill-ctype'),
    'Respectify\\' => array($baseDir . '/includes', $vendorDir . '/respectify/respectify-php/src'),
    'React\\Stream\\' => array($vendorDir . '/react/stream/src'),
    'React\\Socket\\' => array($vendorDir . '/react/socket/src'),
    'React\\Promise\\' => array($vendorDir . '/react/promise/src'),
    'React\\Http\\' => array($vendorDir . '/react/http/src'),
    'React\\EventLoop\\' => array($vendorDir . '/react/event-loop/src'),
    'React\\Dns\\' => array($vendorDir . '/react/dns/src'),
    'React\\Cache\\' => array($vendorDir . '/react/cache/src'),
    'Psr\\Http\\Message\\' => array($vendorDir . '/psr/http-factory/src', $vendorDir . '/psr/http-message/src'),
    'GuzzleHttp\\Psr7\\' => array($vendorDir . '/guzzlehttp/psr7/src'),
    'Fig\\Http\\Message\\' => array($vendorDir . '/fig/http-message-util/src'),
    'Evenement\\' => array($vendorDir . '/evenement/evenement/src'),
);

error_log('vendor dir: ' . $vendorDir);
error_log('base dir: ' . $baseDir);
error_log('Respectify\\ maps to: ' . implode(', ', $ret['Respectify\\']));

// Function to recursively get all .php files in a directory
function getPhpFiles($dir) {
    $files = [];
    if (is_dir($dir)) {
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        } catch (Exception $e) {
            error_log('Error reading directory: ' . $dir . ' - ' . $e->getMessage());
        }
    } else {
        error_log('Directory does not exist: ' . $dir);
    }
    return $files;
}

// Get all .php files in the directories mapped by Respectify\
foreach ($ret['Respectify\\'] as $dir) {
    $phpFiles = getPhpFiles($dir);
    foreach ($phpFiles as $file) {
        error_log('Found PHP file: ' . $file);
    }
}

echo "Autoloader test completed. Check the PHP error log for details.\n";
