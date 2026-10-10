<?php
/**
 * PHPUnit bootstrap: lets the plugin's comment-handling code run outside WordPress.
 *
 * Order matters: the WordPress stubs and ABSPATH must exist before the plugin files are required, because
 * respectify-constants.php exits without ABSPATH and respectify-utils.php pulls in pluggable.php when
 * wp_salt() is missing.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/Support/WordPressStubState.php';
require_once __DIR__ . '/Support/TerminatedRequest.php';
require_once __DIR__ . '/Support/wordpress-stubs.php';
require_once __DIR__ . '/Support/scoped-library-autoloader.php';
require_once __DIR__ . '/Support/FakeRespectifyClient.php';

require_once __DIR__ . '/../includes/class-respectify-wordpress-plugin.php';
