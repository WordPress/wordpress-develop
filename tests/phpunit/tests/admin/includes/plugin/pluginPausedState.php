<?php

/**
 * Tests for is_plugin_paused(), wp_get_plugin_error(), and resume_plugin().
 *
 * @group admin
 * @group plugins
 * @group error-protection
 */
class Tests_Admin_Includes_Plugin_PluginPausedState extends WP_UnitTestCase {

	const TEST_SESSION_ID = 'test_recovery_session_123';

	/**
	 * Original $_paused_plugins global value.
	 *
	 * @var array|null
	 */
	private $orig_paused_plugins;

	/**
	 * Sets up the environment before each test.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->orig_paused_plugins = isset( $GLOBALS['_paused_plugins'] ) ? $GLOBALS['_paused_plugins'] : null;

		$GLOBALS['_paused_plugins'] = array();
	}

	/**
	 * Cleans up the environment after each test.
	 */
	public function tear_down() {
		if ( null !== $this->orig_paused_plugins ) {
			$GLOBALS['_paused_plugins'] = $this->orig_paused_plugins;
		} else {
			unset( $GLOBALS['_paused_plugins'] );
		}

		delete_option( self::TEST_SESSION_ID . '_paused_extensions' );
		$this->set_recovery_mode_state( false, '' );

		parent::tear_down();
	}

	/**
	 * Sets WP_Recovery_Mode protected state using reflection.
	 *
	 * @param bool   $is_active  Whether recovery mode is active.
	 * @param string $session_id Recovery mode session ID.
	 */
	private function set_recovery_mode_state( $is_active, $session_id ) {
		$recovery_mode = wp_recovery_mode();

		$active_prop = new ReflectionProperty( $recovery_mode, 'is_active' );
		if ( PHP_VERSION_ID < 80100 ) {
			$active_prop->setAccessible( true );
		}
		$active_prop->setValue( $recovery_mode, $is_active );

		$session_prop = new ReflectionProperty( $recovery_mode, 'session_id' );
		if ( PHP_VERSION_ID < 80100 ) {
			$session_prop->setAccessible( true );
		}
		$session_prop->setValue( $recovery_mode, $session_id );
	}

	/**
	 * Tests that is_plugin_paused() returns false when $_paused_plugins global is not set.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_plugin_paused
	 */
	public function test_is_plugin_paused_returns_false_when_global_not_set() {
		unset( $GLOBALS['_paused_plugins'] );

		$this->assertFalse( is_plugin_paused( 'test-plugin/test-plugin.php' ) );
	}

	/**
	 * Tests that is_plugin_paused() returns false when the plugin is not active.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_plugin_paused
	 */
	public function test_is_plugin_paused_returns_false_when_plugin_inactive() {
		$plugin = 'test-plugin/test-plugin.php';

		$GLOBALS['_paused_plugins'] = array(
			'test-plugin' => array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
			),
		);

		update_option( 'active_plugins', array() );

		$this->assertFalse( is_plugin_paused( $plugin ), 'is_plugin_paused() should return false when plugin is not active.' );
	}

	/**
	 * Tests that is_plugin_paused() returns false when the plugin is active but not in the paused list.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_plugin_paused
	 */
	public function test_is_plugin_paused_returns_false_when_active_but_not_paused() {
		$plugin = 'active-plugin/active-plugin.php';
		update_option( 'active_plugins', array( $plugin ) );

		$GLOBALS['_paused_plugins'] = array(
			'other-plugin' => array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
			),
		);

		$this->assertFalse( is_plugin_paused( $plugin ) );
	}

	/**
	 * Tests that is_plugin_paused() returns true when the plugin is active and paused.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_plugin_paused
	 */
	public function test_is_plugin_paused_returns_true_when_active_and_paused() {
		$plugin = 'broken-plugin/broken-plugin.php';
		update_option( 'active_plugins', array( $plugin ) );

		$GLOBALS['_paused_plugins'] = array(
			'broken-plugin' => array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
			),
		);

		$this->assertTrue( is_plugin_paused( $plugin ) );
	}

	/**
	 * Tests that is_plugin_paused() handles single-file plugins without a directory.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_plugin_paused
	 */
	public function test_is_plugin_paused_handles_single_file_plugin() {
		$plugin = 'single-file-plugin.php';
		update_option( 'active_plugins', array( $plugin ) );

		$GLOBALS['_paused_plugins'] = array(
			'single-file-plugin.php' => array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
			),
		);

		$this->assertTrue( is_plugin_paused( $plugin ) );
	}

	/**
	 * Tests that wp_get_plugin_error() returns false when $_paused_plugins global is not set.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_get_plugin_error
	 */
	public function test_wp_get_plugin_error_returns_false_when_global_not_set() {
		unset( $GLOBALS['_paused_plugins'] );

		$this->assertFalse( wp_get_plugin_error( 'test-plugin/test-plugin.php' ) );
	}

	/**
	 * Tests that wp_get_plugin_error() returns false when plugin has no recorded error.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_get_plugin_error
	 */
	public function test_wp_get_plugin_error_returns_false_when_not_paused() {
		$GLOBALS['_paused_plugins'] = array(
			'other-plugin' => array(
				'type'    => E_ERROR,
				'message' => 'Fatal error',
			),
		);

		$this->assertFalse( wp_get_plugin_error( 'unrecorded-plugin/unrecorded-plugin.php' ) );
	}

	/**
	 * Tests that wp_get_plugin_error() returns the recorded error array.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_get_plugin_error
	 */
	public function test_wp_get_plugin_error_returns_recorded_error() {
		$error = array(
			'type'    => E_ERROR,
			'message' => 'Call to undefined function fatal_function()',
			'file'    => '/wp-content/plugins/failing-plugin/failing.php',
			'line'    => 25,
		);

		$GLOBALS['_paused_plugins'] = array(
			'failing-plugin' => $error,
		);

		$this->assertSame( $error, wp_get_plugin_error( 'failing-plugin/failing.php' ) );
	}

	/**
	 * Tests that resume_plugin() successfully deletes paused state and returns true.
	 *
	 * @ticket 65819
	 *
	 * @covers ::resume_plugin
	 */
	public function test_resume_plugin_success() {
		$this->set_recovery_mode_state( true, self::TEST_SESSION_ID );

		$error = array(
			'type'    => E_ERROR,
			'message' => 'Fatal error occurred',
			'file'    => '/wp-content/plugins/sample-plugin/sample.php',
			'line'    => 10,
		);

		$this->assertTrue( wp_paused_plugins()->set( 'sample-plugin', $error ) );
		$this->assertArrayHasKey( 'sample-plugin', wp_paused_plugins()->get_all() );

		$result = resume_plugin( 'sample-plugin/sample.php' );

		$this->assertTrue( $result, 'resume_plugin() should return true upon successful removal from paused list.' );
		$this->assertArrayNotHasKey( 'sample-plugin', wp_paused_plugins()->get_all() );
	}

	/**
	 * Tests that resume_plugin() returns WP_Error when deletion fails.
	 *
	 * @ticket 65819
	 *
	 * @covers ::resume_plugin
	 */
	public function test_resume_plugin_returns_wp_error_when_deletion_fails() {
		// When recovery mode is inactive, storage has no active session option, causing delete() to return false.
		$this->set_recovery_mode_state( false, '' );

		$result = resume_plugin( 'non-paused-plugin/non-paused-plugin.php' );

		$this->assertWPError( $result );
		$this->assertSame( 'could_not_resume_plugin', $result->get_error_code() );
	}
}
