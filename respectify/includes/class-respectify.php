<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/vintagedave
 * @since      1.0.0
 *
 * @package    Respectify
 * @subpackage Respectify/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Respectify
 * @subpackage Respectify/includes
 * @author     David Millington <vintagedave@gmail.com>
 */
class Respectify {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Respectify_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $respectify    The string used to uniquely identify this plugin.
	 */
	protected $respectify;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'RESPECTIFY_VERSION' ) ) {
			$this->version = RESPECTIFY_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->respectify = 'respectify';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		// Intercept comments before they are inserted into the database
		add_filter('preprocess_comment', array($this, 'intercept_comment'));
		// JS and CSS must be included too
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Respectify_Loader. Orchestrates the hooks of the plugin.
	 * - Respectify_i18n. Defines internationalization functionality.
	 * - Respectify_Admin. Defines all hooks for the admin area.
	 * - Respectify_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-respectify-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-respectify-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-respectify-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-respectify-public.php';

		$this->loader = new Respectify_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Respectify_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Respectify_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Respectify_Admin( $this->get_respectify(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Respectify_Public( $this->get_respectify(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_respectify() {
		return $this->respectify;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Respectify_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}


	/**
	* Intercept and modify comments before they are inserted into the database.
	*
	* @param array $commentdata The comment data.
	* @return array Modified comment data.
	*/
	public function intercept_comment($commentdata) {
		// Example: Add a prefix to the comment content
		$commentdata['comment_content'] = '[Intercepted] ' . $commentdata['comment_content'];

		// Example: Reject comments containing certain words
		$forbidden_words = array('hello', 'world');
		foreach ($forbidden_words as $word) {
			if (stripos($commentdata['comment_content'], $word) !== false) {
				wp_send_json_error('Your comment contains forbidden words.');
			}
		}

		return $commentdata;
	}

	/**
     * Enqueue the JavaScript file to handle comment feedback
	 * Plus the CSS as well.
     */
    public function enqueue_scripts_and_styles() {
        wp_enqueue_script('respectify-comments', plugin_dir_url(__FILE__) . 'public/js/respectify-comments.js', array('jquery'), null, true);
		
		wp_enqueue_style('respectify-comments', plugin_dir_url(__FILE__) . 'public/css/respectify-comments.css');
	}
}
