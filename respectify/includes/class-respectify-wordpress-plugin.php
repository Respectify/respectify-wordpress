<?php
namespace Respectify;
use RespectifyScoper\Respectify\RespectifyClientAsync;
use RespectifyScoper\Respectify\CommentScore;
use RespectifyScoper\Respectify\MegaCallResult;


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
        
        if ($assessment_settings['check_spam']) {
            $services[] = 'spam';
        }
        if ($assessment_settings['assess_health']) {
            $services[] = 'commentscore';
        }
        if ($assessment_settings['check_relevance']) {
            $services[] = 'relevance';
        }

        // If no services are enabled, default to all
        if (empty($services)) {
            $services = ['spam', 'commentscore', 'relevance'];
        }

        // Get banned topics if relevance checking is enabled
        $banned_topics = null;
        if ($assessment_settings['check_relevance']) {
            $banned_topics = get_option(\Respectify\OPTION_BANNED_TOPICS, '');
            if (empty($banned_topics)) {
                $banned_topics = null;
            }
        }

        $promise = $this->respectify_client->megacall(
            comment: $full_comment_text,
            articleContextId:$respectify_article_id,
            services:$services,
            bannedTopics: $banned_topics,
            replyToComment: $reply_to_comment_text
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
			// Don't fail, just log - WordPress will handle missing fields
		}

		$post_id = $commentdata['comment_post_ID'];
		$article_id = $this->get_respectify_article_id($post_id);

		if (!$article_id) {
			\Respectify\respectify_log('Invalid article ID: ' . $article_id);
			// Return an error
			return new \WP_Error('invalid_article_id', 'Invalid article ID.');
		}

		// Wordpress adds slashes, so remove them before sanitising to avoid double slashes
		// Caught by words like "don't": visible to the user as "don\'t"
		$comment_text = sanitize_text_field(wp_unslash($commentdata['comment_content']));

		// Get the comment being replied to if this is a reply
		$reply_to_comment_text = null;
		if (!empty($commentdata['comment_parent'])) {
			$parent_comment = get_comment($commentdata['comment_parent']);
			if ($parent_comment) {
				// Verify parent comment belongs to the same post
				if ($parent_comment->comment_post_ID != $commentdata['comment_post_ID']) {
					\Respectify\respectify_log('Parent comment belongs to different post - ignoring parent context');
				} else {
					$reply_to_comment_text = sanitize_text_field(wp_unslash($parent_comment->comment_content));
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
		$evaluation = $this->evaluate_comment($article_id, $comment_text, $reply_to_comment_text, $author_name, $author_email);
		
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
			if (!$megaResult->relevance->onTopic->isOnTopic) {
				$feedback = "Your comment appears to be off-topic. ";
				$feedback .= $megaResult->relevance->onTopic->reasoning;
				return $feedback;
			}
			
			// Check for banned topics only if we have banned topics configured
			$banned_topics = get_option(\Respectify\OPTION_BANNED_TOPICS, '');
			if (!empty($banned_topics) && !empty($megaResult->relevance->bannedTopics->bannedTopics)) {
				$relevance_settings = get_option(\Respectify\OPTION_RELEVANCE_SETTINGS, \Respectify\RELEVANCE_DEFAULT_SETTINGS);
				$banned_topics_percentage = $megaResult->relevance->bannedTopics->percentage ?? 0;
				
				// Only show feedback if the percentage exceeds the threshold
				if ($banned_topics_percentage >= $relevance_settings['banned_topics_threshold']) {
					$feedback = "Your comment contains topics that the site owner does not want discussed. ";
					$feedback .= $megaResult->relevance->bannedTopics->reasoning;
					
					// Add the list of banned topics if available
					if (!empty($megaResult->relevance->bannedTopics->bannedTopics)) {
						$feedback .= "\n\nDetected banned topics:\n";
						foreach ($megaResult->relevance->bannedTopics->bannedTopics as $topic) {
							$feedback .= "- " . esc_html($topic) . "\n";
						}
					}
					
					return $feedback;
				}
			}
		}

		// If no comment score is available or health assessment is disabled, provide generic feedback
		if (!$assessment_settings['assess_health'] || !isset($megaResult->commentScore)) {
			return "Please revise your comment.";
		}

		$feedback = "Please revise your comment.\n\n";
		$comment_score = $megaResult->commentScore;

		// Add score feedback
		$feedback .= "Score: " . $comment_score->score . "/5\n";

		// Add low effort feedback
		if ($comment_score->isLowEffort) {
			$feedback .= "Your comment appears to be low effort. Please provide more substance.\n";
		}

		// Add logical fallacies feedback
		if (!empty($comment_score->logicalFallacies)) {
			$feedback .= "Your comment contains logical fallacies:\n";
			foreach ($comment_score->logicalFallacies as $fallacy) {
				$feedback .= "- " . esc_html($fallacy) . "\n";
			}
		}

		// Add objectionable phrases feedback
		if (!empty($comment_score->objectionablePhrases)) {
			$feedback .= "Your comment contains objectionable phrases:\n";
			foreach ($comment_score->objectionablePhrases as $phrase) {
				$feedback .= "- " . esc_html($phrase) . "\n";
			}
		}

		// Add negative tone feedback
		if (!empty($comment_score->negativeTone)) {
			$feedback .= "Your comment has a negative tone:\n";
			foreach ($comment_score->negativeTone as $tone) {
				$feedback .= "- " . esc_html($tone) . "\n";
			}
		}

		return $feedback;
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

        // Check for spam first if spam checking is enabled
        if ($assessment_settings['check_spam'] && isset($megaResult->spam) && $megaResult->spam->isSpam) {
            $spam_handling = get_option(\Respectify\OPTION_SPAM_HANDLING, \Respectify\DEFAULT_SPAM_HANDLING);
            return $spam_handling;
        }

        // Check for relevance issues if relevance checking is enabled
        if ($assessment_settings['check_relevance'] && isset($megaResult->relevance)) {
            $relevance_settings = get_option(\Respectify\OPTION_RELEVANCE_SETTINGS, \Respectify\RELEVANCE_DEFAULT_SETTINGS);
            
            // Check if comment is off-topic
            if (!$megaResult->relevance->onTopic->isOnTopic) {
                return $relevance_settings['off_topic_handling'];
            }
            
            // Check for banned topics only if we have banned topics configured
            $banned_topics = get_option(\Respectify\OPTION_BANNED_TOPICS, '');
            if (!empty($banned_topics) && !empty($megaResult->relevance->bannedTopics->bannedTopics)) {
                // Get the banned topics percentage from the result
                $banned_topics_percentage = $megaResult->relevance->bannedTopics->percentage ?? 0;
                
                // Check if we should take action based on the mode and threshold
                $should_take_action = false;
                
                if ($relevance_settings['banned_topics_mode'] === 'any') {
                    // Any mention triggers the action
                    $should_take_action = true;
                } else {
                    // Only take action if the percentage exceeds the threshold
                    $should_take_action = ($banned_topics_percentage >= $relevance_settings['banned_topics_threshold']);
                }
                
                if ($should_take_action) {
                    return $relevance_settings['banned_topics_handling'];
                }
            }
        }

        // If no comment score is available or health assessment is disabled, default to publishing
        if (!$assessment_settings['assess_health'] || !isset($megaResult->commentScore)) {
            return \Respectify\ACTION_PUBLISH;
        }

        // Get the revise settings
        $revise_settings = get_option(\Respectify\OPTION_REVISE_SETTINGS, \Respectify\REVISE_DEFAULT_SETTINGS);

        // Check if the comment score is below the minimum
        if ($megaResult->commentScore->score < $revise_settings['min_score']) {
            return \Respectify\ACTION_REVISE;
        }

        // Check for low effort
        if ($revise_settings['low_effort'] && $megaResult->commentScore->isLowEffort) {
            return \Respectify\ACTION_REVISE;
        }

        // Check for logical fallacies
        if ($revise_settings['logical_fallacies'] && !empty($megaResult->commentScore->logicalFallacies)) {
            return \Respectify\ACTION_REVISE;
        }

        // Check for objectionable phrases
        if ($revise_settings['objectionable_phrases'] && !empty($megaResult->commentScore->objectionablePhrases)) {
            return \Respectify\ACTION_REVISE;
        }

        // Check for negative tone
        if ($revise_settings['negative_tone'] && !empty($megaResult->commentScore->negativeTone)) {
            return \Respectify\ACTION_REVISE;
        }

        // If all checks pass, publish the comment
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
			'comment_content'      => isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '',
			'comment_type'         => '', // Empty for regular comments
			'comment_parent'       => isset($_POST['comment_parent']) ? absint($_POST['comment_parent']) : 0,
			'user_id'              => get_current_user_id(),
			'comment_author_IP'    => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
			'comment_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
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
		\Respectify\respectify_log('Intercepting comment with JS turned off: ' . $commentdata['comment_content']);

		// Verify nonce
		$this->verify_comment_nonce();

		$result = $this->intercept_comment($commentdata);

		\Respectify\respectify_log('Result of the comment interception: ' . wp_json_encode($result));

		if (is_wp_error($result)) {
			\Respectify\respectify_log('Comment rejected: ' . $result->get_error_message());
			// Handle the error by preventing the comment from being saved
			wp_die(
				esc_html($result->get_error_message()),
				esc_html__('Comment Submission Error', 'respectify'),
				array('back_link' => true)
			);
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
