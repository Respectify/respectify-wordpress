<?php
namespace Respectify;

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/vintagedave
 * @since      1.0.0
 *
 * @package    Respectify
 * @subpackage Respectify/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Respectify
 * @subpackage Respectify/public
 * @author     David Millington <dave@respectify.ai>
 */
class Respectify_Public {

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
	 * @param      string    $respectify       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $respectify, $version ) {

		$this->respectify = $respectify;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Respectify_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Respectify_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->respectify, plugin_dir_url( __FILE__ ) . 'css/respectify-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Respectify_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Respectify_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->respectify, plugin_dir_url( __FILE__ ) . 'js/respectify-public.js', array( 'jquery' ), $this->version, false );

		// Add localization data
		wp_localize_script( $this->respectify, 'respectify_comments_i18n', array(
			'submitting' => esc_html__('Submitting your comment; please wait a moment...', 'respectify'),
			'error_occurred' => esc_html__('An error occurred.', 'respectify'),
			'error_try_again' => esc_html__('An error occurred. Please try again.', 'respectify'),
		) );

	}

}
