<?php

/**
 * Tests for the WP_Recovery_Mode class.
 *
 * @group error-protection
 *
 * @coversDefaultClass WP_Recovery_Mode
 */
class Tests_Error_Protection_wpRecoveryMode extends WP_UnitTestCase {

	const TEST_SESSION_ID = 'test_recovery_mode_session';

	/**
	 * Original is_active property of the recovery mode singleton.
	 *
	 * @var bool
	 */
	private $orig_is_active;

	/**
	 * Original session_id property of the recovery mode singleton.
	 *
	 * @var string
	 */
	private $orig_session_id;

	/**
	 * Original $wp_theme_directories global value.
	 *
	 * @var array
	 */
	private $orig_theme_directories;

	public function set_up() {
		parent::set_up();

		$this->orig_is_active         = $this->get_property( wp_recovery_mode(), 'is_active' );
		$this->orig_session_id        = $this->get_property( wp_recovery_mode(), 'session_id' );
		$this->orig_theme_directories = $GLOBALS['wp_theme_directories'];
	}

	public function tear_down() {
		$this->set_property( wp_recovery_mode(), 'is_active', $this->orig_is_active );
		$this->set_property( wp_recovery_mode(), 'session_id', $this->orig_session_id );

		$GLOBALS['wp_theme_directories'] = $this->orig_theme_directories;

		unset( $_COOKIE[ RECOVERY_MODE_COOKIE ] );

		parent::tear_down();
	}

	/**
	 * Gets a private property of a recovery mode instance.
	 *
	 * @param WP_Recovery_Mode $recovery_mode Recovery mode instance.
	 * @param string           $property      Property name.
	 * @return mixed Property value.
	 */
	private function get_property( WP_Recovery_Mode $recovery_mode, $property ) {
		$reflection = new ReflectionProperty( $recovery_mode, $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->getValue( $recovery_mode );
	}

	/**
	 * Sets a private property of a recovery mode instance.
	 *
	 * @param WP_Recovery_Mode $recovery_mode Recovery mode instance.
	 * @param string           $property      Property name.
	 * @param mixed            $value         Property value.
	 */
	private function set_property( WP_Recovery_Mode $recovery_mode, $property, $value ) {
		$reflection = new ReflectionProperty( $recovery_mode, $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$reflection->setValue( $recovery_mode, $value );
	}

	/**
	 * Calls a protected method of a recovery mode instance.
	 *
	 * @param WP_Recovery_Mode $recovery_mode Recovery mode instance.
	 * @param string           $method        Method name.
	 * @param mixed            ...$args       Method arguments.
	 * @return mixed Method return value.
	 */
	private function call_method( WP_Recovery_Mode $recovery_mode, $method, ...$args ) {
		$reflection = new ReflectionMethod( $recovery_mode, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invoke( $recovery_mode, ...$args );
	}

	/**
	 * Puts the recovery mode singleton into an active session.
	 */
	private function activate_recovery_mode() {
		$this->set_property( wp_recovery_mode(), 'is_active', true );
		$this->set_property( wp_recovery_mode(), 'session_id', self::TEST_SESSION_ID );
	}

	/**
	 * Builds a signed recovery mode cookie value.
	 *
	 * @param string $random Random part of the cookie, used to derive the session ID.
	 * @return string Cookie value.
	 */
	private function build_cookie( $random ) {
		$service    = new WP_Recovery_Mode_Cookie_Service();
		$reflection = new ReflectionMethod( $service, 'recovery_mode_hash' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$to_sign = sprintf( 'recovery_mode|%s|%s', time(), $random );

		return base64_encode( sprintf( '%s|%s', $to_sign, $reflection->invoke( $service, $to_sign ) ) );
	}

	/**
	 * Tests the state of a new instance.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_initialized
	 * @covers ::is_active
	 * @covers ::get_session_id
	 */
	public function test_new_instance_is_not_initialized_or_active() {
		$recovery_mode = new WP_Recovery_Mode();

		$this->assertFalse( $recovery_mode->is_initialized(), 'A new instance should not be initialized.' );
		$this->assertFalse( $recovery_mode->is_active(), 'A new instance should not be active.' );
		$this->assertSame( '', $recovery_mode->get_session_id(), 'A new instance should not have a session ID.' );
	}

	/**
	 * Tests that initialize() registers the recovery mode hooks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::initialize
	 * @covers ::is_initialized
	 */
	public function test_initialize_registers_hooks() {
		$recovery_mode = new WP_Recovery_Mode();
		$recovery_mode->initialize();

		$this->assertTrue( $recovery_mode->is_initialized(), 'The instance should be initialized.' );
		$this->assertFalse( $recovery_mode->is_active(), 'Recovery mode should not be active without a cookie or constant.' );
		$this->assertSame( 10, has_action( 'wp_logout', array( $recovery_mode, 'exit_recovery_mode' ) ) );
		$this->assertSame( 10, has_action( 'login_form_exit_recovery_mode', array( $recovery_mode, 'handle_exit_recovery_mode' ) ) );
		$this->assertSame( 10, has_action( 'recovery_mode_clean_expired_keys', array( $recovery_mode, 'clean_expired_keys' ) ) );
	}

	/**
	 * Tests that initialize() schedules the daily key cleanup event.
	 *
	 * @ticket 65819
	 *
	 * @covers ::initialize
	 */
	public function test_initialize_schedules_daily_cleanup_event() {
		wp_clear_scheduled_hook( 'recovery_mode_clean_expired_keys' );

		$recovery_mode = new WP_Recovery_Mode();
		$recovery_mode->initialize();

		$event = wp_get_scheduled_event( 'recovery_mode_clean_expired_keys' );

		$this->assertIsObject( $event, 'The cleanup event should be scheduled.' );
		$this->assertSame( 'daily', $event->schedule, 'The cleanup event should run daily.' );
	}

	/**
	 * Tests that initialize() keeps an already scheduled key cleanup event.
	 *
	 * @ticket 65819
	 *
	 * @covers ::initialize
	 */
	public function test_initialize_does_not_reschedule_existing_cleanup_event() {
		$timestamp = time() + HOUR_IN_SECONDS;

		wp_clear_scheduled_hook( 'recovery_mode_clean_expired_keys' );
		wp_schedule_event( $timestamp, 'hourly', 'recovery_mode_clean_expired_keys' );

		$recovery_mode = new WP_Recovery_Mode();
		$recovery_mode->initialize();

		$event = wp_get_scheduled_event( 'recovery_mode_clean_expired_keys' );

		$this->assertSame( $timestamp, $event->timestamp, 'The existing event should keep its timestamp.' );
		$this->assertSame( 'hourly', $event->schedule, 'The existing event should keep its schedule.' );
	}

	/**
	 * Tests that initialize() starts a session from the WP_RECOVERY_MODE_SESSION_ID constant.
	 *
	 * @ticket 65819
	 *
	 * @covers ::initialize
	 * @covers ::is_active
	 * @covers ::get_session_id
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_initialize_uses_session_id_constant() {
		define( 'WP_RECOVERY_MODE_SESSION_ID', 'session-from-constant' );

		$recovery_mode = new WP_Recovery_Mode();
		$recovery_mode->initialize();

		$this->assertTrue( $recovery_mode->is_active(), 'Recovery mode should be active.' );
		$this->assertSame( 'session-from-constant', $recovery_mode->get_session_id() );
	}

	/**
	 * Tests that initialize() starts a session from a valid cookie.
	 *
	 * @ticket 65819
	 *
	 * @covers ::initialize
	 * @covers ::handle_cookie
	 */
	public function test_initialize_starts_session_from_valid_cookie() {
		$_COOKIE[ RECOVERY_MODE_COOKIE ] = $this->build_cookie( 'abcdefghij0123456789' );

		$recovery_mode = new WP_Recovery_Mode();
		$recovery_mode->initialize();

		$this->assertTrue( $recovery_mode->is_active(), 'Recovery mode should be active.' );
		$this->assertSame( '6256c24ff916241741c75edc27548d4ba4124a29', $recovery_mode->get_session_id() );
	}

	/**
	 * Tests that initialize() dies and stays inactive when the cookie is invalid.
	 *
	 * @ticket 65819
	 *
	 * @covers ::initialize
	 * @covers ::handle_cookie
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_initialize_dies_on_invalid_cookie() {
		$_COOKIE[ RECOVERY_MODE_COOKIE ] = 'not-a-valid-cookie';

		$recovery_mode = new WP_Recovery_Mode();

		try {
			$recovery_mode->initialize();
			$this->fail( 'An invalid cookie should stop the request.' );
		} catch ( WPDieException $exception ) {
			$this->assertSame( 'Invalid cookie format.', $exception->getMessage() );
		}

		$this->assertFalse( $recovery_mode->is_active(), 'Recovery mode should not be active.' );
		$this->assertSame( '', $recovery_mode->get_session_id(), 'No session ID should be set.' );
	}

	/**
	 * Tests that handle_error() rejects errors that do not come from a plugin or theme.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle_error
	 * @covers ::get_extension_for_error
	 *
	 * @dataProvider data_errors_without_extension
	 *
	 * @param array $error Error details.
	 */
	public function test_handle_error_returns_invalid_source_without_extension( $error ) {
		$this->activate_recovery_mode();

		$result = wp_recovery_mode()->handle_error( $error );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_source', $result->get_error_code() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_errors_without_extension() {
		return array(
			'empty error'             => array( array() ),
			'missing file'            => array(
				array(
					'type'    => E_ERROR,
					'message' => 'Fatal error',
				),
			),
			'file outside extensions' => array(
				array(
					'type' => E_ERROR,
					'file' => '/not/a/plugin/or/theme/file.php',
				),
			),
		);
	}

	/**
	 * Tests that handle_error() ignores errors on non-protected endpoints while inactive.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle_error
	 */
	public function test_handle_error_returns_error_on_non_protected_endpoint() {
		reset_phpmailer_instance();

		$recovery_mode = new WP_Recovery_Mode();
		$result        = $recovery_mode->handle_error(
			array(
				'type' => E_ERROR,
				'file' => WP_PLUGIN_DIR . '/my-plugin/my-plugin.php',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'non_protected_endpoint', $result->get_error_code() );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent(), 'No email should be sent.' );
	}

	/**
	 * Tests that handle_error() sends the recovery mode email on a protected endpoint while inactive.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle_error
	 * @covers ::get_email_rate_limit
	 */
	public function test_handle_error_sends_email_on_protected_endpoint() {
		reset_phpmailer_instance();
		set_current_screen( 'dashboard' );

		$error = array(
			'type'    => E_ERROR,
			'file'    => WP_PLUGIN_DIR . '/my-plugin/my-plugin.php',
			'line'    => 12,
			'message' => 'Fatal error',
		);

		$recovery_mode = new WP_Recovery_Mode();

		$this->assertTrue( $recovery_mode->handle_error( $error ), 'The email should be sent.' );
		$this->assertSame( WP_TESTS_EMAIL, tests_retrieve_phpmailer_instance()->get_recipient( 'to' )->address );

		$rate_limited = $recovery_mode->handle_error( $error );

		$this->assertWPError( $rate_limited, 'A second email should be rate limited.' );
		$this->assertSame( 'email_sent_already', $rate_limited->get_error_code() );
	}

	/**
	 * Tests that handle_error() pauses the plugin that caused the error while active.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle_error
	 * @covers ::store_error
	 * @covers ::get_extension_for_error
	 */
	public function test_handle_error_stores_plugin_error_when_active() {
		$this->assertTrue( headers_sent(), 'Headers must already be sent, otherwise handle_error() redirects and exits.' );

		$this->activate_recovery_mode();

		$error = array(
			'type'    => E_ERROR,
			'file'    => WP_PLUGIN_DIR . '/my-plugin/includes/broken.php',
			'line'    => 12,
			'message' => 'Fatal error',
		);

		$this->assertTrue( wp_recovery_mode()->handle_error( $error ) );
		$this->assertSame( array( 'my-plugin' => $error ), wp_paused_plugins()->get_all(), 'The plugin error should be stored.' );
		$this->assertSame( array(), wp_paused_themes()->get_all(), 'No theme error should be stored.' );
	}

	/**
	 * Tests that handle_error() pauses the theme that caused the error while active.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle_error
	 * @covers ::store_error
	 * @covers ::get_extension_for_error
	 */
	public function test_handle_error_stores_theme_error_when_active() {
		$this->assertTrue( headers_sent(), 'Headers must already be sent, otherwise handle_error() redirects and exits.' );

		$GLOBALS['wp_theme_directories'] = array( '/srv/www/wp-content/themes' );

		$this->activate_recovery_mode();

		$error = array(
			'type'    => E_ERROR,
			'file'    => '/srv/www/wp-content/themes/my-theme/functions.php',
			'line'    => 34,
			'message' => 'Fatal error',
		);

		$this->assertTrue( wp_recovery_mode()->handle_error( $error ) );
		$this->assertSame( array( 'my-theme' => $error ), wp_paused_themes()->get_all(), 'The theme error should be stored.' );
		$this->assertSame( array(), wp_paused_plugins()->get_all(), 'No plugin error should be stored.' );
	}

	/**
	 * Tests that handle_error() reports a storage error when the error cannot be saved.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle_error
	 * @covers ::store_error
	 */
	public function test_handle_error_returns_storage_error_when_error_is_not_saved() {
		$this->activate_recovery_mode();

		// Makes update_option() see no change, so it reports a failed write.
		add_filter( 'pre_update_option_' . self::TEST_SESSION_ID . '_paused_extensions', '__return_false' );

		$result = wp_recovery_mode()->handle_error(
			array(
				'type' => E_ERROR,
				'file' => WP_PLUGIN_DIR . '/my-plugin/my-plugin.php',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'storage_error', $result->get_error_code() );
	}

	/**
	 * Tests that is_network_plugin() returns false for extensions that are not network-activated plugins.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_network_plugin
	 *
	 * @dataProvider data_is_network_plugin_returns_false
	 *
	 * @param array $extension Extension type and slug.
	 */
	public function test_is_network_plugin_returns_false( $extension ) {
		$this->assertFalse( $this->call_method( new WP_Recovery_Mode(), 'is_network_plugin', $extension ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_is_network_plugin_returns_false() {
		return array(
			'theme'                        => array(
				array(
					'type' => 'theme',
					'slug' => 'custom-internationalized-plugin',
				),
			),
			'plugin not network activated' => array(
				array(
					'type' => 'plugin',
					'slug' => 'custom-internationalized-plugin',
				),
			),
		);
	}

	/**
	 * Tests that exit_recovery_mode() does nothing when recovery mode is not active.
	 *
	 * @ticket 65819
	 *
	 * @covers ::exit_recovery_mode
	 */
	public function test_exit_recovery_mode_returns_false_when_not_active() {
		update_option( 'recovery_mode_email_last_sent', 1234567890 );

		$recovery_mode = new WP_Recovery_Mode();

		$this->assertFalse( $recovery_mode->exit_recovery_mode() );
		$this->assertSame( 1234567890, get_option( 'recovery_mode_email_last_sent' ), 'The email rate limit should be kept.' );
	}

	/**
	 * Tests that exit_recovery_mode() clears the session data.
	 *
	 * @ticket 65819
	 *
	 * @covers ::exit_recovery_mode
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_exit_recovery_mode_clears_session_data() {
		$this->activate_recovery_mode();

		update_option( 'recovery_mode_email_last_sent', 1234567890 );
		wp_paused_plugins()->set( 'my-plugin', array( 'message' => 'Plugin error' ) );
		wp_paused_themes()->set( 'my-theme', array( 'message' => 'Theme error' ) );

		$this->assertTrue( wp_recovery_mode()->exit_recovery_mode() );
		$this->assertFalse( get_option( 'recovery_mode_email_last_sent' ), 'The email rate limit should be cleared.' );
		$this->assertFalse( get_option( self::TEST_SESSION_ID . '_paused_extensions' ), 'The paused extensions should be deleted.' );
	}

	/**
	 * Tests that handle_exit_recovery_mode() does nothing without the exit action.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle_exit_recovery_mode
	 *
	 * @dataProvider data_non_exit_actions
	 *
	 * @param array $query Query arguments.
	 */
	public function test_handle_exit_recovery_mode_ignores_other_actions( $query ) {
		$this->activate_recovery_mode();

		wp_paused_plugins()->set( 'my-plugin', array( 'message' => 'Plugin error' ) );

		$_GET = $query;

		$this->assertNull( wp_recovery_mode()->handle_exit_recovery_mode() );
		$this->assertSame(
			array( 'my-plugin' => array( 'message' => 'Plugin error' ) ),
			wp_paused_plugins()->get_all(),
			'The session data should be kept.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_non_exit_actions() {
		return array(
			'no action'       => array( array() ),
			'empty action'    => array( array( 'action' => '' ) ),
			'another action'  => array( array( 'action' => 'logout' ) ),
			'different case'  => array( array( 'action' => 'EXIT_RECOVERY_MODE' ) ),
			'non-string type' => array( array( 'action' => array( 'exit_recovery_mode' ) ) ),
		);
	}

	/**
	 * Tests that handle_exit_recovery_mode() dies when the nonce is missing or invalid.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle_exit_recovery_mode
	 *
	 * @dataProvider data_invalid_exit_nonces
	 *
	 * @param array $query Query arguments.
	 */
	public function test_handle_exit_recovery_mode_dies_on_invalid_nonce( $query ) {
		$this->activate_recovery_mode();

		wp_paused_plugins()->set( 'my-plugin', array( 'message' => 'Plugin error' ) );

		$_GET = $query;

		try {
			wp_recovery_mode()->handle_exit_recovery_mode();
			$this->fail( 'An invalid nonce should stop the request.' );
		} catch ( WPDieException $exception ) {
			$this->assertSame( 'Exit recovery mode link expired.', $exception->getMessage() );
			$this->assertSame( 403, $exception->getCode() );
		}

		$this->assertTrue( wp_recovery_mode()->is_active(), 'Recovery mode should stay active.' );
		$this->assertSame(
			array( 'my-plugin' => array( 'message' => 'Plugin error' ) ),
			wp_paused_plugins()->get_all(),
			'The session data should be kept.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_invalid_exit_nonces() {
		return array(
			'missing nonce' => array( array( 'action' => 'exit_recovery_mode' ) ),
			'empty nonce'   => array(
				array(
					'action'   => 'exit_recovery_mode',
					'_wpnonce' => '',
				),
			),
			'invalid nonce' => array(
				array(
					'action'   => 'exit_recovery_mode',
					'_wpnonce' => 'invalid',
				),
			),
		);
	}

	/**
	 * Tests that clean_expired_keys() removes keys older than the link lifetime.
	 *
	 * @ticket 65819
	 *
	 * @covers ::clean_expired_keys
	 * @covers ::get_link_ttl
	 */
	public function test_clean_expired_keys_removes_expired_keys() {
		$fresh   = array(
			'hashed_key' => 'fresh-hash',
			'created_at' => time(),
		);
		$expired = array(
			'hashed_key' => 'expired-hash',
			'created_at' => time() - DAY_IN_SECONDS - MINUTE_IN_SECONDS,
		);

		update_option(
			'recovery_keys',
			array(
				'fresh'      => $fresh,
				'expired'    => $expired,
				'no-created' => array( 'hashed_key' => 'no-created-hash' ),
			)
		);

		$recovery_mode = new WP_Recovery_Mode();
		$recovery_mode->clean_expired_keys();

		$this->assertSame( array( 'fresh' => $fresh ), get_option( 'recovery_keys' ) );
	}

	/**
	 * Tests that clean_expired_keys() respects a filtered link lifetime.
	 *
	 * @ticket 65819
	 *
	 * @covers ::clean_expired_keys
	 * @covers ::get_link_ttl
	 */
	public function test_clean_expired_keys_uses_filtered_link_ttl() {
		$keys = array(
			'one-day-old' => array(
				'hashed_key' => 'one-day-old-hash',
				'created_at' => time() - DAY_IN_SECONDS - MINUTE_IN_SECONDS,
			),
		);

		update_option( 'recovery_keys', $keys );

		add_filter(
			'recovery_mode_email_link_ttl',
			static function () {
				return 2 * DAY_IN_SECONDS;
			}
		);

		$recovery_mode = new WP_Recovery_Mode();
		$recovery_mode->clean_expired_keys();

		$this->assertSame( $keys, get_option( 'recovery_keys' ) );
	}

	/**
	 * Tests the default email rate limit.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_email_rate_limit
	 */
	public function test_get_email_rate_limit_defaults_to_one_day() {
		$this->assertSame( 86400, $this->call_method( new WP_Recovery_Mode(), 'get_email_rate_limit' ) );
	}

	/**
	 * Tests that the email rate limit is filterable.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_email_rate_limit
	 */
	public function test_get_email_rate_limit_is_filterable() {
		$filter = new MockAction();
		add_filter( 'recovery_mode_email_rate_limit', array( $filter, 'filter' ) );
		add_filter(
			'recovery_mode_email_rate_limit',
			static function () {
				return 3600;
			},
			20
		);

		$this->assertSame( 3600, $this->call_method( new WP_Recovery_Mode(), 'get_email_rate_limit' ) );
		$this->assertSame( array( array( 86400 ) ), $filter->get_args(), 'The filter should receive the default rate limit.' );
	}

	/**
	 * Tests the link lifetime for different rate limit and lifetime filter values.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_link_ttl
	 *
	 * @dataProvider data_get_link_ttl
	 *
	 * @param int|null $rate_limit Filtered rate limit, or null to keep the default.
	 * @param int|null $link_ttl   Filtered link lifetime, or null to keep the default.
	 * @param int      $expected   Expected link lifetime.
	 */
	public function test_get_link_ttl( $rate_limit, $link_ttl, $expected ) {
		if ( null !== $rate_limit ) {
			add_filter(
				'recovery_mode_email_rate_limit',
				static function () use ( $rate_limit ) {
					return $rate_limit;
				}
			);
		}

		if ( null !== $link_ttl ) {
			add_filter(
				'recovery_mode_email_link_ttl',
				static function () use ( $link_ttl ) {
					return $link_ttl;
				}
			);
		}

		$this->assertSame( $expected, $this->call_method( new WP_Recovery_Mode(), 'get_link_ttl' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_link_ttl() {
		return array(
			'defaults'                          => array( null, null, 86400 ),
			'filtered rate limit only'          => array( 3600, null, 3600 ),
			'lifetime longer than rate limit'   => array( null, 172800, 172800 ),
			'lifetime shorter than rate limit'  => array( null, 3600, 86400 ),
			'zero lifetime'                     => array( null, 0, 86400 ),
			'both filtered, lifetime is longer' => array( 3600, 7200, 7200 ),
		);
	}

	/**
	 * Tests that the link lifetime filter receives the rate limit as its default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_link_ttl
	 */
	public function test_get_link_ttl_filter_receives_rate_limit() {
		$filter = new MockAction();
		add_filter( 'recovery_mode_email_link_ttl', array( $filter, 'filter' ) );

		$this->call_method( new WP_Recovery_Mode(), 'get_link_ttl' );

		$this->assertSame( array( array( 86400 ) ), $filter->get_args() );
	}

	/**
	 * Tests which extension an error file is attributed to.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_extension_for_error
	 *
	 * @dataProvider data_get_extension_for_error
	 *
	 * @param string      $base     Where the file lives: 'plugins', 'themes' or 'none'.
	 * @param string      $path     File path relative to the base directory.
	 * @param array|false $expected Expected extension, or false if there is none.
	 */
	public function test_get_extension_for_error( $base, $path, $expected ) {
		$GLOBALS['wp_theme_directories'] = array( '/srv/www/wp-content/themes', '/srv/www/more-themes' );

		$directories = array(
			'plugins'     => WP_PLUGIN_DIR,
			'themes'      => '/srv/www/wp-content/themes',
			'more-themes' => '/srv/www/more-themes',
			'none'        => '/srv/www/wp-includes',
		);

		$error = array( 'file' => $directories[ $base ] . $path );

		$this->assertSame( $expected, $this->call_method( new WP_Recovery_Mode(), 'get_extension_for_error', $error ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_extension_for_error() {
		return array(
			'plugin main file'             => array(
				'plugins',
				'/my-plugin/my-plugin.php',
				array(
					'type' => 'plugin',
					'slug' => 'my-plugin',
				),
			),
			'nested plugin file'           => array(
				'plugins',
				'/my-plugin/includes/admin/class-admin.php',
				array(
					'type' => 'plugin',
					'slug' => 'my-plugin',
				),
			),
			'single-file plugin'           => array(
				'plugins',
				'/hello.php',
				array(
					'type' => 'plugin',
					'slug' => 'hello.php',
				),
			),
			'plugin file with backslashes' => array(
				'plugins',
				'\\my-plugin\\includes\\broken.php',
				array(
					'type' => 'plugin',
					'slug' => 'my-plugin',
				),
			),
			'theme file'                   => array(
				'themes',
				'/my-theme/functions.php',
				array(
					'type' => 'theme',
					'slug' => 'my-theme',
				),
			),
			'file in second theme root'    => array(
				'more-themes',
				'/other-theme/inc/setup.php',
				array(
					'type' => 'theme',
					'slug' => 'other-theme',
				),
			),
			'file outside extensions'      => array( 'none', '/plugin.php', false ),
		);
	}

	/**
	 * Tests that an error without a file is not attributed to an extension.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_extension_for_error
	 */
	public function test_get_extension_for_error_returns_false_without_file() {
		$error = array(
			'type'    => E_ERROR,
			'message' => 'Fatal error',
		);

		$this->assertFalse( $this->call_method( new WP_Recovery_Mode(), 'get_extension_for_error', $error ) );
	}

	/**
	 * Tests that a theme file is not attributed to a theme when no theme directories are registered.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_extension_for_error
	 */
	public function test_get_extension_for_error_returns_false_without_theme_directories() {
		$GLOBALS['wp_theme_directories'] = array();

		$error = array( 'file' => '/srv/www/wp-content/themes/my-theme/functions.php' );

		$this->assertFalse( $this->call_method( new WP_Recovery_Mode(), 'get_extension_for_error', $error ) );
	}
}
