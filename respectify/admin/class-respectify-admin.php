<?php
namespace Respectify; 

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/vintagedave
 * @since      1.0.0
 *
 * @package    Respectify
 * @subpackage Respectify/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Respectify
 * @subpackage Respectify/admin
 * @author     David Millington <dave@respectify.ai>
 */
class Respectify_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $respectify    The ID of this plugin.
	 */
	private $respectify;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $respectify       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $respectify, $version ) {

		$this->respectify = $respectify;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->respectify, plugin_dir_url( __FILE__ ) . 'css/respectify-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->respectify, plugin_dir_url( __FILE__ ) . 'js/respectify-admin.js', array( 'jquery' ), $this->version, false );
	}

}
