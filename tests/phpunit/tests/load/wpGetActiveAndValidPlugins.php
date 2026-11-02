<?php

/**
 * Tests for wp_get_active_and_valid_plugins().
 *
 * @group load
 * @group plugins
 *
 * @covers ::wp_get_active_and_valid_plugins
 */
class Tests_Load_WpGetActiveAndValidPlugins extends WP_UnitTestCase {

	const TEST_SESSION_ID = 'test_active_valid_plugins_session';

	/**
	 * Created plugin files to clean up.
	 *
	 * @var string[]
	 */
	private $created_files = array();

	/**
	 * Created directories to clean up.
	 *
	 * @var string[]
	 */
	private $created_dirs = array();

	/**
	 * Original active_plugins option.
	 *
	 * @var mixed
	 */
	private $original_active_plugins;

	/**
	 * Original installing status.
	 *
	 * @var bool
	 */
	private $original_installing;

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

	/**
	 * Original $_paused_plugins global.
	 *
	 * @var array|null
	 */
	private $original_paused_plugins;

	public function set_up() {
		parent::set_up();

		$this->original_active_plugins = get_option( 'active_plugins' );
		$this->original_installing     = wp_installing();
		$this->original_is_active      = $this->get_recovery_mode_property( 'is_active' );
		$this->original_session_id     = $this->get_recovery_mode_property( 'session_id' );
		$this->original_paused_plugins = $GLOBALS['_paused_plugins'] ?? null;

		unset( $GLOBALS['_paused_plugins'] );
		wp_installing( false );
		update_option( 'active_plugins', array() );
	}

	public function tear_down() {
		foreach ( $this->created_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		foreach ( array_reverse( $this->created_dirs ) as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}

		$this->created_files = array();
		$this->created_dirs  = array();

		update_option( 'active_plugins', $this->original_active_plugins );
		wp_installing( $this->original_installing );
		$this->set_recovery_mode_state( $this->original_is_active, $this->original_session_id );
		delete_option( self::TEST_SESSION_ID . '_paused_extensions' );

		if ( null !== $this->original_paused_plugins ) {
			$GLOBALS['_paused_plugins'] = $this->original_paused_plugins;
		} else {
			unset( $GLOBALS['_paused_plugins'] );
		}

		parent::tear_down();
	}

	/**
	 * Creates a plugin fixture under WP_PLUGIN_DIR.
	 *
	 * @param string $relative_path Path relative to WP_PLUGIN_DIR.
	 * @return string Relative plugin path.
	 */
	private function create_plugin_file( $relative_path ) {
		$full_path = WP_PLUGIN_DIR . '/' . $relative_path;
		$dir       = dirname( $full_path );

		if ( ! is_dir( $dir ) && WP_PLUGIN_DIR !== $dir ) {
			$this->assertTrue( wp_mkdir_p( $dir ), "Failed to create directory: {$dir}" );
			$this->created_dirs[] = $dir;
		}

		$this->assertNotFalse(
			file_put_contents( $full_path, "<?php\n/**\n * Plugin Name: Fixture\n */\n" ),
			"Failed to write plugin fixture: {$full_path}"
		);
		$this->created_files[] = $full_path;

		return $relative_path;
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
	 * @ticket 65819
	 */
	public function test_returns_empty_array_when_no_active_plugins() {
		$this->assertSame( array(), wp_get_active_and_valid_plugins() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_array_while_installing() {
		$plugin = $this->create_plugin_file( '65819-active-valid.php' );
		update_option( 'active_plugins', array( $plugin ) );
		wp_installing( true );

		$this->assertSame( array(), wp_get_active_and_valid_plugins() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_absolute_paths_for_valid_active_plugins() {
		$single = $this->create_plugin_file( '65819-single-plugin.php' );
		$nested = $this->create_plugin_file( '65819-nested-plugin/plugin.php' );

		update_option( 'active_plugins', array( $single, $nested ) );

		$this->assertSame(
			array(
				WP_PLUGIN_DIR . '/' . $single,
				WP_PLUGIN_DIR . '/' . $nested,
			),
			wp_get_active_and_valid_plugins()
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_skips_missing_and_invalid_plugin_entries() {
		$valid   = $this->create_plugin_file( '65819-valid-plugin/plugin.php' );
		$non_php = $this->create_plugin_file( '65819-not-a-php-file.txt' );

		update_option(
			'active_plugins',
			array(
				$valid,
				'65819-missing-plugin.php',
				$non_php,
				'65819-valid-plugin/../' . $valid,
			)
		);

		$this->assertSame(
			array( WP_PLUGIN_DIR . '/' . $valid ),
			wp_get_active_and_valid_plugins()
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_skips_paused_plugins_in_recovery_mode() {
		$kept   = $this->create_plugin_file( '65819-kept-plugin.php' );
		$paused = $this->create_plugin_file( '65819-paused-plugin/plugin.php' );
		$error  = array(
			'type'    => E_ERROR,
			'file'    => WP_PLUGIN_DIR . '/65819-paused-plugin/plugin.php',
			'line'    => 1,
			'message' => 'Fatal error',
		);

		update_option( 'active_plugins', array( $kept, $paused ) );

		$this->set_recovery_mode_state( true, self::TEST_SESSION_ID );
		$this->assertTrue( wp_paused_plugins()->set( '65819-paused-plugin', $error ) );

		$this->assertSame(
			array( WP_PLUGIN_DIR . '/' . $kept ),
			wp_get_active_and_valid_plugins()
		);
	}
}
