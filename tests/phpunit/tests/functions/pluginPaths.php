<?php

/**
 * Tests for plugin_dir_path(), plugin_dir_url(), plugin_basename(), wp_register_plugin_realpath() and remove_all_actions().
 *
 * @group functions
 * @group plugins
 */
class Tests_Functions_PluginPaths extends WP_UnitTestCase {

	/**
	 * The original paths of the plugins that are symbolic links.
	 *
	 * @var array|null
	 */
	private $original_plugin_paths;

	/**
	 * The folders that the tests created, which are removed after each test.
	 *
	 * @var string[]
	 */
	private $folders = array();

	/**
	 * The links that the tests created, which are removed after each test.
	 *
	 * @var string[]
	 */
	private $links = array();

	/**
	 * Saves the paths of the plugins.
	 */
	public function set_up() {
		global $wp_plugin_paths;

		parent::set_up();

		$this->original_plugin_paths = $wp_plugin_paths;
		$wp_plugin_paths             = array();
	}

	/**
	 * Restores the paths of the plugins and removes the links and the folders.
	 */
	public function tear_down() {
		global $wp_plugin_paths;

		$wp_plugin_paths = $this->original_plugin_paths;

		foreach ( $this->links as $link ) {
			if ( is_link( $link ) ) {
				// Windows removes a link to a directory like a directory.
				if ( '\\' === DIRECTORY_SEPARATOR ) {
					rmdir( $link );
				} else {
					unlink( $link );
				}
			}
		}

		foreach ( $this->folders as $folder ) {
			if ( is_dir( $folder ) ) {
				$this->rmdir( $folder );
				$this->delete_folders( $folder );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests the path of the directory of a file.
	 *
	 * @ticket 66561
	 *
	 * @covers ::plugin_dir_path
	 *
	 * @dataProvider data_dir_paths
	 *
	 * @param string $file     The file.
	 * @param string $expected The expected path.
	 */
	public function test_plugin_dir_path_should_return_the_directory_with_a_trailing_slash( $file, $expected ) {
		$this->assertSame( $expected, plugin_dir_path( $file ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_dir_paths() {
		return array(
			'a file in a folder'    => array( '/var/www/wp-content/plugins/my-plugin/my-plugin.php', '/var/www/wp-content/plugins/my-plugin/' ),
			'a file in a subfolder' => array( '/var/www/my-plugin/includes/class.php', '/var/www/my-plugin/includes/' ),
			'a file in the root'    => array( '/plugin.php', '/' ),
			'a file without a path' => array( 'plugin.php', './' ),
		);
	}

	/**
	 * Tests the URL of the directory of a plugin file.
	 *
	 * @ticket 66561
	 *
	 * @covers ::plugin_dir_url
	 */
	public function test_plugin_dir_url_should_return_the_url_of_the_directory_with_a_trailing_slash() {
		$this->assertSame(
			plugins_url( '', WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' ) . '/',
			plugin_dir_url( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' )
		);
		$this->assertSame( content_url( 'plugins/my-plugin/' ), plugin_dir_url( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' ), 'The URL should be in the plugins directory.' );
	}

	/**
	 * Tests the URL of the directory of a Must-Use plugin file.
	 *
	 * @ticket 66561
	 *
	 * @covers ::plugin_dir_url
	 */
	public function test_plugin_dir_url_should_return_the_url_of_the_directory_of_a_must_use_plugin() {
		$this->assertSame( content_url( 'mu-plugins/' ), plugin_dir_url( WPMU_PLUGIN_DIR . '/my-plugin.php' ) );
	}

	/**
	 * Tests the path of a plugin that is relative to the plugins directory.
	 *
	 * @ticket 66561
	 *
	 * @covers ::plugin_basename
	 *
	 * @dataProvider data_basenames
	 *
	 * @param string $file     The file, with the constant of the directory in place of its path.
	 * @param string $expected The expected path.
	 */
	public function test_plugin_basename_should_return_the_path_relative_to_the_plugins_directory( $file, $expected ) {
		$file = str_replace( array( '{PLUGINS}', '{MU}' ), array( WP_PLUGIN_DIR, WPMU_PLUGIN_DIR ), $file );

		$this->assertSame( $expected, plugin_basename( $file ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_basenames() {
		return array(
			'a plugin in a folder'          => array( '{PLUGINS}/my-plugin/my-plugin.php', 'my-plugin/my-plugin.php' ),
			'a file of the plugins folder'  => array( '{PLUGINS}/hello.php', 'hello.php' ),
			'a Must-Use plugin'             => array( '{MU}/my-mu-plugin.php', 'my-mu-plugin.php' ),
			'a file in a subfolder'         => array( '{PLUGINS}/my-plugin/includes/class.php', 'my-plugin/includes/class.php' ),
			'a path with backslashes'       => array( str_replace( '/', '\\', '{PLUGINS}/my-plugin/my-plugin.php' ), 'my-plugin/my-plugin.php' ),
			'a path with extra slashes'     => array( '{PLUGINS}//my-plugin//my-plugin.php', 'my-plugin/my-plugin.php' ),
			'a relative path'               => array( 'my-plugin/my-plugin.php', 'my-plugin/my-plugin.php' ),
			'a path with slashes around it' => array( '/my-plugin/my-plugin.php/', 'my-plugin/my-plugin.php' ),
			'a file outside of the plugins' => array( '/var/www/other/file.php', 'var/www/other/file.php' ),
		);
	}

	/**
	 * Tests that the real path of a plugin that is a symbolic link is replaced with its path in the plugins directory.
	 *
	 * @ticket 66561
	 *
	 * @covers ::plugin_basename
	 */
	public function test_plugin_basename_should_use_the_registered_path_of_a_symbolic_link() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array( wp_normalize_path( WP_PLUGIN_DIR ) . '/linked-plugin' => '/real/location/linked-plugin' );

		$this->assertSame( 'linked-plugin/linked-plugin.php', plugin_basename( '/real/location/linked-plugin/linked-plugin.php' ) );
		$this->assertSame( 'linked-plugin/includes/class.php', plugin_basename( '/real/location/linked-plugin/includes/class.php' ) );
	}

	/**
	 * Tests that the most specific registered location is used first.
	 *
	 * @ticket 66561
	 *
	 * @covers ::plugin_basename
	 */
	public function test_plugin_basename_should_use_the_most_specific_registered_path_first() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array(
			wp_normalize_path( WP_PLUGIN_DIR ) . '/short' => '/real/a',
			wp_normalize_path( WP_PLUGIN_DIR ) . '/long'  => '/real/a/b',
		);

		$this->assertSame( 'long/plugin.php', plugin_basename( '/real/a/b/plugin.php' ) );
		$this->assertSame( 'short/plugin.php', plugin_basename( '/real/a/plugin.php' ) );
	}

	/**
	 * Tests that a path that is not in a registered location is not changed.
	 *
	 * @ticket 66561
	 *
	 * @covers ::plugin_basename
	 */
	public function test_plugin_basename_should_not_change_a_path_outside_of_the_registered_locations() {
		global $wp_plugin_paths;

		$wp_plugin_paths = array( wp_normalize_path( WP_PLUGIN_DIR ) . '/linked-plugin' => '/real/location/linked-plugin' );

		$this->assertSame( 'other-plugin/other-plugin.php', plugin_basename( WP_PLUGIN_DIR . '/other-plugin/other-plugin.php' ) );
	}

	/**
	 * Tests that no path is registered for a file in the plugins directory.
	 *
	 * @ticket 66561
	 *
	 * @covers ::wp_register_plugin_realpath
	 *
	 * @dataProvider data_plugin_directories
	 *
	 * @param string $directory The constant of the directory in place of its path.
	 */
	public function test_wp_register_plugin_realpath_should_not_register_a_file_in_a_plugins_directory( $directory ) {
		global $wp_plugin_paths;

		$directory = str_replace( array( '{PLUGINS}', '{MU}' ), array( WP_PLUGIN_DIR, WPMU_PLUGIN_DIR ), $directory );

		$this->assertFalse( wp_register_plugin_realpath( $directory . '/single-file.php' ) );
		$this->assertSame( array(), $wp_plugin_paths );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_plugin_directories() {
		return array(
			'the plugins directory'          => array( '{PLUGINS}' ),
			'the Must-Use plugins directory' => array( '{MU}' ),
		);
	}

	/**
	 * Tests that no path is registered for a plugin that is not a symbolic link.
	 *
	 * @ticket 66561
	 *
	 * @covers ::wp_register_plugin_realpath
	 */
	public function test_wp_register_plugin_realpath_should_not_register_a_plugin_that_is_not_a_link() {
		global $wp_plugin_paths;

		$file = realpath( DIR_TESTDATA ) . '/plugins/custom-internationalized-plugin/custom-internationalized-plugin.php';

		$this->assertTrue( wp_register_plugin_realpath( $file ) );
		$this->assertSame( array(), $wp_plugin_paths );
	}

	/**
	 * Tests that the real path of a plugin that is a symbolic link is registered.
	 *
	 * @ticket 66561
	 *
	 * @covers ::wp_register_plugin_realpath
	 * @covers ::plugin_basename
	 */
	public function test_wp_register_plugin_realpath_should_register_the_real_path_of_a_symbolic_link() {
		global $wp_plugin_paths;

		$real = get_temp_dir() . 'plugin-realpath-' . wp_generate_password( 8, false );
		$link = WP_PLUGIN_DIR . '/' . basename( $real ) . '-link';

		$this->assertTrue( wp_mkdir_p( $real ), 'The folder of the plugin should be created.' );
		$this->folders[] = $real;
		file_put_contents( $real . '/plugin.php', "<?php\n/**\n * Plugin Name: Linked\n */\n" );

		if ( ! @symlink( $real, $link ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Not every system allows links.
			$this->markTestSkipped( 'Symbolic links cannot be created.' );
		}

		$this->links[] = $link;

		$this->assertTrue( wp_register_plugin_realpath( $link . '/plugin.php' ) );
		$this->assertSame(
			array( wp_normalize_path( $link ) => wp_normalize_path( realpath( $real ) ) ),
			$wp_plugin_paths,
			'The real path should be registered for the path in the plugins directory.'
		);
		$this->assertSame( basename( $link ) . '/plugin.php', plugin_basename( realpath( $real ) . '/plugin.php' ), 'The real path should give the path in the plugins directory.' );
	}

	/**
	 * Tests that all the actions of a hook are removed.
	 *
	 * @ticket 66561
	 *
	 * @covers ::remove_all_actions
	 */
	public function test_remove_all_actions_should_remove_the_actions_of_all_the_priorities() {
		add_action( 'plugin_paths_test_action', '__return_true', 5 );
		add_action( 'plugin_paths_test_action', '__return_false', 10 );

		$this->assertTrue( remove_all_actions( 'plugin_paths_test_action' ), 'True should be returned.' );
		$this->assertFalse( has_action( 'plugin_paths_test_action' ), 'No action should be left.' );
	}

	/**
	 * Tests that the actions of a priority are removed, and the others are not.
	 *
	 * @ticket 66561
	 *
	 * @covers ::remove_all_actions
	 */
	public function test_remove_all_actions_should_remove_the_actions_of_one_priority() {
		add_action( 'plugin_paths_test_action', '__return_true', 5 );
		add_action( 'plugin_paths_test_action', '__return_false', 10 );

		$this->assertTrue( remove_all_actions( 'plugin_paths_test_action', 5 ) );
		$this->assertFalse( has_action( 'plugin_paths_test_action', '__return_true' ), 'The action of the priority should be removed.' );
		$this->assertSame( 10, has_action( 'plugin_paths_test_action', '__return_false' ), 'The action of the other priority should be kept.' );
	}

	/**
	 * Tests that removing the actions of a hook that has none is allowed.
	 *
	 * @ticket 66561
	 *
	 * @covers ::remove_all_actions
	 */
	public function test_remove_all_actions_should_return_true_for_a_hook_without_actions() {
		$this->assertTrue( remove_all_actions( 'plugin_paths_test_hook_without_actions' ) );
	}
}
