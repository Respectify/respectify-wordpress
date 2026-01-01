<?php
namespace Respectify;
use RespectifyScoper\Respectify\RespectifyClientAsync;
use RespectifyScoper\Respectify\CommentScore;
use RespectifyScoper\Respectify\MegaCallResult;
use RespectifyScoper\Respectify\DogwhistleResult;


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
 * @author     David Millington <dave@respectify.ai>
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

		$this->update_respectify_client();

	    // Hook the AJAX handler for logged-in users
		add_action('wp_ajax_respectify_submit_comment', array($this, 'ajax_submit_comment'));
		// Hook the AJAX handler for non-logged-in users
		add_action('wp_ajax_nopriv_respectify_submit_comment', array($this, 'ajax_submit_comment'));
		// Intercept comments before they are inserted into the database - non-AJAX, eg when Javascript is disabled
		add_filter('preprocess_comment', array($this, 'respectify_preprocess_comment_no_js'));
		// JS and CSS must be included too
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts_and_styles'));

		// Nonce for the comment form
		add_action('comment_form_after_fields', [$this, 'add_comment_nonce']);
        add_action('comment_form_logged_in_after', [$this, 'add_comment_nonce']);

		// Update the Respectify client when the email and API key are changed
		add_action('update_option_respectify_email', array($this, 'update_respectify_client'));
        add_action('update_option_respectify_api_key_encrypted', array($this, 'update_respectify_client'));
		// and when the URL is changed!
		add_action('update_option_respectify_base_url', array($this, 'update_respectify_client'));
		add_action('update_option_respectify_api_version', array($this, 'update_respectify_client'));
	}

    /**
     * Create or update the Respectify client instance.
     */
    public function update_respectify_client() {
		\Respectify\respectify_log('Updating Respectify client');
        $this->respectify_client = \Respectify\respectify_create_client();
    }

	/**
     * Adds a nonce field to the comment form for security.
     */
    public function add_comment_nonce() {
        wp_nonce_field('respectify_submit_comment', 'respectify_nonce');
    }

	/**
	 * Verifies the comment nonce.
	 *
	 * Terminates execution with an error if the nonce is invalid.
	 */
	private function verify_comment_nonce() {
		// Retrieve and sanitize the nonce from POST data
		$nonce = isset($_POST['respectify_nonce']) ? sanitize_text_field(wp_unslash($_POST['respectify_nonce'])) : '';

		// Verify the nonce
		if ( ! wp_verify_nonce($nonce, 'respectify_submit_comment') ) {
			\Respectify\respectify_log('Invalid nonce.');

			if ( wp_doing_ajax() ) {
				wp_send_json_error(['message' => 'Invalid nonce.']);
				wp_die(); // Terminate execution
			} else {
				wp_die(esc_html__('Invalid comment submission.', 'respectify'), 400);
			}
		}
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

		//\Respectify\respectify_log('Creating article ID for content: ' . substr($post_content, 0, 50) . '...');

        $promise = $this->respectify_client->initTopicFromText($post_content);
        $caughtException = null;

        $promise->then(
            function ($id) use (&$article_id) {
                \Respectify\respectify_log('In generate_respectify_article_id, got article ID: ' . $id);
				$article_id = $id;
            },
            function ($e) use (&$caughtException) {
				\Respectify\respectify_log('Exception in initTopicFromText: ' . $e->getMessage());
                $caughtException = $e;
            }
        );

        $this->respectify_client->run();

        if ($caughtException) { 
            throw $caughtException;
        }

		\Respectify\respectify_log('In generate_respectify_article_id: Returning Respectify article ID: ' . $article_id);
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
		\Respectify\respectify_log('Got article ID: -' . $article_id . '-');
		// Validate it really is a UUID
		//\Respectify\respectify_log('Checking article ID: ' . $article_id);

        // If the custom ID does not exist, create it and save it as post meta
        if (empty($article_id)) {
			\Respectify\respectify_log('Creating article ID for post ID: ' . $post_id);
			$post_content = get_post_field('post_content', $post_id);

            $article_id = $this->generate_respectify_article_id($post_content);
            update_post_meta($post_id, '_respectify_article_id', $article_id);
			\Respectify\respectify_log('Got NEW article ID: ' . $article_id);
        }	
		// Checking it's a GUID
		\Respectify\respectify_log('Returning Respectify article ID: ' . $article_id);
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
	 * @param string $respectify_article_id The Respectify article ID
	 * @param string $comment_text The comment text to evaluate
	 * @param string|null $reply_to_comment_text The text of the comment being replied to
	 * @param string|null $author_name The name of the comment author
	 * @param string|null $author_email The email of the comment author
	 * @return MegaCallResult The evaluated information for the comment
	 */
	public function evaluate_comment($respectify_article_id, $comment_text, $reply_to_comment_text = null, $author_name = null, $author_email = null) {
		\Respectify\respectify_log('Evaluating comment: article id: ' . $respectify_article_id . ', comment: ' . substr($comment_text, 0, 50) . '...');

        // Prepend author information to the comment text - email addresses can be used for spam, for example
		// Should look like this:
		// Author: John Doe
		// Author email: john.doe@example.com
		// 
		// This is a test comment.
		// More comment text here... 
		
        $full_comment_text = $comment_text;
        if (!empty($author_name) || !empty($author_email)) {
            $full_comment_text = '';
            if (!empty($author_name)) {
                $full_comment_text .= "Author: " . $author_name . "\n";
            }
            if (!empty($author_email)) {
                $full_comment_text .= "Author email: " . $author_email . "\n";
            }
            $full_comment_text .= "\n" . $comment_text;
        }

        // Get assessment settings to determine which services to request
        $assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);
        $services = array();

        // Check all enabled services based on user settings
        if ($assessment_settings['check_spam']) {
            $services[] = 'spam';
        }
        if ($assessment_settings['assess_health']) {
            $services[] = 'commentscore';
        }
        if ($assessment_settings['check_relevance'] && $respectify_article_id) {
            $services[] = 'relevance';
        }
        if (isset($assessment_settings['check_dogwhistle']) && $assessment_settings['check_dogwhistle'] && $respectify_article_id) {
            $services[] = 'dogwhistle';
        }

        // If no services are enabled, default to all available services
        // The server will enforce plan limits and return PaymentRequiredException for unauthorized services
        if (empty($services)) {
            $services = ['spam', 'commentscore', 'relevance'];
        }

        // Get banned topics if relevance checking is enabled and we have an article ID
        $banned_topics = null;
        if ($assessment_settings['check_relevance'] && $respectify_article_id) {
            $banned_topics_str = get_option(\Respectify\OPTION_BANNED_TOPICS, '');
            if (!empty($banned_topics_str)) {
                // Split by newlines and remove empty lines
                $banned_topics = array_values(array_filter(explode("\n", $banned_topics_str)));
                if (empty($banned_topics)) {
                    $banned_topics = null;
                }
            }
        }

        // Get sensitive topics and dogwhistle examples if dogwhistle checking is enabled
        $sensitive_topics = null;
        $dogwhistle_examples = null;
        if (in_array('dogwhistle', $services)) {
            $sensitive_topics_str = get_option(\Respectify\OPTION_SENSITIVE_TOPICS, '');
            if (!empty($sensitive_topics_str)) {
                $sensitive_topics = array_values(array_filter(explode("\n", $sensitive_topics_str)));
                if (empty($sensitive_topics)) {
                    $sensitive_topics = null;
                }
            }
            
            $dogwhistle_examples_str = get_option(\Respectify\OPTION_DOGWHISTLE_EXAMPLES, '');
            if (!empty($dogwhistle_examples_str)) {
                $dogwhistle_examples = array_values(array_filter(explode("\n", $dogwhistle_examples_str)));
                if (empty($dogwhistle_examples)) {
                    $dogwhistle_examples = null;
                }
            }
        }

        $promise = $this->respectify_client->megacall(
            comment: $full_comment_text,
            articleContextId: $respectify_article_id,
            services: $services,
            bannedTopics: $banned_topics,
            replyToComment: $reply_to_comment_text,
            sensitiveTopics: $sensitive_topics,
            dogwhistleExamples: $dogwhistle_examples
        ); 
        $caughtException = null;

		$res = null;

        $promise->then(
            function ($megaResult) use(&$res, &$caughtException) {
                if ($megaResult instanceof MegaCallResult) {
                    $res = $megaResult;
                } else {
                    $caughtException = new Exception('Mega call result is not an instance of MegaCallResult');
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

		// Log the incoming comment data, but be careful not to log sensitive info
		$log_data = array(
			'post_id' => isset($commentdata['comment_post_ID']) ? $commentdata['comment_post_ID'] : 'missing',
			'parent_id' => isset($commentdata['comment_parent']) ? $commentdata['comment_parent'] : 'none',
			'content_length' => isset($commentdata['comment_content']) ? strlen($commentdata['comment_content']) : 0,
			'has_author' => isset($commentdata['comment_author']) && !empty($commentdata['comment_author']),
			'has_email' => isset($commentdata['comment_author_email']) && !empty($commentdata['comment_author_email']),
			'is_ajax' => wp_doing_ajax(),
			'timestamp' => current_time('mysql')
		);
		\Respectify\respectify_log('Processing comment: ' . wp_json_encode($log_data));

		// Verify nonce
		// This is ALREADY done in ajax_submit_comment, and in respectify_preprocess_comment_no_js
		// But no harm doing it here as well to be safe.
		$this->verify_comment_nonce();

		// Validate required fields - be flexible but log issues
		$missing_fields = array();
		if (empty($commentdata['comment_post_ID'])) {
			$missing_fields[] = 'post_ID';
		}
		if (empty($commentdata['comment_content'])) {
			$missing_fields[] = 'content';
		}
		if (!empty($missing_fields)) {
			\Respectify\respectify_log('Missing required fields: ' . implode(', ', $missing_fields));
			// Return an error if content is missing
			if (in_array('content', $missing_fields)) {
				return new \WP_Error('missing_content', 'Comment content is required.');
			}
			// Don't fail for other missing fields - WordPress will handle them
		}

		// Get assessment settings to determine which services we need
		$assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);

		// Only get article ID if we need it for relevance checking
		$article_id = null;
		if ($assessment_settings['check_relevance']) {
			$post_id = $commentdata['comment_post_ID'];
			try {
				$article_id = $this->get_respectify_article_id($post_id);
			} catch (\Exception $e) {
				\Respectify\respectify_log('Exception getting article ID: ' . $e->getMessage());
				// Handle API error: notify admin, hold comment for moderation
				$held_comment = \Respectify\respectify_handle_api_error($e->getMessage(), $commentdata);
				wp_insert_comment($held_comment);
				return new \WP_Error('api_error', \Respectify\respectify_get_commenter_error_message());
			}

			if (!$article_id) {
				\Respectify\respectify_log('Invalid article ID: ' . $article_id);
				// Return an error
				return new \WP_Error('invalid_article_id', 'Invalid article ID.');
			}
		}

		// Wordpress adds slashes, so remove them before sanitising to avoid double slashes
		// Caught by words like "don't": visible to the user as "don\'t"
		$comment_text = sanitize_textarea_field(wp_unslash($commentdata['comment_content']));
		
		// Ensure we have valid comment text after sanitization
		if (empty($comment_text)) {
			\Respectify\respectify_log('Comment text is empty after sanitization');
			return new \WP_Error('empty_comment', 'Comment content is required.');
		}

		// Get the comment being replied to if this is a reply
		$reply_to_comment_text = null;
		if (!empty($commentdata['comment_parent'])) {
			$parent_comment = get_comment($commentdata['comment_parent']);
			if ($parent_comment) {
				// Verify parent comment belongs to the same post
				if ($parent_comment->comment_post_ID != $commentdata['comment_post_ID']) {
					\Respectify\respectify_log('Parent comment belongs to different post - ignoring parent context');
				} else {
					$reply_to_comment_text = sanitize_textarea_field(wp_unslash($parent_comment->comment_content));
					\Respectify\respectify_log('Found comment being replied to: ' . substr($reply_to_comment_text, 0, 50) . '...');
				}
			} else {
				\Respectify\respectify_log('Parent comment not found - ignoring parent context');
			}
		}

		// Get author information if available
		$author_name = isset($commentdata['comment_author']) ? sanitize_text_field(wp_unslash($commentdata['comment_author'])) : null;
		$author_email = isset($commentdata['comment_author_email']) ? sanitize_email(wp_unslash($commentdata['comment_author_email'])) : null;

		// Log evaluation attempt
		\Respectify\respectify_log('Evaluating comment with Respectify API...');

		// Evaluate the comment
		try {
			$evaluation = $this->evaluate_comment($article_id, $comment_text, $reply_to_comment_text, $author_name, $author_email);
		} catch (\Exception $e) {
			\Respectify\respectify_log('Exception evaluating comment: ' . $e->getMessage());
			// Handle API error: notify admin, hold comment for moderation
			$held_comment = \Respectify\respectify_handle_api_error($e->getMessage(), $commentdata);
			wp_insert_comment($held_comment);
			return new \WP_Error('api_error', \Respectify\respectify_get_commenter_error_message());
		}

		if (is_wp_error($evaluation)) {
			\Respectify\respectify_log('Error evaluating comment: ' . $evaluation->get_error_message());
			$commentdata['comment_approved'] = 0; // Set to pending rather than spam
			$commentdata['comment_type'] = 'respectify_error';
			$commentdata['comment_meta'] = array(
				'respectify_error' => $evaluation->get_error_message(),
				'respectify_evaluated_at' => current_time('mysql')
			);
			// Return error to prevent comment from being posted
			return new \WP_Error('evaluation_error', 'There was an error handling your comment. Please try again later.');
		} else {
			// Handle the evaluation result
			$action = $this->get_comment_action($evaluation);
			$feedback_html = $this->build_feedback_html($evaluation);
			
			\Respectify\respectify_log('Comment evaluation result: ' . $action);
			if ($feedback_html) {
				\Respectify\respectify_log('Feedback: ' . substr($feedback_html, 0, 150) . '...');
			}

			// Log the final decision
			$decision_log = array(
				'action' => $action,
				'has_feedback' => !empty($feedback_html),
				'comment_length' => strlen($comment_text),
				'has_parent' => !empty($reply_to_comment_text),
				'timestamp' => current_time('mysql')
			);
			\Respectify\respectify_log('Final decision: ' . wp_json_encode($decision_log));

			if ($action === \Respectify\ACTION_PUBLISH) {
				// Comment is good to go - will be stored in database and displayed
				$commentdata['comment_approved'] = 1;
				$commentdata['comment_type'] = 'respectify_approved';
				$commentdata['comment_meta'] = array(
					'respectify_evaluation' => $evaluation,
					'respectify_action' => $action,
					'respectify_evaluated_at' => current_time('mysql')
				);
				\Respectify\respectify_log('Comment result before insertion: ' . wp_json_encode($commentdata));
				return $commentdata;
			} elseif ($action === \Respectify\ACTION_REVISE) {
				// Comment needs revision - we will NOT store it in the database
				// Instead, we return a WP_Error which will be caught by the AJAX handler
				// The error message (feedback_html) will be shown to the user
				// The user can then revise their comment and try again
				$feedback_html = $this->build_feedback_html($evaluation);
				if (empty($feedback_html)) {
					// No feedback to show
					$feedback_html = 'Please revise your comment to be more respectful.';
				}
				// Note: setting comment_approved = 0 is not used since we return WP_Error
				// before wp_new_comment() is called
				$commentdata['comment_approved'] = 0;
				$commentdata['comment_type'] = 'respectify_revision';
				$commentdata['comment_meta'] = array(
					'respectify_evaluation' => $evaluation,
					'respectify_action' => $action,
					'respectify_feedback' => $feedback_html,
					'respectify_evaluated_at' => current_time('mysql')
				);
				return new \WP_Error(\Respectify\ACTION_REVISE, '<div class="respectify-feedback">' . $feedback_html . '</div>');
			} elseif ($action === \Respectify\ACTION_DELETE) {
				// Comment is rejected - we will NOT store it in the database
				// Instead, we return a WP_Error which will be caught by the AJAX handler
				// The error message (feedback_html) will be shown to the user
				$feedback_html = $this->build_feedback_html($evaluation);
				if (empty($feedback_html)) {
					// No feedback to show
					$feedback_html = 'Your comment was rejected for violating our community guidelines.';
				}
				// Note: setting comment_approved = 'spam' is not used since we return WP_Error
				// before wp_new_comment() is called
				$commentdata['comment_approved'] = 'spam';
				$commentdata['comment_type'] = 'respectify_rejected';
				$commentdata['comment_meta'] = array(
					'respectify_evaluation' => $evaluation,
					'respectify_action' => $action,
					'respectify_feedback' => $feedback_html,
					'respectify_evaluated_at' => current_time('mysql')
				);
				return new \WP_Error(\Respectify\ACTION_DELETE, '<div class="respectify-feedback">' . $feedback_html . '</div>');
			}
		}
	}

	/**
     * When a comment was sent for revision, we need to show feedback to the user. This
	 * builds that feedback (as HTML)  
     *
     * @param \RespectifyScoper\Respectify\MegaCallResult $megaResult The comment evaluation result.
     * @return string (HTML) feedback to show to the user.
     */
	private function build_feedback_html($megaResult) {
		// Get assessment settings
		$assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);

		// Check for spam first if spam checking is enabled
		if ($assessment_settings['check_spam'] && isset($megaResult->spam) && $megaResult->spam->isSpam) {
			return "This looks like spam.";
		}

		// Check for relevance issues if relevance checking is enabled
		if ($assessment_settings['check_relevance'] && isset($megaResult->relevance)) {
			// Check if comment is off-topic
			if ($megaResult->relevance->onTopic->onTopic === false) {
				return "<p>Your comment appears to be off-topic. " . esc_html($megaResult->relevance->onTopic->reasoning) . "</p>";
			}

			// Check for banned topics only if we have banned topics configured
			$banned_topics = get_option(\Respectify\OPTION_BANNED_TOPICS, '');
			if (!empty($banned_topics) && !empty($megaResult->relevance->bannedTopics->bannedTopics)) {
				$relevance_settings = get_option(\Respectify\OPTION_RELEVANCE_SETTINGS, \Respectify\RELEVANCE_DEFAULT_SETTINGS);
				$banned_topics_percentage = $megaResult->relevance->bannedTopics->quantityOnBannedTopics ?? 0;

				// Only show feedback if the percentage exceeds the threshold
				if ($banned_topics_percentage >= $relevance_settings['banned_topics_threshold']) {
					return "<p>Your comment contains topics that the site owner does not want discussed. " .
						   esc_html($megaResult->relevance->bannedTopics->reasoning) . "</p>";
				}
			}
		}

		// Check for dogwhistles if dogwhistle checking is enabled
		if (isset($assessment_settings['check_dogwhistle']) && $assessment_settings['check_dogwhistle'] && isset($megaResult->dogwhistle)) {
			if ($megaResult->dogwhistle->detection->dogwhistlesDetected) {
				$feedback = "<p>" . esc_html($megaResult->dogwhistle->detection->reasoning) . "</p>";
				if (isset($megaResult->dogwhistle->details) && !empty($megaResult->dogwhistle->details->dogwhistleTerms)) {
					$feedback .= "<p><strong>Detected terms:</strong> " . esc_html(implode(', ', $megaResult->dogwhistle->details->dogwhistleTerms)) . "</p>";
				}
				return $feedback;
			}
		}

		// Check health assessment if enabled
		if ($assessment_settings['assess_health'] && isset($megaResult->commentScore)) {
			$comment_score = $megaResult->commentScore;
			$revise_settings = get_option(\Respectify\OPTION_REVISE_SETTINGS, \Respectify\REVISE_DEFAULT_SETTINGS);

			// Add low effort feedback
			if ($revise_settings['low_effort'] && isset($comment_score->appearsLowEffort) && $comment_score->appearsLowEffort) {
				return "<p>Your comment appears to be low effort.</p>";
			}

			// Add logical fallacies feedback
			if ($revise_settings['logical_fallacies'] && !empty($comment_score->logicalFallacies)) {
				$feedback = "<p>Your comment contains a common mistep, a logical fallacy:</p><ul>";
				foreach ($comment_score->logicalFallacies as $fallacy) {
					$feedback .= "<li><em>" . esc_html($fallacy->quotedLogicalFallacyExample) . "</em>: ";
					$feedback .= esc_html($fallacy->explanation);
					if (!empty($fallacy->suggestedRewrite)) {
						$feedback .= "<div class='respectify-suggestion'>" . esc_html($fallacy->suggestedRewrite) . "</div>";
					}
					$feedback .= "</li>";
				}
				$feedback .= "</ul>";
				return $feedback;
			}

			// Add objectionable phrases feedback
			if ($revise_settings['objectionable_phrases'] && !empty($comment_score->objectionablePhrases)) {
				$feedback = "<p>Your comment contains objectionable phrases:</p><ul>";
				foreach ($comment_score->objectionablePhrases as $phrase) {
					$feedback .= "<li><em>" . esc_html($phrase->quotedObjectionablePhrase) . "</em>: ";
					$feedback .= esc_html($phrase->explanation) . "</li>";
				}
				$feedback .= "</ul>";
				return $feedback;
			}

			// Add negative tone feedback
			if ($revise_settings['negative_tone'] && !empty($comment_score->negativeTonePhrases)) {
				$feedback = "<p>Your comment is negative in a way that does not help the discussion:</p><ul>";
				foreach ($comment_score->negativeTonePhrases as $tone) {
					$feedback .= "<li><em>" . esc_html($tone->quotedNegativeTonePhrase) . "</em>: ";
					$feedback .= esc_html($tone->explanation);
					if (!empty($tone->suggestedRewrite)) {
						$feedback .= "<div class='respectify-suggestion'>" . esc_html($tone->suggestedRewrite) . "</div>";
					}
					$feedback .= "</li>";
				}
				$feedback .= "</ul>";
				return $feedback;
			}
		}

		// No issues found, return empty string
		return '';
	}

	/**
     * Determine the action to take on the comment based on score and settings.
     *
     * @param MegaCallResult $megaResult The comment evaluation result.
     * @return string Action to take -- see ACTION_ constants
     */
    private function get_comment_action($megaResult) {
        // Get assessment settings
        $assessment_settings = get_option(\Respectify\OPTION_ASSESSMENT_SETTINGS, \Respectify\ASSESSMENT_DEFAULT_SETTINGS);

        // Initialize array to store all issues found
        $issues = array();

        // Check for spam if spam checking is enabled
        if ($assessment_settings['check_spam'] && isset($megaResult->spam) && $megaResult->spam->isSpam) {
            $spam_handling = get_option(\Respectify\OPTION_SPAM_HANDLING, \Respectify\DEFAULT_SPAM_HANDLING);
            return $spam_handling; // Spam is always handled first as it's the most critical
        }

        // Only check relevance if it's enabled
        if ($assessment_settings['check_relevance'] && isset($megaResult->relevance)) {
            $relevance_settings = get_option(\Respectify\OPTION_RELEVANCE_SETTINGS, \Respectify\RELEVANCE_DEFAULT_SETTINGS);
            
            // Validate relevance settings
            if (!isset($relevance_settings['off_topic_handling']) || 
                !in_array($relevance_settings['off_topic_handling'], [\Respectify\ACTION_PUBLISH, \Respectify\ACTION_DELETE, \Respectify\ACTION_REVISE])) {
                $relevance_settings['off_topic_handling'] = \Respectify\RELEVANCE_DEFAULT_OFF_TOPIC_HANDLING;
            }
            
            if (!isset($relevance_settings['banned_topics_mode']) || 
                !in_array($relevance_settings['banned_topics_mode'], ['any', 'threshold'])) {
                $relevance_settings['banned_topics_mode'] = \Respectify\RELEVANCE_DEFAULT_BANNED_TOPICS_MODE;
            }
            
            if (!isset($relevance_settings['banned_topics_threshold']) || 
                !is_numeric($relevance_settings['banned_topics_threshold']) ||
                $relevance_settings['banned_topics_threshold'] < 0 || 
                $relevance_settings['banned_topics_threshold'] > 1) {
                $relevance_settings['banned_topics_threshold'] = \Respectify\RELEVANCE_DEFAULT_BANNED_TOPICS_THRESHOLD;
            }
            
            if (!isset($relevance_settings['banned_topics_handling']) || 
                !in_array($relevance_settings['banned_topics_handling'], [\Respectify\ACTION_DELETE, \Respectify\ACTION_REVISE, \Respectify\ACTION_PUBLISH])) {
                $relevance_settings['banned_topics_handling'] = \Respectify\RELEVANCE_DEFAULT_BANNED_TOPICS_HANDLING;
            }
            
            // Check if comment is off-topic
            if ($megaResult->relevance->onTopic->onTopic === false) {
                // If off-topic comments are allowed (ACTION_PUBLISH), don't add it as an issue
                if ($relevance_settings['off_topic_handling'] !== \Respectify\ACTION_PUBLISH) {
                    $issues[] = array(
                        'type' => 'off_topic',
                        'action' => $relevance_settings['off_topic_handling']
                    );
                }
            }
            
            // Check for banned topics only if we have banned topics configured
            $banned_topics = get_option(\Respectify\OPTION_BANNED_TOPICS, '');
            if (!empty($banned_topics) && !empty($megaResult->relevance->bannedTopics->bannedTopics)) {
                $banned_topics_percentage = $megaResult->relevance->bannedTopics->quantityOnBannedTopics ?? 0;
                
                $should_take_action = false;
                if ($relevance_settings['banned_topics_mode'] === 'any') {
                    $should_take_action = true;
                } else {
                    $should_take_action = ($banned_topics_percentage >= $relevance_settings['banned_topics_threshold']);
                }
                
                if ($should_take_action) {
                    $issues[] = array(
                        'type' => 'banned_topics',
                        'action' => $relevance_settings['banned_topics_handling']
                    );
                }
            }
        }

        // Check for dogwhistles if dogwhistle checking is enabled
        if (isset($assessment_settings['check_dogwhistle']) && $assessment_settings['check_dogwhistle'] && isset($megaResult->dogwhistle)) {
            $dogwhistle_settings = get_option(\Respectify\OPTION_DOGWHISTLE_SETTINGS, array('handling' => \Respectify\DOGWHISTLE_DEFAULT_HANDLING));
            
            // Validate dogwhistle handling setting
            if (!isset($dogwhistle_settings['handling']) || 
                !in_array($dogwhistle_settings['handling'], [\Respectify\ACTION_DELETE, \Respectify\ACTION_REVISE, \Respectify\ACTION_PUBLISH])) {
                $dogwhistle_settings['handling'] = \Respectify\DOGWHISTLE_DEFAULT_HANDLING;
            }
            
            // Check if dogwhistles were detected
            if ($megaResult->dogwhistle->detection->dogwhistlesDetected) {
                // Only add as an issue if the handling is not to publish (allow)
                if ($dogwhistle_settings['handling'] !== \Respectify\ACTION_PUBLISH) {
                    $issues[] = array(
                        'type' => 'dogwhistle',
                        'action' => $dogwhistle_settings['handling']
                    );
                }
            }
        }

        // Only check health assessment if it's enabled
        if ($assessment_settings['assess_health'] && isset($megaResult->commentScore)) {
            $revise_settings = get_option(\Respectify\OPTION_REVISE_SETTINGS, \Respectify\REVISE_DEFAULT_SETTINGS);
            
            // Validate revise settings
            if (!isset($revise_settings['min_score']) || 
                !is_numeric($revise_settings['min_score']) ||
                $revise_settings['min_score'] < 1 || 
                $revise_settings['min_score'] > 5) {
                $revise_settings['min_score'] = \Respectify\REVISE_DEFAULT_MIN_SCORE;
            }
            
            // Check if the comment score is below the minimum
            if ($megaResult->commentScore->overallScore < $revise_settings['min_score']) {
                $issues[] = array(
                    'type' => 'low_score',
                    'action' => \Respectify\ACTION_REVISE
                );
            }

            // Check for low effort
            if ($revise_settings['low_effort'] && isset($megaResult->commentScore->appearsLowEffort) && $megaResult->commentScore->appearsLowEffort) {
                $issues[] = array(
                    'type' => 'low_effort',
                    'action' => \Respectify\ACTION_REVISE
                );
            }

            // Check for logical fallacies
            if ($revise_settings['logical_fallacies'] && !empty($megaResult->commentScore->logicalFallacies)) {
                $issues[] = array(
                    'type' => 'logical_fallacies',
                    'action' => \Respectify\ACTION_REVISE
                );
            }

            // Check for objectionable phrases
            if ($revise_settings['objectionable_phrases'] && !empty($megaResult->commentScore->objectionablePhrases)) {
                $issues[] = array(
                    'type' => 'objectionable_phrases',
                    'action' => \Respectify\ACTION_REVISE
                );
            }

            // Check for negative tone
            if ($revise_settings['negative_tone'] && !empty($megaResult->commentScore->negativeTone)) {
                $issues[] = array(
                    'type' => 'negative_tone',
                    'action' => \Respectify\ACTION_REVISE
                );
            }
        }

        // If no issues were found, publish the comment
        if (empty($issues)) {
            return \Respectify\ACTION_PUBLISH;
        }

        // Determine the most severe action needed
        $actions = array_column($issues, 'action');
        if (in_array(\Respectify\ACTION_DELETE, $actions)) {
            return \Respectify\ACTION_DELETE;
        }
        if (in_array(\Respectify\ACTION_REVISE, $actions)) {
            return \Respectify\ACTION_REVISE;
        }

        // Default to publish if somehow we get here
        return \Respectify\ACTION_PUBLISH;
    }

    /**
     * AJAX handler for submitting comments.
     */
	public function ajax_submit_comment() {
		\Respectify\respectify_log('ajax_submit_comment called');
    
		// Verify nonce
		$this->verify_comment_nonce();

		// Manually prepare comment data from $_POST
		$commentdata = array(
			'comment_post_ID'      => isset($_POST['comment_post_ID']) ? absint($_POST['comment_post_ID']) : 0,
			'comment_author'       => isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '',
			'comment_author_email' => isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '',
			'comment_author_url'   => isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '',
			'comment_content'      => isset($_POST['comment_content']) ? sanitize_textarea_field(wp_unslash($_POST['comment_content'])) : '',
			'comment_type'         => '', // Empty for regular comments
			'comment_parent'       => isset($_POST['comment_parent']) ? absint($_POST['comment_parent']) : 0,
			'user_id'              => get_current_user_id(),
			'comment_author_IP'    => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
			'comment_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
			'comment_date'         => current_time('mysql'),
			'comment_approved'     => 1, // Adjust approval status as needed
		);

		// Log the comment data for debugging
		\Respectify\respectify_log('Comment data prepared: ' . wp_json_encode($commentdata));

		// Intercept and process the comment
		$result = $this->intercept_comment($commentdata);
	
		if (is_wp_error($result)) {
			wp_send_json_error([
				'message' => $result->get_error_message(),
				'data'    => $result->get_error_data(),
			]);
		} else {
			// Log the result before inserting
			\Respectify\respectify_log('Comment result before insertion: ' . wp_json_encode($result));
			
			// Insert the comment into the database directly
			$comment_id = wp_insert_comment($result);
			
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
		\Respectify\respectify_log('Intercepting comment with JS turned off: ' . $commentdata['comment_content']);

		// Verify nonce
		$this->verify_comment_nonce();

		$result = $this->intercept_comment($commentdata);

		\Respectify\respectify_log('Result of the comment interception: ' . wp_json_encode($result));

		if (is_wp_error($result)) {
			\Respectify\respectify_log('Comment rejected: ' . $result->get_error_message());
			
			// Create a properly formatted HTML error page
			$html = '<!DOCTYPE html>
			<html>
			<head>
				<title>' . esc_html__('Please revise your comment', 'respectify') . '</title>
				<link rel="stylesheet" href="' . esc_url(plugins_url('public/css/respectify-comments.css', dirname(__FILE__))) . '" type="text/css" media="all" />
			</head>
			<body>
				<div class="respectify-message respectify-error">
					' . $result->get_error_message() . '
				</div>
				<div class="respectify-comment-text">
					<p>' . esc_html__('Your comment:', 'respectify') . '</p>
					<blockquote>' . esc_html($commentdata['comment_content']) . '</blockquote>
					<p>' . esc_html__('Please use your browser\'s back button to return to the previous page and revise your comment.', 'respectify') . '</p>
				</div>
			</body>
			</html>';

			// Handle the error with proper HTML formatting
			wp_die($html, esc_html__('Please revise your comment', 'respectify'), array('back_link' => false));
		}
	
		\Respectify\respectify_log('Comment allowed: ' . $result['comment_content']);
		// Return processed comment data
		return $result;
	}

	/**
     * Enqueue the JavaScript file to handle comment feedback
	 * Plus the CSS as well.
     */
    public function enqueue_scripts_and_styles() {
		wp_enqueue_script('respectify-comments-js', plugins_url('public/js/respectify-comments.js', __DIR__), array('jquery'), $this->version, true);
	
		wp_localize_script('respectify-comments-js', 'respectify_ajax_object', array(
			'ajax_url' => admin_url('admin-ajax.php'),
		));
	
		wp_enqueue_style('respectify-comments', plugins_url('public/css/respectify-comments.css', __DIR__), array(), $this->version);
	}

}
