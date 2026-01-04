<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/Respectify/respectify-wordpress
 * @since             0.2.0
 * @package           Respectify
 *
 * @wordpress-plugin
 * Plugin Name:       Respectify
 * Plugin URI:        https://respectify.ai
 * Description:       Healthy internet comments! Use Respectify to help your commenters post in a way that builds community.
 * Version:           0.2.3
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            Respectify Inc
 * Author URI:        https://respectify.ai/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       respectify
 */


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// If not found this will give errors re \Respectify\respectify_log()
if (file_exists(__DIR__ . '/includes/respectify-utils.php')) {
    require __DIR__ . '/includes/respectify-utils.php';
}

// Include the prefixed scoped Composer autoloader
if (file_exists(__DIR__ . '/build/autoload.php')) {
    require __DIR__ . '/build/autoload.php';
    \Respectify\respectify_log('Scoper: Composer autoloader included successfully.');
} else {
    \Respectify\respectify_log('Scoper: Composer autoloader not found.');
}

// require __DIR__ . '/build/respectify/respectify-php/src/RespectifyClientAsync.php';
if (class_exists('\RespectifyScoper\Respectify\RespectifyClientAsync')) {
    \Respectify\respectify_log('RespectifyClientAsync class found.');
} else {
    \Respectify\respectify_log('RespectifyClientAsync class not found.');
}


/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'RESPECTIFY_VERSION', '0.2.3' );

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
    \Respectify\respectify_log('Class Respectify\RespectifyWordpressPlugin not found');
    throw new \Exception('Class Respectify\RespectifyWordpressPlugin not found');
}

// Settings page
require_once plugin_dir_path( __FILE__ ) . 'admin/settings-page.php';


/*
    Add a Settings link on the main Plugins page, so you can configure it from the plugin list
    without hunting through the Settings admin page submenu.
*/
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'respectify_add_plugin_action_links');

function respectify_is_api_key_configured() {
    $email = get_option(\Respectify\OPTION_EMAIL);
    $api_key = \Respectify\respectify_get_decrypted_api_key();

    return !empty($email) && !empty($api_key);
}

function respectify_add_plugin_action_links($links) {
    if (respectify_is_api_key_configured()) {
        $settings_link = '<a href="options-general.php?page=respectify">' . esc_html__('Settings', 'respectify') . '</a>';
    } else {
        $settings_link = '<a href="options-general.php?page=respectify">⚠️ ' . esc_html__('Please set up your API Key', 'respectify') . '</a>';
    }
    array_unshift($links, $settings_link);
    return $links;
}


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
