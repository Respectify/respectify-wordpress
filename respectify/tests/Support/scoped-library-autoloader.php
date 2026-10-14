<?php
/**
 * Maps the scoped library namespace onto the unscoped library in vendor/.
 *
 * The plugin refers to RespectifyScoper\Respectify\..., a namespace PHP-Scoper only creates at build time.
 * In development vendor/ holds the library under its own Respectify\ namespace, so each scoped class is
 * aliased to its unscoped original. The tests need respectify-php 0.7.0 or later (comment health:
 * HealthItem, the `off` and `permissive` levels and CommentScore::$healthItems); see the repository README.md for testing against a local checkout.
 */

spl_autoload_register(static function (string $scoped_class): void {
    $scoped_prefix = 'RespectifyScoper\\';
    if (!str_starts_with($scoped_class, $scoped_prefix)) {
        return;
    }

    $unscoped_class = substr($scoped_class, strlen($scoped_prefix));
    if (class_exists($unscoped_class) || interface_exists($unscoped_class)) {
        class_alias($unscoped_class, $scoped_class);
    }
});


// Alias every library class up front. A parameter type such as `CommentScore $comment_score` does not trigger
// autoloading, so a class the library builds internally (eg the CommentScore inside a MegaCallResult) would fail
// the plugin's scoped type check if its alias had not yet been made. In the built plugin both names are the same.
(static function (): void {
    $library_source = __DIR__ . '/../../vendor/respectify/respectify-php/src';
    if (!is_dir($library_source)) {
        return;
    }
    $source_files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($library_source, \FilesystemIterator::SKIP_DOTS));
    foreach ($source_files as $source_file) {
        if ($source_file->getExtension() !== 'php') {
            continue;
        }
        $relative_path = substr($source_file->getPathname(), strlen($library_source) + 1, -strlen('.php'));
        class_exists('RespectifyScoper\\Respectify\\' . str_replace('/', '\\', $relative_path));
    }
})();
