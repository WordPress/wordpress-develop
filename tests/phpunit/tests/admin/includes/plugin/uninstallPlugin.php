<?php

/**
 * Tests for uninstall_plugin() and is_uninstallable_plugin().
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_Includes_Plugin_UninstallPlugin extends WP_UnitTestCase {

	const FIXTURE_PLUGIN = 'wp-tests-uninstall-plugin/wp-tests-uninstall-plugin.php';

	/**
	 * Files created by the test, to be removed on tear down.
	 *
	 * @var string[]
	 */
	private $created_files = array();

	/**
	 * Directories created by the test, to be removed on tear down.
	 *
	 * @var string[]
	 */
	private $created_directories = array();

	/**
	 * Number of times the uninstall callback has run.
	 *
	 * @var int
	 */
	public static $uninstall_callback_count = 0;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		self::$uninstall_callback_count = 0;
	}

	public function tear_down() {
		foreach ( $this->created_files as $file ) {
			unlink( $file );
		}

		foreach ( $this->created_directories as $directory ) {
			rmdir( $directory );
		}

		parent::tear_down();
	}

	/**
	 * Uninstall callback registered for a plugin.
	 */
	public static function uninstall_callback() {
		++self::$uninstall_callback_count;
	}

	/**
	 * Creates a plugin in its own directory, with an uninstall.php file.
	 */
	private function create_plugin_with_uninstall_file() {
		$directory = WP_PLUGIN_DIR . '/' . dirname( self::FIXTURE_PLUGIN );

		$this->assertDirectoryDoesNotExist( $directory, 'The fixture plugin directory should not exist before the test.' );
		$this->assertTrue( mkdir( $directory ), 'The fixture plugin directory should be created.' );
		$this->created_directories[] = $directory;

		$this->create_file( WP_PLUGIN_DIR . '/' . self::FIXTURE_PLUGIN, "<?php\n/*\nPlugin Name: WP Tests Uninstall Plugin\n*/\n" );
		$this->create_file( $directory . '/uninstall.php', "<?php\nupdate_option( 'wp_tests_uninstall_file_ran', WP_UNINSTALL_PLUGIN );\n" );
	}

	/**
	 * Creates a file and registers it for removal.
	 *
	 * @param string $file     File path.
	 * @param string $contents File contents.
	 */
	private function create_file( $file, $contents ) {
		$this->assertFileDoesNotExist( $file, 'The fixture file should not exist before the test.' );
		$this->assertNotFalse( file_put_contents( $file, $contents ), 'The fixture file should be written.' );
		$this->created_files[] = $file;
	}

	/**
	 * Tests that uninstall_plugin() runs the registered uninstall callback once.
	 *
	 * @ticket 65819
	 *
	 * @covers ::uninstall_plugin
	 */
	public function test_uninstall_plugin_runs_registered_callback() {
		$other_callback = array( __CLASS__, 'other_callback' );

		update_option(
			'uninstall_plugins',
			array(
				'hello.php'              => array( __CLASS__, 'uninstall_callback' ),
				'other-plugin/other.php' => $other_callback,
			)
		);

		$this->assertNull( uninstall_plugin( 'hello.php' ) );
		$this->assertSame( 1, self::$uninstall_callback_count, 'The uninstall callback should run once.' );
		$this->assertSame(
			array( 'other-plugin/other.php' => $other_callback ),
			get_option( 'uninstall_plugins' ),
			'Only the uninstalled plugin should be removed from the registered uninstall callbacks.'
		);
	}

	/**
	 * Tests that uninstall_plugin() accepts a full path to the plugin file.
	 *
	 * @ticket 65819
	 *
	 * @covers ::uninstall_plugin
	 */
	public function test_uninstall_plugin_accepts_full_plugin_path() {
		update_option( 'uninstall_plugins', array( 'hello.php' => array( __CLASS__, 'uninstall_callback' ) ) );

		$this->assertNull( uninstall_plugin( WP_PLUGIN_DIR . '/hello.php' ) );
		$this->assertSame( 1, self::$uninstall_callback_count, 'The uninstall callback should run once.' );
		$this->assertSame( array(), get_option( 'uninstall_plugins' ) );
	}

	/**
	 * Tests that uninstall_plugin() does nothing for a plugin without an uninstall routine.
	 *
	 * @ticket 65819
	 *
	 * @covers ::uninstall_plugin
	 */
	public function test_uninstall_plugin_does_nothing_without_uninstall_routine() {
		$uninstallable_plugins = array( 'other-plugin/other.php' => array( __CLASS__, 'uninstall_callback' ) );

		update_option( 'uninstall_plugins', $uninstallable_plugins );

		$uninstall_action = new MockAction();
		add_action( 'uninstall_hello.php', array( $uninstall_action, 'action' ) );

		$this->assertNull( uninstall_plugin( 'hello.php' ) );
		$this->assertSame( 0, self::$uninstall_callback_count, 'No uninstall callback should run.' );
		$this->assertSame( 0, $uninstall_action->get_call_count(), 'The uninstall action should not fire.' );
		$this->assertSame( $uninstallable_plugins, get_option( 'uninstall_plugins' ), 'The registered uninstall callbacks should be kept.' );
	}

	/**
	 * Tests that the pre_uninstall_plugin action fires with the plugin and the registered uninstall callbacks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::uninstall_plugin
	 *
	 * @dataProvider data_plugins_for_pre_uninstall_action
	 *
	 * @param string $plugin Plugin passed to uninstall_plugin().
	 */
	public function test_uninstall_plugin_fires_pre_uninstall_action( $plugin ) {
		$uninstallable_plugins = array( 'hello.php' => array( __CLASS__, 'uninstall_callback' ) );

		update_option( 'uninstall_plugins', $uninstallable_plugins );

		$action = new MockAction();
		add_action( 'pre_uninstall_plugin', array( $action, 'action' ), 10, 2 );

		uninstall_plugin( $plugin );

		$this->assertSame( array( array( $plugin, $uninstallable_plugins ) ), $action->get_args() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugins_for_pre_uninstall_action() {
		return array(
			'plugin with an uninstall callback'   => array( 'hello.php' ),
			'plugin without an uninstall routine' => array( 'other-plugin/other.php' ),
		);
	}

	/**
	 * Tests that uninstall_plugin() loads the plugin's uninstall.php file instead of running the registered callback.
	 *
	 * @ticket 65819
	 *
	 * @covers ::uninstall_plugin
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_uninstall_plugin_loads_uninstall_file() {
		$this->create_plugin_with_uninstall_file();

		$other_callback = array( __CLASS__, 'other_callback' );

		update_option(
			'uninstall_plugins',
			array(
				self::FIXTURE_PLUGIN     => array( __CLASS__, 'uninstall_callback' ),
				'other-plugin/other.php' => $other_callback,
			)
		);

		$this->assertTrue( uninstall_plugin( self::FIXTURE_PLUGIN ) );
		$this->assertSame( self::FIXTURE_PLUGIN, WP_UNINSTALL_PLUGIN, 'The WP_UNINSTALL_PLUGIN constant should hold the plugin file.' );
		$this->assertSame( self::FIXTURE_PLUGIN, get_option( 'wp_tests_uninstall_file_ran' ), 'The uninstall.php file should be loaded.' );
		$this->assertSame( 0, self::$uninstall_callback_count, 'The registered uninstall callback should not run.' );
		$this->assertSame(
			array( 'other-plugin/other.php' => $other_callback ),
			get_option( 'uninstall_plugins' ),
			'Only the uninstalled plugin should be removed from the registered uninstall callbacks.'
		);
	}

	/**
	 * Tests that a plugin with an uninstall.php file is uninstallable without a registered callback.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_uninstallable_plugin
	 */
	public function test_is_uninstallable_plugin_returns_true_for_plugin_with_uninstall_file() {
		$this->create_plugin_with_uninstall_file();

		delete_option( 'uninstall_plugins' );

		$this->assertTrue( is_uninstallable_plugin( self::FIXTURE_PLUGIN ), 'The plugin file should be uninstallable.' );
		$this->assertTrue( is_uninstallable_plugin( WP_PLUGIN_DIR . '/' . self::FIXTURE_PLUGIN ), 'The full plugin path should be uninstallable.' );
	}

	/**
	 * Tests that only plugins with a registered uninstall callback are uninstallable.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_uninstallable_plugin
	 *
	 * @dataProvider data_is_uninstallable_plugin_with_registered_callback
	 *
	 * @param string $plugin   Plugin to check.
	 * @param bool   $expected Whether the plugin is uninstallable.
	 */
	public function test_is_uninstallable_plugin_with_registered_callback( $plugin, $expected ) {
		update_option( 'uninstall_plugins', array( 'my-plugin/my-plugin.php' => array( __CLASS__, 'uninstall_callback' ) ) );

		$this->assertSame( $expected, is_uninstallable_plugin( $plugin ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_is_uninstallable_plugin_with_registered_callback() {
		return array(
			'registered plugin'           => array( 'my-plugin/my-plugin.php', true ),
			'other file in the directory' => array( 'my-plugin/other.php', false ),
			'unregistered plugin'         => array( 'other-plugin/other.php', false ),
			'single-file plugin'          => array( 'hello.php', false ),
		);
	}
}
