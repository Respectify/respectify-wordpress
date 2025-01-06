<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/vintagedave
 * @since             0.2.0
 * @package           Respectify
 *
 * @wordpress-plugin
 * Plugin Name:       Respectify -- Healthy Internet Comments
 * Plugin URI:        https://respectify.org
 * Description:       Use Respectify to ensure the comments people write on your site demonstrate healthy online conversation.
 * Version:           0.2.0
 * Author:            David Millington
 * Author URI:        https://github.com/vintagedave/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       respectify
 * Domain Path:       /languages
 */

 // !!! Should be temporary, while debugging loading only
error_reporting(E_ALL);
ini_set('display_errors', 1);

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Have to load the classloader, it isn't found for some reason otherwise
// require __DIR__ . '/build/composer/ClassLoader.php';
// error_log('Scoper: loaded ClassLoader successfully.');

// Include the prefixed scoped Composer autoloader
if (file_exists(__DIR__ . '/build/autoload.php')) {
    require __DIR__ . '/build/autoload.php';
	error_log('Scoper: Composer autoloader included successfully.');
} else {
    error_log('Scoper: Composer autoloader not found.');
}

// require __DIR__ . '/build/respectify/respectify-php/src/RespectifyClientAsync.php';
if (class_exists('\RespectifyScoper\Respectify\RespectifyClientAsync')) {
    error_log('RespectifyClientAsync class found.');
} else {
    error_log('RespectifyClientAsync class not found.');
}

// if (!class_exists('Respectify\RespectifyClientAsync')) {
//     error_log('Class Respectify\RespectifyClientAsync not found');
//     throw new Exception('Class Respectify\RespectifyClientAsync not found');
// }

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'RESPECTIFY_VERSION', '1.0.0' );

use Respectify\Respectify_Activator;

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-respectify-activator.php
 */
function activate_respectify() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-respectify-activator.php';
	Respectify_Activator::activate();
}

use Respectify\Respectify_Deactivator;

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-respectify-deactivator.php
 */
function deactivate_respectify() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-respectify-deactivator.php';
	Respectify_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_respectify' );
register_deactivation_hook( __FILE__, 'deactivate_respectify' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-respectify-wordpress-plugin.php';


if (!class_exists('Respectify\RespectifyWordpressPlugin')) {
    error_log('Class Respectify\RespectifyWordpressPlugin not found');
    throw new Exception('Class Respectify\RespectifyWordpressPlugin not found');
}

// Settings page
require_once plugin_dir_path( __FILE__ ) . 'admin/settings-page.php';


use Respectify\RespectifyWordpressPlugin;

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_respectify() {

	$plugin = new Respectify\RespectifyWordpressPlugin();
	$plugin->run();

}
run_respectify();
