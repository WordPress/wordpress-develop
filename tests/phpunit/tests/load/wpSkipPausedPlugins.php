<?php

/**
 * Tests for wp_skip_paused_plugins().
 *
 * @group load
 * @group error-protection
 *
 * @covers ::wp_skip_paused_plugins
 */
class Tests_Load_WpSkipPausedPlugins extends WP_UnitTestCase {

	const TEST_SESSION_ID = 'test_skip_paused_plugins_session';

	/**
	 * Original $_paused_plugins global.
	 *
	 * @var array|null
	 */
	private $original_paused_plugins;

	/**
	 * Original recovery mode is_active property.
	 *
	 * @var bool
	 */
	private $original_is_active;

	/**
	 * Original recovery mode session_id property.
	 *
	 * @var string
	 */
	private $original_session_id;

	public function set_up() {
		parent::set_up();

		$this->original_paused_plugins = $GLOBALS['_paused_plugins'] ?? null;
		$this->original_is_active      = $this->get_recovery_mode_property( 'is_active' );
		$this->original_session_id     = $this->get_recovery_mode_property( 'session_id' );

		unset( $GLOBALS['_paused_plugins'] );
	}

	public function tear_down() {
		if ( null !== $this->original_paused_plugins ) {
			$GLOBALS['_paused_plugins'] = $this->original_paused_plugins;
		} else {
			unset( $GLOBALS['_paused_plugins'] );
		}

		delete_option( self::TEST_SESSION_ID . '_paused_extensions' );
		$this->set_recovery_mode_state( $this->original_is_active, $this->original_session_id );

		parent::tear_down();
	}

	/**
	 * Gets a WP_Recovery_Mode property value.
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
	 * Sets WP_Recovery_Mode protected state.
	 *
	 * @param bool   $is_active  Whether recovery mode is active.
	 * @param string $session_id Recovery mode session ID.
	 */
	private function set_recovery_mode_state( $is_active, $session_id ) {
		$recovery_mode = wp_recovery_mode();

		$is_active_property = new ReflectionProperty( $recovery_mode, 'is_active' );
		if ( PHP_VERSION_ID < 80100 ) {
			$is_active_property->setAccessible( true );
		}
		$is_active_property->setValue( $recovery_mode, $is_active );

		$session_id_property = new ReflectionProperty( $recovery_mode, 'session_id' );
		if ( PHP_VERSION_ID < 80100 ) {
			$session_id_property->setAccessible( true );
		}
		$session_id_property->setValue( $recovery_mode, $session_id );
	}

	/**
	 * Pauses a plugin in recovery mode.
	 *
	 * @param string $plugin Plugin directory or file name.
	 * @param array  $error  Recorded error.
	 */
	private function pause_plugin( $plugin, $error ) {
		$this->set_recovery_mode_state( true, self::TEST_SESSION_ID );

		$this->assertTrue( wp_paused_plugins()->set( $plugin, $error ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_plugins_unchanged_when_none_are_paused() {
		$plugins = array(
			WP_PLUGIN_DIR . '/hello.php',
			WP_PLUGIN_DIR . '/akismet/akismet.php',
		);

		$this->assertSame( $plugins, wp_skip_paused_plugins( $plugins ) );
		$this->assertArrayNotHasKey( '_paused_plugins', $GLOBALS );
	}

	/**
	 * @ticket 65819
	 */
	public function test_removes_paused_plugins_and_stores_them_for_notices() {
		$error = array(
			'type'    => E_ERROR,
			'file'    => WP_PLUGIN_DIR . '/akismet/akismet.php',
			'line'    => 10,
			'message' => 'Fatal error',
		);

		$this->pause_plugin( 'akismet', $error );

		$hello   = WP_PLUGIN_DIR . '/hello.php';
		$akismet = WP_PLUGIN_DIR . '/akismet/akismet.php';
		$result  = wp_skip_paused_plugins( array( $hello, $akismet ) );

		$this->assertSame( array( $hello ), $result );
		$this->assertSame( array( 'akismet' => $error ), $GLOBALS['_paused_plugins'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_matches_single_file_plugins() {
		$error = array(
			'type'    => E_ERROR,
			'file'    => WP_PLUGIN_DIR . '/hello.php',
			'line'    => 1,
			'message' => 'Fatal error',
		);

		$this->pause_plugin( 'hello.php', $error );

		$hello   = WP_PLUGIN_DIR . '/hello.php';
		$akismet = WP_PLUGIN_DIR . '/akismet/akismet.php';
		$result  = wp_skip_paused_plugins( array( $hello, $akismet ) );

		$this->assertSame( array( 1 => $akismet ), $result );
		$this->assertSame( array( 'hello.php' => $error ), $GLOBALS['_paused_plugins'] );
	}
}
