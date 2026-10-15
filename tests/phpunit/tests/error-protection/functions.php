<?php

/**
 * Tests for the error protection functions.
 *
 * @group error-protection
 */
class Tests_Error_Protection_Functions extends WP_UnitTestCase {

	const TEST_SESSION_ID = 'test_error_protection_session';

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

	public function set_up() {
		parent::set_up();

		$this->orig_is_active  = $this->get_recovery_mode_property( 'is_active' );
		$this->orig_session_id = $this->get_recovery_mode_property( 'session_id' );
	}

	public function tear_down() {
		$this->set_recovery_mode_property( 'is_active', $this->orig_is_active );
		$this->set_recovery_mode_property( 'session_id', $this->orig_session_id );

		parent::tear_down();
	}

	/**
	 * Gets a private property of the recovery mode singleton.
	 *
	 * @param string $property Property name.
	 * @return mixed Property value.
	 */
	private function get_recovery_mode_property( $property ) {
		$reflection = new ReflectionProperty( wp_recovery_mode(), $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->getValue( wp_recovery_mode() );
	}

	/**
	 * Sets a private property of the recovery mode singleton.
	 *
	 * @param string $property Property name.
	 * @param mixed  $value    Property value.
	 */
	private function set_recovery_mode_property( $property, $value ) {
		$reflection = new ReflectionProperty( wp_recovery_mode(), $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$reflection->setValue( wp_recovery_mode(), $value );
	}

	/**
	 * Puts the recovery mode singleton into an active session.
	 */
	private function activate_recovery_mode() {
		$this->set_recovery_mode_property( 'is_active', true );
		$this->set_recovery_mode_property( 'session_id', self::TEST_SESSION_ID );
	}

	/**
	 * Tests that wp_paused_plugins() always returns the same storage instance.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_paused_plugins
	 */
	public function test_wp_paused_plugins_returns_same_storage_instance() {
		$storage = wp_paused_plugins();

		$this->assertInstanceOf( 'WP_Paused_Extensions_Storage', $storage );
		$this->assertSame( $storage, wp_paused_plugins() );
	}

	/**
	 * Tests that wp_paused_themes() always returns the same storage instance.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_paused_themes
	 */
	public function test_wp_paused_themes_returns_same_storage_instance() {
		$storage = wp_paused_themes();

		$this->assertInstanceOf( 'WP_Paused_Extensions_Storage', $storage );
		$this->assertSame( $storage, wp_paused_themes() );
	}

	/**
	 * Tests that plugins and themes use separate storage instances.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_paused_plugins
	 * @covers ::wp_paused_themes
	 */
	public function test_wp_paused_plugins_and_themes_use_separate_storage() {
		$this->assertNotSame( wp_paused_plugins(), wp_paused_themes() );
	}

	/**
	 * Tests that wp_paused_plugins() stores errors under the plugin type.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_paused_plugins
	 */
	public function test_wp_paused_plugins_stores_errors_as_plugin_type() {
		$this->activate_recovery_mode();

		$error = array( 'message' => 'Plugin error' );

		wp_paused_plugins()->set( 'my-extension', $error );

		$this->assertSame(
			array( 'plugin' => array( 'my-extension' => $error ) ),
			get_option( self::TEST_SESSION_ID . '_paused_extensions' )
		);
	}

	/**
	 * Tests that wp_paused_themes() stores errors under the theme type.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_paused_themes
	 */
	public function test_wp_paused_themes_stores_errors_as_theme_type() {
		$this->activate_recovery_mode();

		$error = array( 'message' => 'Theme error' );

		wp_paused_themes()->set( 'my-extension', $error );

		$this->assertSame(
			array( 'theme' => array( 'my-extension' => $error ) ),
			get_option( self::TEST_SESSION_ID . '_paused_extensions' )
		);
	}

	/**
	 * Tests that wp_recovery_mode() always returns the same instance.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_recovery_mode
	 */
	public function test_wp_recovery_mode_returns_same_instance() {
		$recovery_mode = wp_recovery_mode();

		$this->assertInstanceOf( 'WP_Recovery_Mode', $recovery_mode );
		$this->assertSame( $recovery_mode, wp_recovery_mode() );
	}

	/**
	 * Tests the error description for different error types.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_get_extension_error_description
	 *
	 * @dataProvider data_wp_get_extension_error_description
	 *
	 * @param mixed  $type          Error type.
	 * @param string $expected_type Error type shown in the description.
	 */
	public function test_wp_get_extension_error_description( $type, $expected_type ) {
		$error = array(
			'type'    => $type,
			'line'    => 12,
			'file'    => '/srv/www/wp-content/plugins/my-plugin/my-plugin.php',
			'message' => 'Call to undefined function my_plugin_missing()',
		);

		$this->assertSame(
			"An error of type <code>{$expected_type}</code> was caused in line <code>12</code> of the file <code>/srv/www/wp-content/plugins/my-plugin/my-plugin.php</code>. Error message: <code>Call to undefined function my_plugin_missing()</code>",
			wp_get_extension_error_description( $error )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_get_extension_error_description() {
		return array(
			'E_ERROR'             => array( E_ERROR, 'E_ERROR' ),
			'E_WARNING'           => array( E_WARNING, 'E_WARNING' ),
			'E_PARSE'             => array( E_PARSE, 'E_PARSE' ),
			'E_NOTICE'            => array( E_NOTICE, 'E_NOTICE' ),
			'E_CORE_ERROR'        => array( E_CORE_ERROR, 'E_CORE_ERROR' ),
			'E_COMPILE_ERROR'     => array( E_COMPILE_ERROR, 'E_COMPILE_ERROR' ),
			'E_USER_ERROR'        => array( E_USER_ERROR, 'E_USER_ERROR' ),
			'E_RECOVERABLE_ERROR' => array( E_RECOVERABLE_ERROR, 'E_RECOVERABLE_ERROR' ),
			'E_DEPRECATED'        => array( E_DEPRECATED, 'E_DEPRECATED' ),
			'unknown integer'     => array( 3, '3' ),
			'zero'                => array( 0, '0' ),
			'unknown string'      => array( 'custom_error', 'custom_error' ),
			'empty string'        => array( '', '' ),
		);
	}

	/**
	 * Tests that the fatal error handler is enabled by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_is_fatal_error_handler_enabled
	 */
	public function test_wp_is_fatal_error_handler_enabled_by_default() {
		$filter = new MockAction();
		add_filter( 'wp_fatal_error_handler_enabled', array( $filter, 'filter' ) );

		$this->assertTrue( wp_is_fatal_error_handler_enabled() );
		$this->assertSame( array( array( true ) ), $filter->get_args(), 'The filter should receive the default value.' );
	}

	/**
	 * Tests that the fatal error handler can be disabled with the filter.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_is_fatal_error_handler_enabled
	 */
	public function test_wp_is_fatal_error_handler_enabled_can_be_disabled_by_filter() {
		add_filter( 'wp_fatal_error_handler_enabled', '__return_false' );

		$this->assertFalse( wp_is_fatal_error_handler_enabled() );
	}

	/**
	 * Tests the effect of the WP_DISABLE_FATAL_ERROR_HANDLER constant.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_is_fatal_error_handler_enabled
	 *
	 * @dataProvider data_disable_fatal_error_handler_constant
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param bool $constant Value of the constant.
	 * @param bool $expected Whether the handler is expected to be enabled.
	 */
	public function test_wp_is_fatal_error_handler_enabled_respects_constant( $constant, $expected ) {
		define( 'WP_DISABLE_FATAL_ERROR_HANDLER', $constant );

		$filter = new MockAction();
		add_filter( 'wp_fatal_error_handler_enabled', array( $filter, 'filter' ) );

		$this->assertSame( $expected, wp_is_fatal_error_handler_enabled() );
		$this->assertSame( array( array( $expected ) ), $filter->get_args(), 'The filter should receive the value from the constant.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_disable_fatal_error_handler_constant() {
		return array(
			'constant is true'  => array( true, false ),
			'constant is false' => array( false, true ),
		);
	}

	/**
	 * Tests that the filter can enable the handler when the constant disables it.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_is_fatal_error_handler_enabled
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wp_is_fatal_error_handler_enabled_filter_overrides_constant() {
		define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );

		add_filter( 'wp_fatal_error_handler_enabled', '__return_true' );

		$this->assertTrue( wp_is_fatal_error_handler_enabled() );
	}
}
