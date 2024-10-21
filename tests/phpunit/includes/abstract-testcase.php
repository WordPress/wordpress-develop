<?php

require_once __DIR__ . '/factory.php';
require_once __DIR__ . '/trac.php';

/**
 * Defines a basic fixture to run multiple tests.
 *
 * Resets the state of the WordPress installation before and after every test.
 *
 * Includes utility functions and assertions useful for testing WordPress.
 *
 * All WordPress unit tests should inherit from this class.
 */
abstract class WP_UnitTestCase_Base extends PHPUnit_Adapter_TestCase {

	protected static $forced_tickets   = array();
	protected $expected_deprecated     = array();
	protected $caught_deprecated       = array();
	protected $expected_doing_it_wrong = array();
	protected $caught_doing_it_wrong   = array();

	protected static $hooks_saved = array();
	protected static $ignore_files;

	public function __isset( $name ) {
		return 'factory' === $name;
	}

	public function __get( $name ) {
		if ( 'factory' === $name ) {
			return self::factory();
		}
	}

	/**
	 * Fetches the factory object for generating WordPress fixtures.
	 *
	 * @return WP_UnitTest_Factory The fixture factory.
	 */
	protected static function factory() {
		static $factory = null;
		if ( ! $factory ) {
			$factory = new WP_UnitTest_Factory();
		}
		return $factory;
	}

	/**
	 * Retrieves the name of the class the static method is called in.
	 *
	 * @deprecated 5.3.0 Use the PHP native get_called_class() function instead.
	 *
	 * @return string The class name.
	 */
	public static function get_called_class() {
		return get_called_class();
	}

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class() {
		global $wpdb;

		$wpdb->suppress_errors = false;
		$wpdb->show_errors     = true;
		$wpdb->db_connect();
		ini_set( 'display_errors', 1 );

		parent::set_up_before_class();

		$class = get_called_class();

		if ( method_exists( $class, 'wpSetUpBeforeClass' ) ) {
			call_user_func( array( $class, 'wpSetUpBeforeClass' ), self::factory() );
		}

		self::commit_transaction();
	}

	/**
	 * Runs the routine after all tests have been run.
	 */
	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		_delete_all_data();
		self::flush_cache();

		$class = get_called_class();

		if ( method_exists( $class, 'wpTearDownAfterClass' ) ) {
			call_user_func( array( $class, 'wpTearDownAfterClass' ) );
		}

		self::commit_transaction();
	}

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up() {
		set_time_limit( 0 );

		if ( ! self::$ignore_files ) {
			self::$ignore_files = $this->scan_user_uploads();
		}

		if ( ! self::$hooks_saved ) {
			$this->_backup_hooks();
		}

		global $wp_rewrite;

		$this->clean_up_global_scope();

		/*
		 * When running core tests, ensure that post types and taxonomies
		 * are reset for each test. We skip this step for non-core tests,
		 * given the large number of plugins that register post types and
		 * taxonomies at 'init'.
		 */
		if ( defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS ) {
			$this->reset_post_types();
			$this->reset_taxonomies();
			$this->reset_post_statuses();
			$this->reset__SERVER();

			if ( $wp_rewrite->permalink_structure ) {
				$this->set_permalink_structure( '' );
			}
		}

		$this->start_transaction();
		$this->expectDeprecated();
		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );
	}

	/**
	 * After a test method runs, resets any state in WordPress the test method might have changed.
	 */
	public function tear_down() {
		global $wpdb, $wp_query, $wp;
		$wpdb->query( 'ROLLBACK' );
		if ( is_multisite() ) {
			while ( ms_is_switched() ) {
				restore_current_blog();
			}
		}
		$wp_query = new WP_Query();
		$wp       = new WP();

		// Reset globals related to the post loop and `setup_postdata()`.
		$post_globals = array( 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );
		foreach ( $post_globals as $global ) {
			$GLOBALS[ $global ] = null;
		}

		/*
		 * Reset globals related to current screen to provide a consistent global starting state
		 * for tests that interact with admin screens. Replaces the need for individual tests
		 * to invoke `set_current_screen( 'front' )` (or an alternative implementation) as a reset.
		 *
		 * The globals are from `WP_Screen::set_current_screen()`.
		 *
		 * Why not invoke `set_current_screen( 'front' )`?
		 * Performance (faster test runs with less memory usage). How so? For each test,
		 * it saves creating an instance of WP_Screen, making two method calls,
		 * and firing of the `current_screen` action.
		 */
		$current_screen_globals = array( 'current_screen', 'taxnow', 'typenow' );
		foreach ( $current_screen_globals as $global ) {
			$GLOBALS[ $global ] = null;
		}

		/*
		 * Reset $wp_sitemap global so that sitemap-related dynamic $wp->public_query_vars
		 * are added when the next test runs.
		 */
		$GLOBALS['wp_sitemaps'] = null;

		$this->unregister_all_meta_keys();
		remove_theme_support( 'html5' );
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		remove_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );
		$this->_restore_hooks();
		wp_set_current_user( 0 );
	}

	/**
	 * Cleans the global scope (e.g `$_GET` and `$_POST`).
	 */
	public function clean_up_global_scope() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		self::flush_cache();
	}

	/**
	 * Allows tests to be skipped on some automated runs.
	 *
	 * For test runs on GitHub Actions for something other than trunk/master,
	 * we want to skip tests that only need to run for master.
	 */
	public function skipOnAutomatedBranches() {
		// https://docs.github.com/en/free-pro-team@latest/actions/reference/environment-variables#default-environment-variables
		$github_event_name = getenv( 'GITHUB_EVENT_NAME' );
		$github_ref        = getenv( 'GITHUB_REF' );

		if ( $github_event_name && 'false' !== $github_event_name ) {
			// We're on GitHub Actions.
			$skipped = array( 'pull_request', 'pull_request_target' );

			if ( in_array( $github_event_name, $skipped, true ) || 'refs/heads/master' !== $github_ref ) {
				$this->markTestSkipped( 'For automated test runs, this test is only run on trunk/master' );
			}
		}
	}

	/**
	 * Allows tests to be skipped when Multisite is not in use.
	 *
	 * Use in conjunction with the ms-required group.
	 */
	public function skipWithoutMultisite() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Test only runs on Multisite' );
		}
	}

	/**
	 * Allows tests to be skipped when Multisite is in use.
	 *
	 * Use in conjunction with the ms-excluded group.
	 */
	public function skipWithMultisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Test does not run on Multisite' );
		}
	}

	/**
	 * Allows tests to be skipped if the HTTP request times out.
	 *
	 * @param array|WP_Error $response HTTP response.
	 */
	public function skipTestOnTimeout( $response ) {
		if ( ! is_wp_error( $response ) ) {
			return;
		}
		if ( 'connect() timed out!' === $response->get_error_message() ) {
			$this->markTestSkipped( 'HTTP timeout' );
		}

		if ( false !== strpos( $response->get_error_message(), 'timed out after' ) ) {
			$this->markTestSkipped( 'HTTP timeout' );
		}

		if ( 0 === strpos( $response->get_error_message(), 'stream_socket_client(): unable to connect to tcp://s.w.org:80' ) ) {
			$this->markTestSkipped( 'HTTP timeout' );
		}

	}

	/**
	 * Unregisters existing post types and register defaults.
	 *
	 * Run before each test in order to clean up the global scope, in case
	 * a test forgets to unregister a post type on its own, or fails before
	 * it has a chance to do so.
	 */
	protected function reset_post_types() {
		foreach ( get_post_types( array(), 'objects' ) as $pt ) {
			if ( empty( $pt->tests_no_auto_unregister ) ) {
				_unregister_post_type( $pt->name );
			}
		}
		create_initial_post_types();
	}

	/**
	 * Unregisters existing taxonomies and register defaults.
	 *
	 * Run before each test in order to clean up the global scope, in case
	 * a test forgets to unregister a taxonomy on its own, or fails before
	 * it has a chance to do so.
	 */
	protected function reset_taxonomies() {
		foreach ( get_taxonomies() as $tax ) {
			_unregister_taxonomy( $tax );
		}
		create_initial_taxonomies();
	}

	/**
	 * Unregisters non-built-in post statuses.
	 */
	protected function reset_post_statuses() {
		foreach ( get_post_stati( array( '_builtin' => false ) ) as $post_status ) {
			_unregister_post_status( $post_status );
		}
	}

	/**
	 * Resets `$_SERVER` variables
	 */
	protected function reset__SERVER() {
		tests_reset__SERVER();
	}

	/**
	 * Saves the action and filter-related globals so they can be restored later.
	 *
	 * Stores $wp_actions, $wp_current_filter, and $wp_filter on a class variable
	 * so they can be restored on tearDown() using _restore_hooks().
	 *
	 * @global array $wp_actions
	 * @global array $wp_current_filter
	 * @global array $wp_filter
	 */
	protected function _backup_hooks() {
		$globals = array( 'wp_actions', 'wp_current_filter' );
		foreach ( $globals as $key ) {
			self::$hooks_saved[ $key ] = $GLOBALS[ $key ];
		}
		self::$hooks_saved['wp_filter'] = array();
		foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook_object ) {
			self::$hooks_saved['wp_filter'][ $hook_name ] = clone $hook_object;
		}
	}

	/**
	 * Restores the hook-related globals to their state at setUp()
	 * so that future tests aren't affected by hooks set during this last test.
	 *
	 * @global array $wp_actions
	 * @global array $wp_current_filter
	 * @global array $wp_filter
	 */
	protected function _restore_hooks() {
		$globals = array( 'wp_actions', 'wp_current_filter' );
		foreach ( $globals as $key ) {
			if ( isset( self::$hooks_saved[ $key ] ) ) {
				$GLOBALS[ $key ] = self::$hooks_saved[ $key ];
			}
		}
		if ( isset( self::$hooks_saved['wp_filter'] ) ) {
			$GLOBALS['wp_filter'] = array();
			foreach ( self::$hooks_saved['wp_filter'] as $hook_name => $hook_object ) {
				$GLOBALS['wp_filter'][ $hook_name ] = clone $hook_object;
			}
		}
	}

	/**
	 * Flushes the WordPress object cache.
	 */
	public static function flush_cache() {
		global $wp_object_cache;
		$wp_object_cache->group_ops      = array();
		$wp_object_cache->stats          = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache          = array();
		if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset();
		}
		wp_cache_flush();
		wp_cache_add_global_groups( array( 'users', 'userlogins', 'usermeta', 'user_meta', 'useremail', 'userslugs', 'site-transient', 'site-options', 'blog-lookup', 'blog-details', 'rss', 'global-posts', 'blog-id-cache', 'networks', 'sites', 'site-details', 'blog_meta' ) );
		wp_cache_add_non_persistent_groups( array( 'comment', 'counts', 'plugins' ) );
	}

	/**
	 * Cleans up any registered meta keys.
	 *
	 * @since 5.1.0
	 *
	 * @global array $wp_meta_keys
	 */
	public function unregister_all_meta_keys() {
		global $wp_meta_keys;
		if ( ! is_array( $wp_meta_keys ) ) {
			return;
		}
		foreach ( $wp_meta_keys as $object_type => $type_keys ) {
			foreach ( $type_keys as $object_subtype => $subtype_keys ) {
				foreach ( $subtype_keys as $key => $value ) {
					unregister_meta_key( $object_type, $key, $object_subtype );
				}
			}
		}
	}

	/**
	 * Starts a database transaction.
	 */
	public function start_transaction() {
		global $wpdb;
		$wpdb->query( 'SET autocommit = 0;' );
		$wpdb->query( 'START TRANSACTION;' );
		add_filter( 'query', array( $this, '_create_temporary_tables' ) );
		add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	/**
	 * Commits the queries in a transaction.
	 *
	 * @since 4.1.0
	 */
	public static function commit_transaction() {
		global $wpdb;
		$wpdb->query( 'COMMIT;' );
	}

	/**
	 * Replaces the `CREATE TABLE` statement with a `CREATE TEMPORARY TABLE` statement.
	 *
	 * @param string $query The query to replace the statement for.
	 * @return string The altered query.
	 */
	public function _create_temporary_tables( $query ) {
		if ( 0 === strpos( trim( $query ), 'CREATE TABLE' ) ) {
			return substr_replace( trim( $query ), 'CREATE TEMPORARY TABLE', 0, 12 );
		}
		return $query;
	}

	/**
	 * Replaces the `DROP TABLE` statement with a `DROP TEMPORARY TABLE` statement.
	 *
	 * @param string $query The query to replace the statement for.
	 * @return string The altered query.
	 */
	public function _drop_temporary_tables( $query ) {
		if ( 0 === strpos( trim( $query ), 'DROP TABLE' ) ) {
			return substr_replace( trim( $query ), 'DROP TEMPORARY TABLE', 0, 10 );
		}
		return $query;
	}

	/**
	 * Retrieves the `wp_die()` handler.
	 *
	 * @param callable $handler The current die handler.
	 * @return callable The test die handler.
	 */
	public function get_wp_die_handler( $handler ) {
		return array( $this, 'wp_die_handler' );
	}

	/**
	 * Throws an exception when called.
	 *
	 * @since UT (3.7.0)
	 * @since 5.9.0 Added the `$title` and `$args` parameters.
	 *
	 * @throws WPDieException Exception containing the message and the response code.
	 *
	 * @param string|WP_Error $message The `wp_die()` message or WP_Error object.
	 * @param string          $title   The `wp_die()` title.
	 * @param string|array    $args    The `wp_die()` arguments.
	 */
	public function wp_die_handler( $message, $title, $args ) {
		if ( is_wp_error( $message ) ) {
			$message = $message->get_error_message();
		}

		if ( ! is_scalar( $message ) ) {
			$message = '0';
		}

		$code = 0;
		if ( isset( $args['response'] ) ) {
			$code = $args['response'];
		}

		throw new WPDieException( $message, $code );
	}

	/**
	 * Sets up the expectations for testing a deprecated call.
	 */
	public function expectDeprecated() {
		if ( method_exists( $this, 'getAnnotations' ) ) {
			// PHPUnit < 9.5.0.
			$annotations = $this->getAnnotations();
		} else {
			// PHPUnit >= 9.5.0.
			$annotations = \PHPUnit\Util\Test::parseTestMethodAnnotations(
				static::class,
				$this->getName( false )
			);
		}

		foreach ( array( 'class', 'method' ) as $depth ) {
			if ( ! empty( $annotations[ $depth ]['expectedDeprecated'] ) ) {
				$this->expected_deprecated = array_merge( $this->expected_deprecated, $annotations[ $depth ]['expectedDeprecated'] );
			}
			if ( ! empty( $annotations[ $depth ]['expectedIncorrectUsage'] ) ) {
				$this->expected_doing_it_wrong = array_merge( $this->expected_doing_it_wrong, $annotations[ $depth ]['expectedIncorrectUsage'] );
			}
		}
		add_action( 'deprecated_function_run', array( $this, 'deprecated_function_run' ) );
		add_action( 'deprecated_argument_run', array( $this, 'deprecated_function_run' ) );
		add_action( 'deprecated_file_included', array( $this, 'deprecated_function_run' ) );
		add_action( 'deprecated_hook_run', array( $this, 'deprecated_function_run' ) );
		add_action( 'doing_it_wrong_run', array( $this, 'doing_it_wrong_run' ) );
		add_action( 'deprecated_function_trigger_error', '__return_false' );
		add_action( 'deprecated_argument_trigger_error', '__return_false' );
		add_action( 'deprecated_file_trigger_error', '__return_false' );
		add_action( 'deprecated_hook_trigger_error', '__return_false' );
		add_action( 'doing_it_wrong_trigger_error', '__return_false' );
	}

	/**
	 * Handles a deprecated expectation.
	 *
	 * The DocBlock should contain `@expectedDeprecated` to trigger this.
	 */
	public function expectedDeprecated() {
		$errors = array();

		$not_caught_deprecated = array_diff( $this->expected_deprecated, $this->caught_deprecated );
		foreach ( $not_caught_deprecated as $not_caught ) {
			$errors[] = "Failed to assert that $not_caught triggered a deprecated notice";
		}

		$unexpected_deprecated = array_diff( $this->caught_deprecated, $this->expected_deprecated );
		foreach ( $unexpected_deprecated as $unexpected ) {
			$errors[] = "Unexpected deprecated notice for $unexpected";
		}

		$not_caught_doing_it_wrong = array_diff( $this->expected_doing_it_wrong, $this->caught_doing_it_wrong );
		foreach ( $not_caught_doing_it_wrong as $not_caught ) {
			$errors[] = "Failed to assert that $not_caught triggered an incorrect usage notice";
		}

		$unexpected_doing_it_wrong = array_diff( $this->caught_doing_it_wrong, $this->expected_doing_it_wrong );
		foreach ( $unexpected_doing_it_wrong as $unexpected ) {
			$errors[] = "Unexpected incorrect usage notice for $unexpected";
		}

		// Perform an assertion, but only if there are expected or unexpected deprecated calls or wrongdoings.
		if ( ! empty( $this->expected_deprecated ) ||
			! empty( $this->expected_doing_it_wrong ) ||
			! empty( $this->caught_deprecated ) ||
			! empty( $this->caught_doing_it_wrong ) ) {
			$this->assertEmpty( $errors, implode( "\n", $errors ) );
		}
	}

	/**
	 * Detects post-test failure conditions.
	 *
	 * We use this method to detect expectedDeprecated and expectedIncorrectUsage annotations.
	 *
	 * @since 4.2.0
	 */
	protected function assert_post_conditions() {
		$this->expectedDeprecated();
	}

	/**
	 * Declares an expected `_deprecated_function()` or `_deprecated_argument()` call from within a test.
	 *
	 * @since 4.2.0
	 *
	 * @param string $deprecated Name of the function, method, class, or argument that is deprecated. Must match
	 *                           the first parameter of the `_deprecated_function()` or `_deprecated_argument()` call.
	 */
	public function setExpectedDeprecated( $deprecated ) {
		$this->expected_deprecated[] = $deprecated;
	}

	/**
	 * Declares an expected `_doing_it_wrong()` call from within a test.
	 *
	 * @since 4.2.0
	 *
	 * @param string $doing_it_wrong Name of the function, method, or class that appears in the first argument
	 *                               of the source `_doing_it_wrong()` call.
	 */
	public function setExpectedIncorrectUsage( $doing_it_wrong ) {
		$this->expected_doing_it_wrong[] = $doing_it_wrong;
	}

	/**
	 * Redundant PHPUnit 6+ compatibility shim. DO NOT USE!
	 *
	 * This method is only left in place for backward compatibility reasons.
	 *
	 * @deprecated 5.9.0 Use the PHPUnit native expectException*() methods directly.
	 *
	 * @param mixed      $exception
	 * @param string     $message
	 * @param int|string $code
	 */
	public function setExpectedException( $exception, $message = '', $code = null ) {
		$this->expectException( $exception );

		if ( '' !== $message ) {
			$this->expectExceptionMessage( $message );
		}

		if ( null !== $code ) {
			$this->expectExceptionCode( $code );
		}
	}

	/**
	 * Adds a deprecated function to the list of caught deprecated calls.
	 *
	 * @param string $function The deprecated function.
	 */
	public function deprecated_function_run( $function ) {
		if ( ! in_array( $function, $this->caught_deprecated, true ) ) {
			$this->caught_deprecated[] = $function;
		}
	}

	/**
	 * Adds a function called in a wrong way to the list of `_doing_it_wrong()` calls.
	 *
	 * @param string $function The function to add.
	 */
	public function doing_it_wrong_run( $function ) {
		if ( ! in_array( $function, $this->caught_doing_it_wrong, true ) ) {
			$this->caught_doing_it_wrong[] = $function;
		}
	}

	/**
	 * Asserts that the given value is an instance of WP_Error.
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public function assertWPError( $actual, $message = '' ) {
		$this->assertInstanceOf( 'WP_Error', $actual, $message );
	}

	/**
	 * Asserts that the given value is not an instance of WP_Error.
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public function assertNotWPError( $actual, $message = '' ) {
		if ( '' === $message && is_wp_error( $actual ) ) {
			$message = $actual->get_error_message();
		}
		$this->assertNotInstanceOf( 'WP_Error', $actual, $message );
	}

	/**
	 * Asserts that the given value is an instance of IXR_Error.
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public function assertIXRError( $actual, $message = '' ) {
		$this->assertInstanceOf( 'IXR_Error', $actual, $message );
	}

	/**
	 * Asserts that the given value is not an instance of IXR_Error.
	 *
	 * @param mixed  $actual  The value to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public function assertNotIXRError( $actual, $message = '' ) {
		if ( '' === $message && $actual instanceof IXR_Error ) {
			$message = $actual->message;
		}
		$this->assertNotInstanceOf( 'IXR_Error', $actual, $message );
	}

	/**
	 * Asserts that the given fields are present in the given object.
	 *
	 * @since UT (3.7.0)
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param object $object  The object to check.
	 * @param array  $fields  The fields to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public function assertEqualFields( $object, $fields, $message = '' ) {
		$this->assertIsObject( $object, $message . ' Passed $object is not an object.' );
		$this->assertIsArray( $fields, $message . ' Passed $fields is not an array.' );
		$this->assertNotEmpty( $fields, $message . ' Fields array is empty.' );

		foreach ( $fields as $field_name => $field_value ) {
			$this->assertObjectHasAttribute( $field_name, $object, $message . " Property $field_name does not exist on the object." );
			$this->assertSame( $field_value, $object->$field_name, $message . " Value of property $field_name is not $field_value." );
		}
	}

	/**
	 * Asserts that two values are equal, with whitespace differences discarded.
	 *
	 * @since UT (3.7.0)
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param mixed  $expected The expected value.
	 * @param mixed  $actual   The actual value.
	 * @param string $message  Optional. Message to display when the assertion fails.
	 */
	public function assertDiscardWhitespace( $expected, $actual, $message = '' ) {
		if ( is_string( $expected ) ) {
			$expected = preg_replace( '/\s*/', '', $expected );
		}

		if ( is_string( $actual ) ) {
			$actual = preg_replace( '/\s*/', '', $actual );
		}

		$this->assertEquals( $expected, $actual, $message );
	}

	/**
	 * Asserts that two values have the same type and value, with EOL differences discarded.
	 *
	 * @since 5.6.0
	 * @since 5.8.0 Added support for nested arrays.
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param mixed  $expected The expected value.
	 * @param mixed  $actual   The actual value.
	 * @param string $message  Optional. Message to display when the assertion fails.
	 */
	public function assertSameIgnoreEOL( $expected, $actual, $message = '' ) {
		if ( null !== $expected ) {
			$expected = map_deep(
				$expected,
				static function ( $value ) {
					if ( is_string( $value ) ) {
						return str_replace( "\r\n", "\n", $value );
					}

					return $value;
				}
			);
		}

		if ( null !== $actual ) {
			$actual = map_deep(
				$actual,
				static function ( $value ) {
					if ( is_string( $value ) ) {
						return str_replace( "\r\n", "\n", $value );
					}

					return $value;
				}
			);
		}

		$this->assertSame( $expected, $actual, $message );
	}

	/**
	 * Asserts that two values are equal, with EOL differences discarded.
	 *
	 * @since 5.4.0
	 * @since 5.6.0 Turned into an alias for `::assertSameIgnoreEOL()`.
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param mixed  $expected The expected value.
	 * @param mixed  $actual   The actual value.
	 * @param string $message  Optional. Message to display when the assertion fails.
	 */
	public function assertEqualsIgnoreEOL( $expected, $actual, $message = '' ) {
		$this->assertSameIgnoreEOL( $expected, $actual, $message );
	}

	/**
	 * Asserts that the contents of two un-keyed, single arrays are the same, without accounting for the order of elements.
	 *
	 * @since 5.6.0
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param array  $expected Expected array.
	 * @param array  $actual   Array to check.
	 * @param string $message  Optional. Message to display when the assertion fails.
	 */
	public function assertSameSets( $expected, $actual, $message = '' ) {
		$this->assertIsArray( $expected, $message . ' Expected value must be an array.' );
		$this->assertIsArray( $actual, $message . ' Value under test is not an array.' );

		sort( $expected );
		sort( $actual );
		$this->assertSame( $expected, $actual, $message );
	}

	/**
	 * Asserts that the contents of two un-keyed, single arrays are equal, without accounting for the order of elements.
	 *
	 * @since 3.5.0
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param array  $expected Expected array.
	 * @param array  $actual   Array to check.
	 * @param string $message  Optional. Message to display when the assertion fails.
	 */
	public function assertEqualSets( $expected, $actual, $message = '' ) {
		$this->assertIsArray( $expected, $message . ' Expected value must be an array.' );
		$this->assertIsArray( $actual, $message . ' Value under test is not an array.' );

		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual, $message );
	}

	/**
	 * Asserts that the contents of two keyed, single arrays are the same, without accounting for the order of elements.
	 *
	 * @since 5.6.0
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param array  $expected Expected array.
	 * @param array  $actual   Array to check.
	 * @param string $message  Optional. Message to display when the assertion fails.
	 */
	public function assertSameSetsWithIndex( $expected, $actual, $message = '' ) {
		$this->assertIsArray( $expected, $message . ' Expected value must be an array.' );
		$this->assertIsArray( $actual, $message . ' Value under test is not an array.' );

		ksort( $expected );
		ksort( $actual );
		$this->assertSame( $expected, $actual, $message );
	}

	/**
	 * Asserts that the contents of two keyed, single arrays are equal, without accounting for the order of elements.
	 *
	 * @since 4.1.0
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param array  $expected Expected array.
	 * @param array  $actual   Array to check.
	 * @param string $message  Optional. Message to display when the assertion fails.
	 */
	public function assertEqualSetsWithIndex( $expected, $actual, $message = '' ) {
		$this->assertIsArray( $expected, $message . ' Expected value must be an array.' );
		$this->assertIsArray( $actual, $message . ' Value under test is not an array.' );

		ksort( $expected );
		ksort( $actual );
		$this->assertEquals( $expected, $actual, $message );
	}

	/**
	 * Asserts that the given variable is a multidimensional array, and that all arrays are non-empty.
	 *
	 * @since 4.8.0
	 * @since 5.9.0 Added the `$message` parameter.
	 *
	 * @param array  $array   Array to check.
	 * @param string $message Optional. Message to display when the assertion fails.
	 */
	public function assertNonEmptyMultidimensionalArray( $array, $message = '' ) {
		$this->assertIsArray( $array, $message . ' Value under test is not an array.' );
		$this->assertNotEmpty( $array, $message . ' Array is empty.' );

		foreach ( $array as $sub_array ) {
			$this->assertIsArray( $sub_array, $message . ' Subitem of the array is not an array.' );
			$this->assertNotEmpty( $sub_array, $message . ' Subitem of the array is empty.' );
		}
	}

	/**
	 * Sets the global state to as if a given URL has been requested.
	 *
	 * This sets:
	 * - The super globals.
	 * - The globals.
	 * - The query variables.
	 * - The main query.
	 *
	 * @since 3.5.0
	 *
	 * @param string $url The URL for the request.
	 */
	public function go_to( $url ) {
		/*
		 * Note: the WP and WP_Query classes like to silently fetch parameters
		 * from all over the place (globals, GET, etc), which makes it tricky
		 * to run them more than once without very carefully clearing everything.
		 */
		$_GET  = array();
		$_POST = array();
		foreach ( array( 'query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow', 'current_screen' ) as $v ) {
			if ( isset( $GLOBALS[ $v ] ) ) {
				unset( $GLOBALS[ $v ] );
			}
		}
		$parts = parse_url( $url );
		if ( isset( $parts['scheme'] ) ) {
			$req = isset( $parts['path'] ) ? $parts['path'] : '';
			if ( isset( $parts['query'] ) ) {
				$req .= '?' . $parts['query'];
				// Parse the URL query vars into $_GET.
				parse_str( $parts['query'], $_GET );
			}
		} else {
			$req = $url;
		}
		if ( ! isset( $parts['query'] ) ) {
			$parts['query'] = '';
		}

		$_SERVER['REQUEST_URI'] = $req;
		unset( $_SERVER['PATH_INFO'] );

		self::flush_cache();
		unset( $GLOBALS['wp_query'], $GLOBALS['wp_the_query'] );
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];

		$public_query_vars  = $GLOBALS['wp']->public_query_vars;
		$private_query_vars = $GLOBALS['wp']->private_query_vars;

		$GLOBALS['wp']                     = new WP();
		$GLOBALS['wp']->public_query_vars  = $public_query_vars;
		$GLOBALS['wp']->private_query_vars = $private_query_vars;

		_cleanup_query_vars();

		$GLOBALS['wp']->main( $parts['query'] );
	}

	/**
	 * Allows tests to be skipped on single or multisite installs by using @group annotations.
	 *
	 * This is a custom extension of the PHPUnit requirements handling.
	 *
	 * @since 3.5.0
	 * @deprecated 5.9.0 This method has not been functional since PHPUnit 7.0.
	 */
	protected function checkRequirements() {
		// For PHPUnit 5/6, as we're overloading a public PHPUnit native method in those versions.
		if ( is_callable( 'PHPUnit\Framework\TestCase', 'checkRequirements' ) ) {
			parent::checkRequirements();
		}
	}

	/**
	 * Skips the current test if there is an open Trac ticket associated with it.
	 *
	 * @since 3.5.0
	 *
	 * @param int $ticket_id Ticket number.
	 */
	public function knownWPBug( $ticket_id ) {
		if ( WP_TESTS_FORCE_KNOWN_BUGS || in_array( $ticket_id, self::$forced_tickets, true ) ) {
			return;
		}
		if ( ! TracTickets::isTracTicketClosed( 'https://core.trac.wordpress.org', $ticket_id ) ) {
			$this->markTestSkipped( sprintf( 'WordPress Ticket #%d is not fixed', $ticket_id ) );
		}
	}

	/**
	 * Skips the current test if there is an open Unit Test Trac ticket associated with it.
	 *
	 * @since 3.5.0
	 * @deprecated No longer used since the Unit Test Trac was merged into the Core Trac.
	 *
	 * @param int $ticket_id Ticket number.
	 */
	public function knownUTBug( $ticket_id ) {
		return;
	}

	/**
	 * Skips the current test if there is an open Plugin Trac ticket associated with it.
	 *
	 * @since 3.5.0
	 *
	 * @param int $ticket_id Ticket number.
	 */
	public function knownPluginBug( $ticket_id ) {
		if ( WP_TESTS_FORCE_KNOWN_BUGS || in_array( 'Plugin' . $ticket_id, self::$forced_tickets, true ) ) {
			return;
		}
		if ( ! TracTickets::isTracTicketClosed( 'https://plugins.trac.wordpress.org', $ticket_id ) ) {
			$this->markTestSkipped( sprintf( 'WordPress Plugin Ticket #%d is not fixed', $ticket_id ) );
		}
	}

	/**
	 * Adds a Trac ticket number to the `$forced_tickets` property.
	 *
	 * @since 3.5.0
	 *
	 * @param int $ticket Ticket number.
	 */
	public static function forceTicket( $ticket ) {
		self::$forced_tickets[] = $ticket;
	}

	/**
	 * Custom preparations for the PHPUnit process isolation template.
	 *
	 * When restoring global state between tests, PHPUnit defines all the constants that were already defined, and then
	 * includes included files. This does not work with WordPress, as the included files define the constants.
	 *
	 * This method defines the constants after including files.
	 *
	 * @param Text_Template $template The template to prepare.
	 */
	public function prepareTemplate( Text_Template $template ) {
		$template->setVar( array( 'constants' => '' ) );
		$template->setVar( array( 'wp_constants' => PHPUnit_Util_GlobalState::getConstantsAsString() ) );
		parent::prepareTemplate( $template );
	}

	/**
	 * Creates a unique temporary file name.
	 *
	 * The directory in which the file is created depends on the environment configuration.
	 *
	 * @since 3.5.0
	 *
	 * @return string|bool Path on success, else false.
	 */
	public function temp_filename() {
		$tmp_dir = '';
		$dirs    = array( 'TMP', 'TMPDIR', 'TEMP' );

		foreach ( $dirs as $dir ) {
			if ( isset( $_ENV[ $dir ] ) && ! empty( $_ENV[ $dir ] ) ) {
				$tmp_dir = $dir;
				break;
			}
		}

		if ( empty( $tmp_dir ) ) {
			$tmp_dir = get_temp_dir();
		}

		$tmp_dir = realpath( $tmp_dir );

		return tempnam( $tmp_dir, 'wpunit' );
	}

	/**
	 * Checks each of the WP_Query is_* functions/properties against expected boolean value.
	 *
	 * Any properties that are listed by name as parameters will be expected to be true; all others are
	 * expected to be false. For example, assertQueryTrue( 'is_single', 'is_feed' ) means is_single()
	 * and is_feed() must be true and everything else must be false to pass.
	 *
	 * @since 2.5.0
	 * @since 3.8.0 Moved from `Tests_Query_Conditionals` to `WP_UnitTestCase`.
	 * @since 5.3.0 Formalized the existing `...$prop` parameter by adding it
	 *              to the function signature.
	 *
	 * @param string ...$prop Any number of WP_Query properties that are expected to be true for the current request.
	 */
	public function assertQueryTrue( ...$prop ) {
		global $wp_query;

		$all = array(
			'is_404',
			'is_admin',
			'is_archive',
			'is_attachment',
			'is_author',
			'is_category',
			'is_comment_feed',
			'is_date',
			'is_day',
			'is_embed',
			'is_feed',
			'is_front_page',
			'is_home',
			'is_privacy_policy',
			'is_month',
			'is_page',
			'is_paged',
			'is_post_type_archive',
			'is_posts_page',
			'is_preview',
			'is_robots',
			'is_favicon',
			'is_search',
			'is_single',
			'is_singular',
			'is_tag',
			'is_tax',
			'is_time',
			'is_trackback',
			'is_year',
		);

		foreach ( $prop as $true_thing ) {
			$this->assertContains( $true_thing, $all, "Unknown conditional: {$true_thing}." );
		}

		$passed  = true;
		$message = '';

		foreach ( $all as $query_thing ) {
			$result = is_callable( $query_thing ) ? call_user_func( $query_thing ) : $wp_query->$query_thing;

			if ( in_array( $query_thing, $prop, true ) ) {
				if ( ! $result ) {
					$message .= $query_thing . ' is false but is expected to be true. ' . PHP_EOL;
					$passed   = false;
				}
			} elseif ( $result ) {
				$message .= $query_thing . ' is true but is expected to be false. ' . PHP_EOL;
				$passed   = false;
			}
		}

		if ( ! $passed ) {
			$this->fail( $message );
		}
	}

	/**
	 * Selectively deletes a file.
	 *
	 * Does not delete a file if its path is set in the `$ignore_files` property.
	 *
	 * @param string $file File path.
	 */
	public function unlink( $file ) {
		$exists = is_file( $file );
		if ( $exists && ! in_array( $file, self::$ignore_files, true ) ) {
			//error_log( $file );
			unlink( $file );
		} elseif ( ! $exists ) {
			$this->fail( "Trying to delete a file that doesn't exist: $file" );
		}
	}

	/**
	 * Selectively deletes files from a directory.
	 *
	 * Does not delete files if their paths are set in the `$ignore_files` property.
	 *
	 * @param string $path Directory path.
	 */
	public function rmdir( $path ) {
		$files = $this->files_in_dir( $path );
		foreach ( $files as $file ) {
			if ( ! in_array( $file, self::$ignore_files, true ) ) {
				$this->unlink( $file );
			}
		}
	}

	/**
	 * Deletes files added to the `uploads` directory during tests.
	 *
	 * This method works in tandem with the `setUp()` and `rmdir()` methods:
	 * - `setUp()` scans the `uploads` directory before every test, and stores its contents inside of the
	 *   `$ignore_files` property.
	 * - `rmdir()` and its helper methods only delete files that are not listed in the `$ignore_files` property. If
	 *   called during `tearDown()` in tests, this will only delete files added during the previously run test.
	 */
	public function remove_added_uploads() {
		$uploads = wp_upload_dir();
		$this->rmdir( $uploads['basedir'] );
	}

	/**
	 * Returns a list of all files contained inside a directory.
	 *
	 * @since 4.0.0
	 *
	 * @param string $dir Path to the directory to scan.
	 * @return array List of file paths.
	 */
	public function files_in_dir( $dir ) {
		$files = array();

		$iterator = new RecursiveDirectoryIterator( $dir );
		$objects  = new RecursiveIteratorIterator( $iterator );
		foreach ( $objects as $name => $object ) {
			if ( is_file( $name ) ) {
				$files[] = $name;
			}
		}

		return $files;
	}

	/**
	 * Returns a list of all files contained inside the `uploads` directory.
	 *
	 * @since 4.0.0
	 *
	 * @return array List of file paths.
	 */
	public function scan_user_uploads() {
		static $files = array();
		if ( ! empty( $files ) ) {
			return $files;
		}

		$uploads = wp_upload_dir();
		$files   = $this->files_in_dir( $uploads['basedir'] );
		return $files;
	}

	/**
	 * Deletes all directories contained inside a directory.
	 *
	 * @since 4.1.0
	 *
	 * @param string $path Path to the directory to scan.
	 */
	public function delete_folders( $path ) {
		$this->matched_dirs = array();
		if ( ! is_dir( $path ) ) {
			return;
		}

		$this->scandir( $path );
		foreach ( array_reverse( $this->matched_dirs ) as $dir ) {
			rmdir( $dir );
		}
		rmdir( $path );
	}

	/**
	 * Retrieves all directories contained inside a directory and stores them in the `$matched_dirs` property.
	 * Hidden directories are ignored.
	 *
	 * This is a helper for the `delete_folders()` method.
	 *
	 * @since 4.1.0
	 *
	 * @param string $dir Path to the directory to scan.
	 */
	public function scandir( $dir ) {
		foreach ( scandir( $dir ) as $path ) {
			if ( 0 !== strpos( $path, '.' ) && is_dir( $dir . '/' . $path ) ) {
				$this->matched_dirs[] = $dir . '/' . $path;
				$this->scandir( $dir . '/' . $path );
			}
		}
	}

	/**
	 * Converts a microtime string into a float.
	 *
	 * @since 4.1.0
	 *
	 * @param string $microtime Time string generated by `microtime()`.
	 * @return float `microtime()` output as a float.
	 */
	protected function _microtime_to_float( $microtime ) {
		$time_array = explode( ' ', $microtime );
		return array_sum( $time_array );
	}

	/**
	 * Deletes a user from the database in a Multisite-agnostic way.
	 *
	 * @since 4.3.0
	 *
	 * @param int $user_id User ID.
	 * @return bool True if the user was deleted.
	 */
	public static function delete_user( $user_id ) {
		if ( is_multisite() ) {
			return wpmu_delete_user( $user_id );
		}

		return wp_delete_user( $user_id );
	}

	/**
	 * Resets permalinks and flushes rewrites.
	 *
	 * @since 4.4.0
	 *
	 * @global WP_Rewrite $wp_rewrite
	 *
	 * @param string $structure Optional. Permalink structure to set. Default empty.
	 */
	public function set_permalink_structure( $structure = '' ) {
		global $wp_rewrite;

		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( $structure );
		$wp_rewrite->flush_rules();
	}

	/**
	 * Creates an attachment post from an uploaded file.
	 *
	 * @since 4.4.0
	 *
	 * @param array $upload         Array of information about the uploaded file, provided by wp_upload_bits().
	 * @param int   $parent_post_id Optional. Parent post ID.
	 * @return int|WP_Error The attachment ID on success. The value 0 or WP_Error on failure.
	 */
	public function _make_attachment( $upload, $parent_post_id = 0 ) {
		$type = '';
		if ( ! empty( $upload['type'] ) ) {
			$type = $upload['type'];
		} else {
			$mime = wp_check_filetype( $upload['file'] );
			if ( $mime ) {
				$type = $mime['type'];
			}
		}

		$attachment = array(
			'post_title'     => wp_basename( $upload['file'] ),
			'post_content'   => '',
			'post_type'      => 'attachment',
			'post_parent'    => $parent_post_id,
			'post_mime_type' => $type,
			'guid'           => $upload['url'],
		);

		$id = wp_insert_attachment( $attachment, $upload['file'], $parent_post_id );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		return $id;
	}

	/**
	 * Updates the modified and modified GMT date of a post in the database.
	 *
	 * @since 4.8.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $date    Post date, in the format YYYY-MM-DD HH:MM:SS.
	 * @return int|false 1 on success, or false on error.
	 */
	protected function update_post_modified( $post_id, $date ) {
		global $wpdb;
		return $wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => $date,
				'post_modified_gmt' => $date,
			),
			array(
				'ID' => $post_id,
			),
			array(
				'%s',
				'%s',
			),
			array(
				'%d',
			)
		);
	}

	/**
	 * Touches the given file and its directory if it doesn't already exist.
	 *
	 * This can be used to ensure a file that is implictly relied on in a test exists
	 * without it having to be built.
	 *
	 * @param string $file The file name.
	 */
	public static function touch( $file ) {
		if ( file_exists( $file ) ) {
			return;
		}

		$dir = dirname( $file );

		if ( ! file_exists( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		touch( $file );
	}
}
