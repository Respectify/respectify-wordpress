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
 * @since             1.0.0
 * @package           Respectify
 *
 * @wordpress-plugin
 * Plugin Name:       Respectify for Wordpress
 * Plugin URI:        https://respectify.org
 * Description:       Use Respectify to ensure the comments people write on your site demonstrate healthy online conversation.
 * Version:           1.0.0
 * Author:            David Millington
 * Author URI:        https://github.com/vintagedave/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       respectify
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Include the Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'RESPECTIFY_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-respectify-activator.php
 */
function activate_respectify() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-respectify-activator.php';
	Respectify_Activator::activate();
}

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
require plugin_dir_path( __FILE__ ) . 'includes/class-respectify.php';

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

	$plugin = new RespectifyWordpressPlugin();
	$plugin->run();

}
run_respectify();
