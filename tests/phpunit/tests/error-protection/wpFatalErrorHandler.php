<?php

/**
 * Tests for the WP_Fatal_Error_Handler class.
 *
 * @group error-protection
 *
 * @coversDefaultClass WP_Fatal_Error_Handler
 */
class Tests_Error_Protection_wpFatalErrorHandler extends WP_UnitTestCase {

	const TROUBLESHOOTING_LINK = '<p><a href="https://wordpress.org/documentation/article/faq-troubleshooting/">Learn more about troubleshooting WordPress.</a></p>';

	/**
	 * Original state of the recovery mode singleton, keyed by property name.
	 *
	 * @var array
	 */
	private $orig_recovery_mode = array();

	/**
	 * Arguments of each wp_die() call made during the test.
	 *
	 * @var array[]
	 */
	private $wp_die_calls = array();

	public function set_up() {
		parent::set_up();

		foreach ( array( 'is_initialized', 'is_active', 'session_id' ) as $property ) {
			$this->orig_recovery_mode[ $property ] = $this->recovery_mode_property( $property )->getValue( wp_recovery_mode() );
		}

		// Record wp_die() calls instead of stopping the test.
		add_filter( 'wp_die_handler', array( $this, 'get_recording_wp_die_handler' ), 20 );

		error_clear_last();
	}

	public function tear_down() {
		foreach ( $this->orig_recovery_mode as $property => $value ) {
			$this->recovery_mode_property( $property )->setValue( wp_recovery_mode(), $value );
		}

		error_clear_last();

		parent::tear_down();
	}

	/**
	 * Gets an accessible property of the recovery mode singleton.
	 *
	 * @param string $property Property name.
	 * @return ReflectionProperty Accessible property.
	 */
	private function recovery_mode_property( $property ) {
		$reflection = new ReflectionProperty( wp_recovery_mode(), $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection;
	}

	/**
	 * Sets the state of the recovery mode singleton.
	 *
	 * @param bool $is_initialized Whether recovery mode is initialized.
	 * @param bool $is_active      Whether a recovery mode session is active.
	 */
	private function set_recovery_mode_state( $is_initialized, $is_active ) {
		$this->recovery_mode_property( 'is_initialized' )->setValue( wp_recovery_mode(), $is_initialized );
		$this->recovery_mode_property( 'is_active' )->setValue( wp_recovery_mode(), $is_active );
		$this->recovery_mode_property( 'session_id' )->setValue( wp_recovery_mode(), $is_active ? 'test_fatal_error_handler_session' : '' );
	}

	/**
	 * Calls a protected method of a fatal error handler.
	 *
	 * @param string $method  Method name.
	 * @param mixed  ...$args Method arguments.
	 * @return mixed Method return value.
	 */
	private function call_handler_method( $method, ...$args ) {
		$handler    = new WP_Fatal_Error_Handler();
		$reflection = new ReflectionMethod( $handler, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invoke( $handler, ...$args );
	}

	/**
	 * Filters the wp_die() handler to record calls.
	 *
	 * @return callable Recording handler.
	 */
	public function get_recording_wp_die_handler() {
		return array( $this, 'record_wp_die' );
	}

	/**
	 * Records a wp_die() call.
	 *
	 * @param string|WP_Error $message Error message or object.
	 * @param string          $title   Error title.
	 * @param array           $args    Arguments.
	 */
	public function record_wp_die( $message, $title, $args ) {
		$this->wp_die_calls[] = array( $message, $title, $args );
	}

	/**
	 * Makes error_get_last() return a warning raised by this test.
	 */
	private function raise_last_error() {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- Silenced on purpose so that only error_get_last() is populated.
		@trigger_error( 'Test fatal error handler warning', E_USER_WARNING );
	}

	/**
	 * Tests which error types are handled.
	 *
	 * @ticket 65819
	 *
	 * @covers ::should_handle_error
	 *
	 * @dataProvider data_should_handle_error
	 *
	 * @param array $error    Error details.
	 * @param bool  $expected Whether the error should be handled.
	 */
	public function test_should_handle_error( $error, $expected ) {
		$this->assertSame( $expected, $this->call_handler_method( 'should_handle_error', $error ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_should_handle_error() {
		return array(
			'E_ERROR'             => array( array( 'type' => E_ERROR ), true ),
			'E_PARSE'             => array( array( 'type' => E_PARSE ), true ),
			'E_USER_ERROR'        => array( array( 'type' => E_USER_ERROR ), true ),
			'E_COMPILE_ERROR'     => array( array( 'type' => E_COMPILE_ERROR ), true ),
			'E_RECOVERABLE_ERROR' => array( array( 'type' => E_RECOVERABLE_ERROR ), true ),
			'E_WARNING'           => array( array( 'type' => E_WARNING ), false ),
			'E_NOTICE'            => array( array( 'type' => E_NOTICE ), false ),
			'E_CORE_ERROR'        => array( array( 'type' => E_CORE_ERROR ), false ),
			'E_CORE_WARNING'      => array( array( 'type' => E_CORE_WARNING ), false ),
			'E_COMPILE_WARNING'   => array( array( 'type' => E_COMPILE_WARNING ), false ),
			'E_USER_WARNING'      => array( array( 'type' => E_USER_WARNING ), false ),
			'E_USER_NOTICE'       => array( array( 'type' => E_USER_NOTICE ), false ),
			'E_DEPRECATED'        => array( array( 'type' => E_DEPRECATED ), false ),
			'E_USER_DEPRECATED'   => array( array( 'type' => E_USER_DEPRECATED ), false ),
			'numeric string type' => array( array( 'type' => '1' ), false ),
			'zero type'           => array( array( 'type' => 0 ), false ),
			'null type'           => array( array( 'type' => null ), false ),
			'missing type'        => array( array( 'message' => 'Fatal error' ), false ),
			'empty error'         => array( array(), false ),
		);
	}

	/**
	 * Tests that the filter can make other error types handled.
	 *
	 * @ticket 65819
	 *
	 * @covers ::should_handle_error
	 *
	 * @dataProvider data_should_handle_error_filter_values
	 *
	 * @param mixed $filtered Value returned by the filter.
	 * @param bool  $expected Whether the error should be handled.
	 */
	public function test_should_handle_error_uses_filter_for_other_error_types( $filtered, $expected ) {
		$error = array(
			'type'    => E_WARNING,
			'message' => 'A warning',
		);

		$filter = new MockAction();
		add_filter( 'wp_should_handle_php_error', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'wp_should_handle_php_error',
			static function () use ( $filtered ) {
				return $filtered;
			},
			20
		);

		$this->assertSame( $expected, $this->call_handler_method( 'should_handle_error', $error ) );
		$this->assertSame( array( array( false, $error ) ), $filter->get_args(), 'The filter should receive false and the error.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_should_handle_error_filter_values() {
		return array(
			'true'         => array( true, true ),
			'false'        => array( false, false ),
			'truthy value' => array( 1, true ),
			'zero'         => array( 0, false ),
			'zero string'  => array( '0', false ),
			'empty string' => array( '', false ),
			'empty array'  => array( array(), false ),
			'null'         => array( null, false ),
		);
	}

	/**
	 * Tests that the filter is not consulted for error types that are always handled.
	 *
	 * @ticket 65819
	 *
	 * @covers ::should_handle_error
	 */
	public function test_should_handle_error_does_not_filter_fatal_error_types() {
		$filter = new MockAction();
		add_filter( 'wp_should_handle_php_error', array( $filter, 'filter' ) );
		add_filter( 'wp_should_handle_php_error', '__return_false', 20 );

		$this->assertTrue( $this->call_handler_method( 'should_handle_error', array( 'type' => E_ERROR ) ) );
		$this->assertSame( 0, $filter->get_call_count() );
	}

	/**
	 * Tests that detect_error() returns null when no error occurred.
	 *
	 * @ticket 65819
	 *
	 * @covers ::detect_error
	 */
	public function test_detect_error_returns_null_without_error() {
		add_filter( 'wp_should_handle_php_error', '__return_true' );

		$this->assertNull( $this->call_handler_method( 'detect_error' ) );
	}

	/**
	 * Tests that detect_error() returns null when the last error should not be handled.
	 *
	 * @ticket 65819
	 *
	 * @covers ::detect_error
	 */
	public function test_detect_error_returns_null_for_error_that_is_not_handled() {
		$this->raise_last_error();

		$this->assertNull( $this->call_handler_method( 'detect_error' ) );
	}

	/**
	 * Tests that detect_error() returns the last error when it should be handled.
	 *
	 * @ticket 65819
	 *
	 * @covers ::detect_error
	 */
	public function test_detect_error_returns_last_error_that_is_handled() {
		add_filter( 'wp_should_handle_php_error', '__return_true' );

		$this->raise_last_error();

		$error = $this->call_handler_method( 'detect_error' );

		$this->assertIsArray( $error );
		$this->assertSame( E_USER_WARNING, $error['type'] );
		$this->assertSame( 'Test fatal error handler warning', $error['message'] );
		$this->assertSame( __FILE__, $error['file'] );
	}

	/**
	 * Tests that handle() does nothing when no error occurred.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle
	 */
	public function test_handle_does_nothing_without_error() {
		set_current_screen( 'dashboard' );
		add_filter( 'wp_should_handle_php_error', '__return_true' );

		$handler = new WP_Fatal_Error_Handler();
		$handler->handle();

		$this->assertSame( array(), $this->wp_die_calls );
	}

	/**
	 * Tests that handle() does nothing when the last error should not be handled.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle
	 */
	public function test_handle_does_nothing_for_error_that_is_not_handled() {
		set_current_screen( 'dashboard' );

		$this->raise_last_error();

		$handler = new WP_Fatal_Error_Handler();
		$handler->handle();

		$this->assertSame( array(), $this->wp_die_calls );
	}

	/**
	 * Tests that handle() does not display the error template on the front end once headers are sent.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle
	 */
	public function test_handle_does_not_display_template_on_front_end_after_headers_sent() {
		$this->assertTrue( headers_sent(), 'Headers must already be sent for this test.' );

		add_filter( 'wp_should_handle_php_error', '__return_true' );

		$this->raise_last_error();

		$handler = new WP_Fatal_Error_Handler();
		$handler->handle();

		$this->assertSame( array(), $this->wp_die_calls );
	}

	/**
	 * Tests that handle() displays the error template in the admin.
	 *
	 * Runs in a separate process because handle() returns early once another
	 * test has defined the WP_SANDBOX_SCRAPING constant.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle
	 * @covers ::display_error_template
	 * @covers ::display_default_error_template
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_handle_displays_error_template_in_admin() {
		set_current_screen( 'dashboard' );
		$this->set_recovery_mode_state( false, false );

		add_filter( 'wp_should_handle_php_error', '__return_true' );

		$this->raise_last_error();

		$handler = new WP_Fatal_Error_Handler();
		$handler->handle();

		$this->assertCount( 1, $this->wp_die_calls, 'wp_die() should be called once.' );

		list( $wp_error, $title, $args ) = $this->wp_die_calls[0];

		$this->assertWPError( $wp_error );
		$this->assertSame( 'internal_server_error', $wp_error->get_error_code() );
		$this->assertSame(
			'<p>There has been a critical error on this website.</p>' . self::TROUBLESHOOTING_LINK,
			$wp_error->get_error_message()
		);
		$this->assertSame( E_USER_WARNING, $wp_error->get_error_data()['error']['type'] );
		$this->assertSame( 'Test fatal error handler warning', $wp_error->get_error_data()['error']['message'] );
		$this->assertSame( '', $title );
		$this->assertSame(
			array(
				'response' => 500,
				'exit'     => false,
			),
			$args
		);
	}

	/**
	 * Tests that handle() does nothing while scraping for errors in a sandbox.
	 *
	 * @ticket 65819
	 *
	 * @covers ::handle
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_handle_does_nothing_while_sandbox_scraping() {
		define( 'WP_SANDBOX_SCRAPING', true );

		set_current_screen( 'dashboard' );
		add_filter( 'wp_should_handle_php_error', '__return_true' );

		$this->raise_last_error();

		$handler = new WP_Fatal_Error_Handler();
		$handler->handle();

		$this->assertSame( array(), $this->wp_die_calls );
	}

	/**
	 * Tests the message of the default error template.
	 *
	 * @ticket 65819
	 *
	 * @covers ::display_default_error_template
	 *
	 * @dataProvider data_default_error_template_messages
	 *
	 * @param bool   $is_admin       Whether the request is for an admin page.
	 * @param bool   $is_initialized Whether recovery mode is initialized.
	 * @param bool   $is_active      Whether a recovery mode session is active.
	 * @param mixed  $handled        Whether recovery mode handled the error.
	 * @param string $expected       Expected message, without the troubleshooting link.
	 */
	public function test_display_default_error_template_message( $is_admin, $is_initialized, $is_active, $handled, $expected ) {
		if ( $is_admin ) {
			set_current_screen( 'dashboard' );
		}

		$this->set_recovery_mode_state( $is_initialized, $is_active );

		$error = array(
			'type'    => E_ERROR,
			'message' => 'Fatal error',
		);

		$this->call_handler_method( 'display_default_error_template', $error, $handled );

		$this->assertCount( 1, $this->wp_die_calls, 'wp_die() should be called once.' );

		list( $wp_error, $title, $args ) = $this->wp_die_calls[0];

		$this->assertWPError( $wp_error );
		$this->assertSame( 'internal_server_error', $wp_error->get_error_code() );
		$this->assertSame( '<p>' . $expected . '</p>' . self::TROUBLESHOOTING_LINK, $wp_error->get_error_message() );
		$this->assertSame( array( 'error' => $error ), $wp_error->get_error_data() );
		$this->assertSame( '', $title );
		$this->assertSame(
			array(
				'response' => 500,
				'exit'     => false,
			),
			$args
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_default_error_template_messages() {
		$generic  = 'There has been a critical error on this website.';
		$recovery = 'There has been a critical error on this website, putting it in recovery mode. Please check the Themes and Plugins screens for more details. If you just installed or updated a theme or plugin, check the relevant page for that first.';

		if ( is_multisite() ) {
			$protected = 'There has been a critical error on this website. Please reach out to your site administrator, and inform them of this error for further assistance.';
		} else {
			$protected = 'There has been a critical error on this website. Please check your site admin email inbox for instructions. If you continue to have problems, please try the <a href="https://wordpress.org/support/forums/">support forums</a>.';
		}

		return array(
			'front end'                                => array( false, true, false, false, $generic ),
			'front end, handled outside recovery mode' => array( false, true, false, true, $generic ),
			'handled in recovery mode'                 => array( false, true, true, true, $recovery ),
			'handled in recovery mode, in admin'       => array( true, true, true, true, $recovery ),
			'not handled in recovery mode'             => array( false, true, true, false, $generic ),
			'error result in recovery mode'            => array( false, true, true, new WP_Error( 'storage_error' ), $generic ),
			'truthy result that is not true'           => array( false, true, true, 1, $generic ),
			'null result in recovery mode'             => array( false, true, true, null, $generic ),
			'protected endpoint'                       => array( true, true, false, false, $protected ),
			'protected endpoint, email sent'           => array( true, true, false, true, $protected ),
			'protected endpoint, not handled, active'  => array( true, true, true, false, $protected ),
			'protected endpoint, recovery mode not initialized' => array( true, false, false, false, $generic ),
		);
	}

	/**
	 * Tests that the error message and wp_die() arguments are filterable.
	 *
	 * @ticket 65819
	 *
	 * @covers ::display_default_error_template
	 */
	public function test_display_default_error_template_applies_filters() {
		$this->set_recovery_mode_state( false, false );

		$error = array(
			'type'    => E_ERROR,
			'message' => 'Fatal error',
		);

		$message_filter = new MockAction();
		add_filter( 'wp_php_error_message', array( $message_filter, 'filter' ), 10, 2 );
		add_filter(
			'wp_php_error_message',
			static function () {
				return '<p>Custom message</p>';
			},
			20
		);

		$args_filter = new MockAction();
		add_filter( 'wp_php_error_args', array( $args_filter, 'filter' ), 10, 2 );
		add_filter(
			'wp_php_error_args',
			static function ( $args ) {
				$args['response'] = 503;

				return $args;
			},
			20
		);

		$this->call_handler_method( 'display_default_error_template', $error, false );

		$this->assertSame(
			array(
				array(
					'<p>There has been a critical error on this website.</p>' . self::TROUBLESHOOTING_LINK,
					$error,
				),
			),
			$message_filter->get_args(),
			'The message filter should receive the default message and the error.'
		);
		$this->assertSame(
			array(
				array(
					array(
						'response' => 500,
						'exit'     => false,
					),
					$error,
				),
			),
			$args_filter->get_args(),
			'The arguments filter should receive the default arguments and the error.'
		);

		$this->assertCount( 1, $this->wp_die_calls, 'wp_die() should be called once.' );

		list( $wp_error, , $args ) = $this->wp_die_calls[0];

		$this->assertSame( '<p>Custom message</p>', $wp_error->get_error_message() );
		$this->assertSame(
			array(
				'response' => 503,
				'exit'     => false,
			),
			$args
		);
	}
}
