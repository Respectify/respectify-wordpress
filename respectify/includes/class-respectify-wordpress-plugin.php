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
require_once plugin_dir_path(__FILE__) . 'respectify-constants.php';


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
		$this->version = \Respectify\RESPECTIFY_VERSION;

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

		$email = get_option(\Respectify\OPTION_EMAIL, '');
		$api_key = respectify_get_decrypted_api_key();
		$this->respectify_client = new RespectifyClientAsync($email, $api_key);

	    // Hook the AJAX handler for logged-in users
		add_action('wp_ajax_respectify_submit_comment', array($this, 'ajax_submit_comment'));
		// Hook the AJAX handler for non-logged-in users
		add_action('wp_ajax_nopriv_respectify_submit_comment', array($this, 'ajax_submit_comment'));
		// Intercept comments before they are inserted into the database - non-AJAX, eg when Javascript is disabled
		add_filter('preprocess_comment', array($this, 'respectify_preprocess_comment_no_js'));
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
		
		$email = get_option(\Respectify\OPTION_EMAIL, '');
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
		return 'respectify';
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
            update_post_meta($post_id, '_respectify_article_id', $article_id);
			error_log('Got NEW article ID: ' . $article_id);
        }	
		// Checking it's a GUID
		error_log('Returning Respectify article ID: ' . $article_id);
		assert(!empty($article_id));

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
		// How this all works:
		// 1. AJAX Submission:
		//    The JavaScript code submits the comment via AJAX to the ajax_submit_comment handler.
		//    The AJAX request includes the action 'respectify_submit_comment'.
		// 2. ajax_submit_comment method:
		//    Processes the comment data using wp_handle_comment_submission.
		//    Calls the intercept_comment method to determine what to do with the comment.
		// 3. intercept_comment method:
		//    Evaluates the comment and decides on an action: post, reject with feedback, or trash. See ACTION_ constants

		// Returns an array of comment data, or a WP_Error object if the comment should be rejected
		// (including sending feedback to the user)

		error_log('Intercepting comment AJAX: ' . $commentdata['comment_content']);


		$post_id = $commentdata['comment_post_ID'];
		$article_id = $this->get_respectify_article_id($post_id);

		if (!$article_id) {
			error_log('Invalid article ID: ' . $article_id);
			// Return an error
			return new \WP_Error('invalid_article_id', 'Invalid article ID.');
		}

		$comment_text = sanitize_text_field($commentdata['comment_content']);
		$comment_score = $this->evaluate_comment($article_id, $comment_text);

		// Your custom logic based on settings
		$action = $this->get_comment_action($comment_score);

		error_log('Comment action: ' . $action);

		if ($action === \Respectify\ACTION_PUBLISH) {
			// Allow comment to be posted
			// Return the comment data to proceed
			return $commentdata;
		} elseif ($action === \Respectify\ACTION_REVISE) {
			// Provide feedback and ask user to edit
			$feedback_html = $this->build_feedback_html($comment_score);
			if (empty($feedback_html)) {
				// No feedback to show
				error_log('No feedback to show, sending generic revise message');
				$feedback_html = '<div class="respectify-feedback">Please revise your comment.</div>';
			}
			return new \WP_Error(\Respectify\ACTION_REVISE, '<div class="respectify-feedback">' . $feedback_html . '</div>');
		} elseif ($action === \Respectify\ACTION_DELETE) {
			// Reject comment without feedback
			return new \WP_Error(\Respectify\ACTION_DELETE, 'Your comment was rejected.');
		}
	}

	/**
     * When a comment was sent for revision, we need to show feedback to the user. This
	 * builds that feedback (as HTML)  
     *
     * @param object $comment_score The comment evaluation result.
     * @return string (HTML) feedback to show to the user.
     */
	private function build_feedback_html($comment_score) {
		if ($comment_score->isSpam) {
			return "This looks like spam.";
		}

		$feedback = "";

		// First, if the minimum score is too low
		$minAcceptableScore = isset($revise_settings['min_score']) ? $revise_settings['min_score'] : 3;
		if ($comment_score->overallScore < $minAcceptableScore) {
			$feedback .= "We aim for thoughtful, engaged conversation."; // Don't give a negative 'didn't meet the score' message; give a goal
		}

		error_log('Building feedback HTML for comment score: '. print_r($comment_score, true));

		$revise_settings = get_option(\Respectify\OPTION_REVISE_SETTINGS, \Respectify\REVISE_DEFAULT_SETTINGS);

		$feedback .= "<ul>"; // Remainder of feedback is a list

		$revise_on_low_effort_handling = isset($revise_settings['low_effort']) ? $revise_settings['low_effort'] : \Respectify\REVISE_DEFAULT_LOW_EFFORT;
		error_log('Low effort setting: ' . $revise_on_low_effort_handling);
		error_log('Low effort?: ' . $comment_score->appearsLowEffort);
		if ($comment_score->appearsLowEffort && $revise_on_low_effort_handling) {
			$feedback .= "<li>Your comment appears not to contribute to the conversation. Please provide a thoughtful response.</li>";
		}

		$revise_on_logical_fallacies = isset($revise_settings['logical_fallacies']) ? $revise_settings['logical_fallacies'] : \Respectify\REVISE_DEFAULT_LOGICAL_FALLACIES;	
		// !!! Should  not reat as strings, just see if the array is empty or not
		$hasValidFallacies = !empty($comment_score->logicalFallacies);
		error_log('Logical fallaces setting: ' . $revise_on_logical_fallacies);
		error_log('Logical fallacies?: ' . $hasValidFallacies);
		if ($hasValidFallacies && $revise_on_logical_fallacies) {
			$feedback .= "<li>Your comment contains logic that seems right, but when looked at doesn't hold together, known as a '<a href=\"https://academicguides.waldenu.edu/writingcenter/writingprocess/logicalfallacies \" target=\"_new\">logical fallacy</a>'.</li>";
			// List each logical fallace in a sublist
			$feedback .= "<ul>";
			foreach ($comment_score->logicalFallacies as $fallacy) {
				$feedback .= "<li><strong>'" . $fallacy->quotedLogicalFallacy . "':</strong> " . $fallacy->explanation;
				if (!empty($phrase->suggestedRewrite)) {
					$feedback .= "<br/><em>Try something like:</em> '" . $phrase->suggestedRewrite . "'";
				}
				$feedback .= "</li>";
			}
			$feedback .= "</ul>";
		}

		$revise_on_phrases = isset($revise_settings['objectionable_phrases']) ? $revise_settings['objectionable_phrases'] : \Respectify\REVISE_DEFAULT_OBJECTIONABLE_PHRASES;	
		$hasValidObjectionablePhrases = !empty($comment_score->objectionablePhrases);
		error_log('Objectionable phrases setting: ' . $revise_on_phrases);
		error_log('Objectionable phrases?: ' . $hasValidObjectionablePhrases);
		if ($hasValidObjectionablePhrases && $revise_on_phrases) {
			$feedback .= "<li>Your comment contains potentially objectionable language or phrases.</li>";
			// List each phrase in a sublist
			$feedback .= "<ul>";
			foreach ($comment_score->objectionablePhrases as $phrase) {
				$feedback .= "<li><strong>'" . $phrase->quotedObjectionablePhrase . "':</strong> " . $phrase->explanation;
				if (!empty($phrase->suggestedRewrite)) {
					$feedback .= "<br/><em>Try something like:</em> '" . $phrase->suggestedRewrite . "'";
				}
				$feedback .= "</li>";
			}
			$feedback .= "</ul>";
		}

		$revise_on_negative_tone = isset($revise_settings['negative_tone']) ? $revise_settings['negative_tone'] : \Respectify\REVISE_DEFAULT_NEGATIVE_TONE;
		$hasValidNegativeTone = !empty($comment_score->negativeTonePhrases);
		error_log('Negative tone setting: ' . $revise_on_negative_tone);
		error_log('Negative tone?: ' . $hasValidNegativeTone);
		if ($hasValidNegativeTone && $revise_on_negative_tone) {
			$feedback .= "<li>Your comment may not contribute to a healthy conversation.</li>";
			// List each phrase in a sublist
			$feedback .= "<ul>";
			foreach ($comment_score->negativeTonePhrases as $phrase) {
				$feedback .= "<li><strong>'" . $phrase->quotedNegativeTonePhrase . "':</strong> " . $phrase->explanation;
				if (!empty($phrase->suggestedRewrite)) {
					$feedback .= "<br/><em>Try something like:</em> '" . $phrase->suggestedRewrite . "'";
				}
				$feedback .= "</li>";
			}
			$feedback .= "</ul>";
		}

		$feedback .= "</ul>";

		$feedback .= "<p>Can you edit your comment to take the above feedback into account?</p>";

		return $feedback;
	}

	/**
     * Determine the action to take on the comment based on score and settings.
     *
     * @param object $comment_score The comment evaluation result.
     * @return string Action to take -- see ACTION_ constants
     */
    private function get_comment_action($comment_score) {
		// Spam is an easy early exit
		if ($comment_score->isSpam) {
			$spam_handling = get_option(\Respectify\OPTION_SPAM_HANDLING, \Respectify\DEFAULT_SPAM_HANDLING); // An ACTION_ constant
			assert($spam_handling === \Respectify\ACTION_DELETE || $spam_handling === \Respectify\ACTION_REVISE);
			return $spam_handling;
		}

		// These are true when, if any (eg) logical fallacies exist, the comment must be revised
		// Wordpress seems to only save non-default items in this array? So need to check if the key exists
		// and use the default if not
		$revise_settings = get_option(\Respectify\OPTION_REVISE_SETTINGS, \Respectify\REVISE_DEFAULT_SETTINGS);

		error_log('Revise settings: ' . print_r($revise_settings, true));

		error_log('Comment score object: ' . print_r($comment_score, true));

		// First, if the minimum score is too low
		$minAcceptableScore = isset($revise_settings['min_score']) ? $revise_settings['min_score'] : \Respectify\REVISE_DEFAULT_MIN_SCORE;
		if ($comment_score->overallScore < $minAcceptableScore) {
			error_log('Score too low - decision: ' . \Respectify\ACTION_REVISE);
			return \Respectify\ACTION_REVISE;
		}

		if ($comment_score->appearsLowEffort) {
			// Setting may not be set, default true
			$revise_on_low_effort_handling = isset($revise_settings['low_effort']) ? $revise_settings['low_effort'] : \Respectify\REVISE_DEFAULT_LOW_EFFORT;
			$low_effort_decision = $revise_on_low_effort_handling ? \Respectify\ACTION_REVISE :  \Respectify\ACTION_PUBLISH;
			error_log('Low effort - decision: ' . $low_effort_decision);
			return $low_effort_decision;
		}

		// Sanitizes: Non-empty array, and any array items are not empty
		$hasValidFallacies = !empty($comment_score->logicalFallacies);
		if ($hasValidFallacies) {
			// Setting may not be set, default true
			$revise_on_logical_fallacies = isset($revise_settings['logical_fallacies']) ? $revise_settings['logical_fallacies'] : \Respectify\REVISE_DEFAULT_LOGICAL_FALLACIES;
			$logical_fallacies_decision = $revise_on_logical_fallacies ? \Respectify\ACTION_REVISE : \Respectify\ACTION_PUBLISH;
			error_log('Logical fallacies - decision: ' . $logical_fallacies_decision);
			return $logical_fallacies_decision;
		}

		// Sanitizes: Non-empty array, and any array items are not empty
		$hasValidObjectionablePhrases = !empty($comment_score->objectionablePhrases);
		if ($hasValidObjectionablePhrases) {
			// Setting may not be set, default true
			$revise_on_phrases = isset($revise_settings['objectionable_phrases']) ? $revise_settings['objectionable_phrases'] : \Respectify\REVISE_DEFAULT_OBJECTIONABLE_PHRASES;
			$phrases_decision = $revise_on_phrases ? \Respectify\ACTION_REVISE : \Respectify\ACTION_PUBLISH;
			error_log('Objectionable phrases - decision: ' . $phrases_decision);
			return $phrases_decision;
		}

		// Sanitizes: Non-empty array, and any array items are not empty
		$hasValidNegativeTone = !empty($comment_score->negativeTonePhrases);
		if ($hasValidNegativeTone) {
			// Setting may not be set, default true
			$revise_on_negative_tone = isset($revise_settings['negative_tone']) ? $revise_settings['negative_tone'] : \Respectify\REVISE_DEFAULT_NEGATIVE_TONE;
			$negative_tone_decision = $revise_on_negative_tone ? \Respectify\ACTION_REVISE : \Respectify\ACTION_PUBLISH;
			error_log('Negative tone - decision: ' . $negative_tone_decision);
			return $negative_tone_decision;
		}

		error_log("Fallback decision: " . \Respectify\ACTION_PUBLISH);
		return \Respectify\ACTION_PUBLISH;
    }

    /**
     * AJAX handler for submitting comments.
     */
	public function ajax_submit_comment() {
		error_log('ajax_submit_comment called');

		// Manually prepare comment data from $_POST
		$commentdata = array(
			'comment_post_ID'      => isset($_POST['comment_post_ID']) ? absint($_POST['comment_post_ID']) : 0,
			'comment_author'       => isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '',
			'comment_author_email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
			'comment_author_url'   => isset($_POST['url']) ? esc_url_raw($_POST['url']) : '',
			'comment_content'      => isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '',
			'comment_type'         => '', // Empty for regular comments
			'comment_parent'       => isset($_POST['comment_parent']) ? absint($_POST['comment_parent']) : 0,
			'user_id'              => get_current_user_id(),
			'comment_author_IP'    => $_SERVER['REMOTE_ADDR'],
			'comment_agent'        => $_SERVER['HTTP_USER_AGENT'],
			'comment_date'         => current_time('mysql'),
			'comment_approved'     => 1, // Adjust approval status as needed
		);
	
		// Intercept and process the comment
		$result = $this->intercept_comment($commentdata);
	
		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
				'data'    => $result->get_error_data(),
			]);
		} else {
			// Insert the comment into the database
			$comment_id = wp_new_comment($result, true); // Pass $result which contains the processed comment data
	
			if (is_wp_error($comment_id)) {
				wp_send_json_error(['message' => $comment_id->get_error_message()]);
			} else {
				// Comment inserted successfully
				wp_send_json_success([
					'message'    => 'Your comment has been posted.',
					'comment_id' => $comment_id,
				]);
			}
		}
	}

	// For when Javascript is turned off
	public function respectify_preprocess_comment_no_js($commentdata) {
		// Intercept and evaluate the comment
		error_log('Intercepting comment with JS turned off: ' . $commentdata['comment_content']);

		$result = $this->intercept_comment($commentdata);

		error_log('Result of the comment interception: ' . print_r($result, true));

		if (is_wp_error($result)) {
			error_log('Comment rejected: ' . $result->get_error_message());
			// Handle the error by preventing the comment from being saved
			wp_die(
				esc_html($result->get_error_message()),
				__('Comment Submission Error', 'respectify'),
				array('back_link' => true)
			);
		}
	
		error_log('Comment allowed: ' . $result['comment_content']);
		// Return processed comment data
		return $result;
	}

	/**
     * Enqueue the JavaScript file to handle comment feedback
	 * Plus the CSS as well.
     */
    public function enqueue_scripts_and_styles() {
		wp_enqueue_script('respectify-comments-js', plugins_url('public/js/respectify-comments.js', __DIR__), array('jquery'), null, true);
	
		wp_localize_script('respectify-comments-js', 'respectify_ajax_object', array(
			'ajax_url' => admin_url('admin-ajax.php'),
		));
	
		wp_enqueue_style('respectify-comments', plugins_url('public/css/respectify-comments.css', __DIR__));
	}
}
