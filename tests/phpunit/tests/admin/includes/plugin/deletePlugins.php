<?php

/**
 * Tests for delete_plugins().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::delete_plugins
 */
class Tests_Admin_Includes_Plugin_DeletePlugins extends WP_UnitTestCase {

	/**
	 * Number used to give each test plugin a unique slug, since plugin files are included once.
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * Uninstall callbacks that ran, by plugin file.
	 *
	 * @var string[]
	 */
	public static $uninstalled = array();

	/**
	 * The paths created by the current test, removed in tear_down() if they still exist.
	 *
	 * @var string[]
	 */
	private $created = array();

	/**
	 * Whether the wp_filesystem global was set before the test.
	 *
	 * @var bool
	 */
	private $had_filesystem;

	/**
	 * The original wp_filesystem global.
	 *
	 * @var mixed
	 */
	private $original_filesystem;

	/**
	 * Saves the filesystem global.
	 */
	public function set_up() {
		parent::set_up();

		$this->had_filesystem      = array_key_exists( 'wp_filesystem', $GLOBALS );
		$this->original_filesystem = $this->had_filesystem ? $GLOBALS['wp_filesystem'] : null;

		add_filter(
			'filesystem_method',
			static function () {
				return 'direct';
			}
		);

		self::$uninstalled = array();
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Removes what the test created and restores the filesystem global.
	 */
	public function tear_down() {
		foreach ( array_reverse( $this->created ) as $path ) {
			$this->remove_path( $path );
		}

		if ( $this->had_filesystem ) {
			$GLOBALS['wp_filesystem'] = $this->original_filesystem;
		} else {
			unset( $GLOBALS['wp_filesystem'] );
		}

		wp_cache_delete( 'plugins', 'plugins' );
		delete_site_transient( 'update_plugins' );

		parent::tear_down();
	}

	/**
	 * Tests that nothing is deleted without plugins.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_no_plugins
	 *
	 * @param mixed $plugins The plugins to delete.
	 */
	public function test_should_return_false_without_plugins( $plugins ) {
		$this->assertFalse( delete_plugins( $plugins ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_no_plugins() {
		return array(
			'empty array' => array( array() ),
			'null'        => array( null ),
		);
	}

	/**
	 * Tests that a plugin directory is deleted and the delete hooks fire.
	 *
	 * @ticket 65819
	 */
	public function test_should_delete_plugin_directory_and_fire_hooks() {
		$plugin = $this->create_plugin_directory();

		$delete  = new MockAction();
		$deleted = new MockAction();
		add_action( 'delete_plugin', array( $delete, 'action' ) );
		add_action( 'deleted_plugin', array( $deleted, 'action' ), 10, 2 );

		$this->assertTrue( delete_plugins( array( $plugin ) ), 'The plugin should be deleted.' );
		$this->assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . dirname( $plugin ), 'The plugin directory should be removed.' );
		$this->assertSame( array( array( $plugin ) ), $delete->get_args(), 'delete_plugin should receive the plugin.' );
		$this->assertSame( array( array( $plugin, true ) ), $deleted->get_args(), 'deleted_plugin should receive the plugin and the result.' );
	}

	/**
	 * Tests that only the file of a single-file plugin is deleted.
	 *
	 * @ticket 65819
	 */
	public function test_should_delete_only_file_of_single_file_plugin() {
		$plugin = 'delete-test-single-' . ++self::$counter . '.php';
		$this->create_file( WP_PLUGIN_DIR . '/' . $plugin, "<?php\n/**\n * Plugin Name: Single\n */\n" );

		$this->assertTrue( delete_plugins( array( $plugin ) ), 'The plugin should be deleted.' );
		$this->assertFileDoesNotExist( WP_PLUGIN_DIR . '/' . $plugin, 'The plugin file should be removed.' );
		$this->assertFileExists( WP_PLUGIN_DIR . '/hello.php', 'Other plugins should be kept.' );
	}

	/**
	 * Tests that the uninstall callback of a plugin runs before it is deleted.
	 *
	 * @ticket 65819
	 */
	public function test_should_run_uninstall_callback() {
		$plugin = $this->create_plugin_directory();
		update_option( 'uninstall_plugins', array( $plugin => array( __CLASS__, 'record_uninstall' ) ) );

		$this->assertTrue( delete_plugins( array( $plugin ) ), 'The plugin should be deleted.' );
		$this->assertSame( array( $plugin ), self::$uninstalled, 'The uninstall callback should run.' );
	}

	/**
	 * Tests that deleted plugins are removed from the available updates.
	 *
	 * @ticket 65819
	 */
	public function test_should_remove_deleted_plugins_from_updates() {
		$deleted = $this->create_plugin_directory();
		$missing = $this->create_undeletable_plugin();

		set_site_transient(
			'update_plugins',
			(object) array(
				'response' => array(
					$deleted          => (object) array( 'slug' => dirname( $deleted ) ),
					$missing          => (object) array( 'slug' => 'missing-plugin' ),
					'other/other.php' => (object) array( 'slug' => 'other' ),
				),
			)
		);

		delete_plugins( array( $deleted, $missing ) );

		$this->assertSame( array( $missing, 'other/other.php' ), array_keys( get_site_transient( 'update_plugins' )->response ) );
	}

	/**
	 * Tests that the translation files of a deleted plugin are removed.
	 *
	 * @ticket 65819
	 */
	public function test_should_delete_translation_files() {
		$plugin = $this->create_plugin_directory();
		$slug   = dirname( $plugin );
		$dir    = WP_LANG_DIR . '/plugins/';

		$po = "msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: Test\\n\"\n\"Language: de_DE\\n\"\n";
		foreach ( array( '.po', '.mo', '.l10n.php', '-0123456789abcdef.json' ) as $suffix ) {
			$this->create_file( "{$dir}{$slug}-de_DE{$suffix}", '.po' === $suffix ? $po : 'x' );
		}

		$this->assertTrue( delete_plugins( array( $plugin ) ), 'The plugin should be deleted.' );

		foreach ( array( '.po', '.mo', '.l10n.php', '-0123456789abcdef.json' ) as $suffix ) {
			$this->assertFileDoesNotExist( "{$dir}{$slug}-de_DE{$suffix}", "The {$suffix} translation file should be removed." );
		}
		$this->assertFileExists( $dir . 'internationalized-plugin-de_DE.mo', 'Translations of other plugins should be kept.' );
	}

	/**
	 * Tests the error when a plugin cannot be removed.
	 *
	 * @ticket 65819
	 */
	public function test_should_report_plugin_that_could_not_be_removed() {
		$plugin = $this->create_undeletable_plugin();

		$deleted = new MockAction();
		add_action( 'deleted_plugin', array( $deleted, 'action' ), 10, 2 );

		$result = delete_plugins( array( $plugin ) );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( 'could_not_remove_plugin', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( "Could not fully remove the plugin {$plugin}.", $result->get_error_message(), 'The error message should name the plugin.' );
		$this->assertSame( array( array( $plugin, false ) ), $deleted->get_args(), 'deleted_plugin should receive false.' );
	}

	/**
	 * Tests the error when several plugins cannot be removed, while the others are deleted.
	 *
	 * @ticket 65819
	 */
	public function test_should_report_plugins_that_could_not_be_removed() {
		$first  = $this->create_undeletable_plugin();
		$plugin = $this->create_plugin_directory();
		$second = $this->create_undeletable_plugin();

		$result = delete_plugins( array( $first, $plugin, $second ) );

		$this->assertWPError( $result, 'An error should be returned.' );
		$this->assertSame( "Could not fully remove the plugins {$first}, {$second}.", $result->get_error_message(), 'The error message should list the plugins.' );
		$this->assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . dirname( $plugin ), 'The other plugin should be deleted.' );
	}

	/**
	 * Records that an uninstall callback ran.
	 */
	public static function record_uninstall() {
		self::$uninstalled[] = current_action() ? substr( current_action(), strlen( 'uninstall_' ) ) : '';
	}

	/**
	 * Creates a plugin directory with a main file and a second file.
	 *
	 * @return string The plugin file.
	 */
	private function create_plugin_directory() {
		$slug = 'delete-test-plugin-' . ++self::$counter;
		$dir  = WP_PLUGIN_DIR . '/' . $slug;

		$this->remove_path( $dir );
		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->created[] = $dir;

		$this->create_file( "{$dir}/{$slug}.php", "<?php\n/**\n * Plugin Name: Delete Test Plugin\n */\n" );
		$this->create_file( "{$dir}/readme.txt", 'Readme.' );

		wp_cache_delete( 'plugins', 'plugins' );

		return "{$slug}/{$slug}.php";
	}

	/**
	 * Creates a single-file plugin that cannot be deleted: a directory with a file in it,
	 * which a non-recursive delete cannot remove.
	 *
	 * @return string The plugin file.
	 */
	private function create_undeletable_plugin() {
		$plugin = 'delete-test-undeletable-' . ++self::$counter . '.php';
		$dir    = WP_PLUGIN_DIR . '/' . $plugin;

		$this->remove_path( $dir );
		$this->assertTrue( mkdir( $dir ), 'The test directory should be created.' );
		$this->created[] = $dir;

		$this->create_file( $dir . '/keep.txt', 'Keep.' );

		return $plugin;
	}

	/**
	 * Creates a file and registers it for cleanup.
	 *
	 * @param string $path     The file path.
	 * @param string $contents The file contents.
	 */
	private function create_file( $path, $contents ) {
		$this->remove_path( $path );
		$this->assertNotFalse( file_put_contents( $path, $contents ), 'The file should be written.' );
		$this->created[] = $path;
	}

	/**
	 * Removes a file or directory, including one left over by an interrupted run.
	 *
	 * @param string $path The file or directory path.
	 */
	private function remove_path( $path ) {
		if ( is_file( $path ) ) {
			unlink( $path );
		} elseif ( is_dir( $path ) ) {
			$this->rmdir( $path );
			$this->delete_folders( $path );
		}
	}
}
