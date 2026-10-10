<?php
/**
 * Maps the scoped library namespace onto the unscoped library in vendor/.
 *
 * The plugin refers to RespectifyScoper\Respectify\..., a namespace PHP-Scoper only creates at build time.
 * In development vendor/ holds the library under its own Respectify\ namespace, so each scoped class is
 * aliased to its unscoped original on first use.
 *
 * The one exception is the Moderation namespace, which arrived in respectify-php 0.6.0. Until 0.6.0 is tagged
 * and installed, vendor/ holds 0.5.0, which lacks it, so a minimal stand-in is loaded instead. Once the real
 * classes are installed they are always preferred, and Fallback/moderation-classes.php can be deleted.
 */

spl_autoload_register(static function (string $scoped_class): void {
    $scoped_prefix = 'RespectifyScoper\\';
    if (!str_starts_with($scoped_class, $scoped_prefix)) {
        return;
    }

    $unscoped_class = substr($scoped_class, strlen($scoped_prefix));
    if (class_exists($unscoped_class) || interface_exists($unscoped_class)) {
        class_alias($unscoped_class, $scoped_class);
        return;
    }

    if (str_starts_with($unscoped_class, 'Respectify\\Moderation\\')) {
        require_once __DIR__ . '/Fallback/moderation-classes.php';
    }
});
