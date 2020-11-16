<?php
/**
 * Ajax test case class
 *
 * @package    WordPress
 * @subpackage UnitTests
 * @since      3.4.0
 */
abstract class WP_Ajax_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Last Ajax response. This is set via echo -or- wp_die.
	 *
	 * @var string
	 */
	protected $_last_response = '';

	/**
	 * List of Ajax actions called via GET.
	 *
	 * @var array
	 */
	protected static $_core_actions_get = array(
		'fetch-list',
		'ajax-tag-search',
		'wp-compression-test',
		'imgedit-preview',
		'oembed-cache',
		'autocomplete-user',
		'dashboard-widgets',
		'logged-in',
	);

	/**
	 * Saved error reporting level.
	 *
	 * @var int
	 */
	protected $_error_level = 0;

	/**
	 * List of Ajax actions called via POST.
	 *
	 * @var array
	 */
	protected static $_core_actions_post = array(
		'oembed_cache',
		'image-editor',
		'delete-comment',
		'delete-tag',
		'delete-link',
		'delete-meta',
		'delete-post',
		'trash-post',
		'untrash-post',
		'delete-page',
		'dim-comment',
		'add-link-category',
		'add-tag',
		'get-tagcloud',
		'get-comments',
		'replyto-comment',
		'edit-comment',
		'add-menu-item',
		'add-meta',
		'add-user',
		'closed-postboxes',
		'hidden-columns',
		'update-welcome-panel',
		'menu-get-metabox',
		'wp-link-ajax',
		'menu-locations-save',
		'menu-quick-search',
		'meta-box-order',
		'get-permalink',
		'sample-permalink',
		'inline-save',
		'inline-save-tax',
		'find_posts',
		'widgets-order',
		'save-widget',
		'set-post-thumbnail',
		'date_format',
		'time_format',
		'wp-fullscreen-save-post',
		'wp-remove-post-lock',
		'dismiss-wp-pointer',
		'send-attachment-to-editor',
		'heartbeat',
		'nopriv_heartbeat',
		'get-revision-diffs',
		'save-user-color-scheme',
		'update-widget',
		'query-themes',
		'parse-embed',
		'set-attachment-thumbnail',
		'parse-media-shortcode',
		'destroy-sessions',
		'install-plugin',
		'update-plugin',
		'press-this-save-post',
		'press-this-add-category',
		'crop-image',
		'generate-password',
		'save-wporg-username',
		'delete-plugin',
		'search-plugins',
		'search-install-plugins',
		'activate-plugin',
		'update-theme',
		'delete-theme',
		'install-theme',
		'get-post-thumbnail-html',
		'wp-privacy-export-personal-data',
		'wp-privacy-erase-personal-data',
	);

	public static function setUpBeforeClass() {
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );

		// Register the core actions.
		foreach ( array_merge( self::$_core_actions_get, self::$_core_actions_post ) as $action ) {
			if ( function_exists( 'wp_ajax_' . str_replace( '-', '_', $action ) ) ) {
				add_action( 'wp_ajax_' . $action, 'wp_ajax_' . str_replace( '-', '_', $action ), 1 );
			}
		}

		parent::setUpBeforeClass();
	}

	/**
	 * Sets up the test fixture.
	 *
	 * Overrides wp_die(), pretends to be Ajax, and suppresses E_WARNINGs.
	 */
	public function setUp() {
		parent::setUp();

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );

		set_current_screen( 'ajax' );

		// Clear logout cookies.
		add_action( 'clear_auth_cookie', array( $this, 'logout' ) );

		// Suppress warnings from "Cannot modify header information - headers already sent by".
		$this->_error_level = error_reporting();
		error_reporting( $this->_error_level & ~E_WARNING );

		// Make some posts.
		self::factory()->post->create_many( 5 );
	}

	/**
	 * Tears down the test fixture.
	 *
	 * Resets $_POST, removes the wp_die() override, restores error reporting.
	 */
	public function tearDown() {
		parent::tearDown();
		$_POST = array();
		$_GET  = array();
		unset( $GLOBALS['post'] );
		unset( $GLOBALS['comment'] );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'getDieHandler' ), 1, 1 );
		remove_action( 'clear_auth_cookie', array( $this, 'logout' ) );
		error_reporting( $this->_error_level );
		set_current_screen( 'front' );
	}

	/**
	 * Clears login cookies, unsets the current user.
	 */
	public function logout() {
		unset( $GLOBALS['current_user'] );
		$cookies = array( AUTH_COOKIE, SECURE_AUTH_COOKIE, LOGGED_IN_COOKIE, USER_COOKIE, PASS_COOKIE );
		foreach ( $cookies as $c ) {
			unset( $_COOKIE[ $c ] );
		}
	}

	/**
	 * Returns our callback handler
	 *
	 * @return callback
	 */
	public function getDieHandler() {
		return array( $this, 'dieHandler' );
	}

	/**
	 * Handler for wp_die().
	 *
	 * Save the output for analysis, stop execution by throwing an exception.
	 *
	 * Error conditions (no output, just die) will throw <code>WPAjaxDieStopException( $message )</code>
	 * You can test for this with:
	 * <code>
	 * $this->setExpectedException( 'WPAjaxDieStopException', 'something contained in $message' );
	 * </code>
	 * Normal program termination (wp_die called at then end of output) will throw <code>WPAjaxDieContinueException( $message )</code>
	 * You can test for this with:
	 * <code>
	 * $this->setExpectedException( 'WPAjaxDieContinueException', 'something contained in $message' );
	 * </code>
	 *
	 * @param string $message The message to set.
	 *
	 * @throws WPAjaxDieStopException     Thrown to stop further execution.
	 * @throws WPAjaxDieContinueException Thrown to stop execution of the Ajax function,
	 *                                    but continue the unit test.
	 */
	public function dieHandler( $message ) {
		$this->_last_response .= ob_get_clean();

		if ( '' === $this->_last_response ) {
			if ( is_scalar( $message ) ) {
				throw new WPAjaxDieStopException( (string) $message );
			} else {
				throw new WPAjaxDieStopException( '0' );
			}
		} else {
			throw new WPAjaxDieContinueException( $message );
		}
	}

	/**
	 * Switches between user roles.
	 *
	 * E.g. administrator, editor, author, contributor, subscriber.
	 *
	 * @param string $role The role to set.
	 */
	protected function _setRole( $role ) {
		$post    = $_POST;
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );
		$_POST = array_merge( $_POST, $post );
	}

	/**
	 * Mimics the Ajax handling of admin-ajax.php.
	 *
	 * Captures the output via output buffering, and if there is any,
	 * stores it in $this->_last_response.
	 *
	 * @param string $action The action to handle.
	 */
	protected function _handleAjax( $action ) {

		// Start output buffering.
		ini_set( 'implicit_flush', false );
		ob_start();

		// Build the request.
		$_POST['action'] = $action;
		$_GET['action']  = $action;
		$_REQUEST        = array_merge( $_POST, $_GET );

		// Call the hooks.
		do_action( 'admin_init' );
		do_action( 'wp_ajax_' . $_REQUEST['action'], null );

		// Save the output.
		$buffer = ob_get_clean();
		if ( ! empty( $buffer ) ) {
			$this->_last_response = $buffer;
		}
	}
}
