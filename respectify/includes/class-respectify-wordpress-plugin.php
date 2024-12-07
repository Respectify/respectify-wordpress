<?php
namespace Respectify;
use RespectifyScoper\Respectify\RespectifyClientAsync;
use RespectifyScoper\Respectify\CommentScore;


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

//  require __DIR__ . '/../build/composer/ClassLoader.php';
//  error_log('Scoper: loaded ClassLoader successfully.');

// require __DIR__ . '/../build/respectify/respectify-php/src/RespectifyClientAsync.php';
// error_log('Main plugin: loaded RespectifyClientAsync successfully.');

require_once plugin_dir_path(__FILE__) . 'respectify-utils.php';


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
class RespectifyWordpressPlugin {

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
	 * Respectify client: the PHP library instance for accessing the Respectify API.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      RespectifyClient    $respectify_client    Respectify client.
	 */
	protected RespectifyClientAsync $respectify_client;

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


		// if (class_exists('RespectifyScoper\Respectify\RespectifyClientAsync')) {
		// 	error_log('RespectifyClientAsync class found.');
		// } else {
		// 	error_log('RespectifyClientAsync class not found.');
		// }
		
		$this->update_respectify_client();

		$email = get_option('respectify_email', '');
		$api_key = respectify_get_decrypted_api_key();
		$this->respectify_client = new RespectifyClientAsync($email, $api_key);

		// Intercept comments before they are inserted into the database
		add_filter('preprocess_comment', array($this, 'intercept_comment'));
		// JS and CSS must be included too
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));

		// Update the Respectify client when the email and API key are changed
		add_action('update_option_respectify_email', array($this, 'update_respectify_client'));
        add_action('update_option_respectify_api_key_encrypted', array($this, 'update_respectify_client'));
	}

    /**
     * Create or update the Respectify client instance.
     */
    public function update_respectify_client() {
		error_log('Updating Respectify client');
		
		$email = get_option('respectify_email', '');
        $api_key = respectify_get_decrypted_api_key();
        $this->respectify_client = new RespectifyClientAsync($email, $api_key);
    }

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Respectify_Loader. Orchestrates the hooks of the plugin.
	 * - Respectify_i18n. Defines internationalization functionality.
	 * - RespectifyAdmin. Defines all hooks for the admin area.
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
	 * Generate the Respectify ID for a post or page, given its content.
	 *
	 * @return string The custom ID, as string UUID.
	 */
	public function generate_respectify_article_id($post_content) {
		// Generate a custom ID for the post
		$article_id = null;

		//error_log('Creating article ID for content: ' . substr($post_content, 0, 50) . '...');

        $promise = $this->respectify_client->initTopicFromText($post_content);
        $caughtException = null;

        $promise->then(
            function ($id) use (&$article_id) {
                error_log('In generate_respectify_article_id, got article ID: ' . $id);
				$article_id = $id;
            },
            function ($e) use (&$caughtException) {
				error_log('Exception in initTopicFromText: ' . $e->getMessage());
                $caughtException = $e;
            }
        );

        $this->respectify_client->run();

        if ($caughtException) { 
            throw $caughtException;
        }

		error_log('In generate_respectify_article_id: Returning Respectify article ID: ' . $article_id);
		return $article_id;
	}

	/**
	 * Find or create a Respectify article ID for a post or page.
	 *
	 * @return string The custom ID, as string UUID.
	 */
	public function get_respectify_article_id($post_id) {
		// Check if the custom ID exists for the post
        $article_id = get_post_meta($post_id, '_respectify_article_id', true);
		error_log('Got article ID: -' . $article_id . '-');
		// Validate it really is a UUID
		//error_log('Checking article ID: ' . $article_id);

        // If the custom ID does not exist, create it and save it as post meta
        if (empty($article_id)) {
			error_log('Creating article ID for post ID: ' . $post_id);
			$post_content = get_post_field('post_content', $post_id);

            $article_id = $this->generate_respectify_article_id($post_content);
            //!!!update_post_meta($post_id, '_respectify_article_id', $article_id);
			error_log('Got NEW article ID: ' . $article_id);
        }	
		// Checking it's a GUID
		error_log('Returning Respectify article ID: ' . $article_id);
		assert(!empty($article_id));// && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $article_id));

		return $article_id;
	}

	/**
	 * Get Wordpress's own ID for a post or page.
	 *
	 * @return string Wordpress post ID.
	 */
	public function get_post_id_from_comment($commentdata) {
		// Get the post ID from the comment
		if (isset($commentdata['comment_post_ID'])) {
            return $commentdata['comment_post_ID'];
        }

        return null;
	}


	/**
	 * Evaluate a comment made on a Wordpress post or page, given the Respectify article ID
	 * for that post/page and the comment text.
	 *
	 * @return CommentScore The evaluated information for the comment
	 */
	public function evaluate_comment($respectify_article_id, $comment_text) {
		error_log('Evaluating comment: article id: ' . $respectify_article_id . ', comment: ' . substr($comment_text, 0, 50) . '...');

        $promise = $this->respectify_client->evaluateComment($respectify_article_id, $comment_text); 
        $caughtException = null;

		$res = null;

        $promise->then(
            function ($commentScore) use(&$res, &$caughtException) {
				if ($commentScore instanceof CommentScore) {
					$res = $commentScore;
				} else {
					$caughtException = new Exception('Comment score result is not an instance of CommentScore');
				}
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->respectify_client->run();

        if ($caughtException) {
            throw $caughtException;
        }

		return $res;
	}


	/**
	* Intercept and modify comments before they are inserted into the database.
	*
	* @param array $commentdata The comment data.
	* @return array Modified comment data.
	*/
	public function intercept_comment($commentdata) {
		$post_id = $this->get_post_id_from_comment($commentdata);
		if ($post_id == null) {
			error_log('Could not get post_id');
			wp_send_json_error('Could not get post_id');
			return; // !!! log an error, but allow the comment to go through
		}
		$article_id = $this->get_respectify_article_id($post_id);
		if ($article_id == null) {
			error_log('Could not get article_id');
			//wp_send_json_error('Could not get article_id');
			return; // !!! log an error, but allow the comment to go through
		}

		$comment_score = $this->evaluate_comment($article_id, $commentdata['comment_content']); 

		$comment_score_str = print_r($comment_score, true);
		wp_send_json_error('Comment score: ' . $comment_score_str);

		// Example: Add a prefix to the comment content
		//$commentdata['comment_content'] = '[Intercepted] ' . $commentdata['comment_content'];

		// Example: Reject comments containing certain words
		// $forbidden_words = array('hello', 'world');
		// foreach ($forbidden_words as $word) {
		// 	if (stripos($commentdata['comment_content'], $word) !== false) {
		// 		error_log('Comment rejected: ' . $commentdata['comment_content']);
		// 		wp_send_json_error('Your comment had an issue.');
		// 	}
		// }

		return $commentdata;
	}

	/**
     * Enqueue the JavaScript file to handle comment feedback
	 * Plus the CSS as well.
     */
    public function enqueue_scripts_and_styles() {
        wp_enqueue_script('respectify-comments', plugins_url('public/js/respectify-comments.js', __DIR__), array('jquery'), null, true);
		
		wp_enqueue_style('respectify-comments', plugins_url('public/css/respectify-comments.css', __DIR__));
	}
}
