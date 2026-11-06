<?php

/**
 * Tests for get_plugins() and get_plugin_files().
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_Includes_Plugin_GetPlugins extends WP_UnitTestCase {

	/**
	 * A prefix that makes the names of the test plugins unique.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * The paths in the plugins directory that the test created.
	 *
	 * @var string[]
	 */
	private $created = array();

	/**
	 * Prepares unique test plugins.
	 */
	public function set_up() {
		parent::set_up();

		$this->prefix  = 'get-plugins-test-' . wp_generate_password( 8, false );
		$this->created = array();

		wp_clean_plugins_cache( false );
	}

	/**
	 * Removes the test plugins.
	 */
	public function tear_down() {
		foreach ( $this->created as $path ) {
			$full_path = WP_PLUGIN_DIR . '/' . $path;

			$this->remove_path( $full_path );
		}

		wp_clean_plugins_cache( false );

		parent::tear_down();
	}

	/**
	 * Tests that a plugin in the plugins directory is found.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_find_a_plugin_file_in_the_plugins_directory() {
		$this->write_plugin( "{$this->prefix}.php", 'Single File' );

		$plugins = get_plugins();

		$this->assertArrayHasKey( "{$this->prefix}.php", $plugins );
		$this->assertSame( 'Single File', $plugins[ "{$this->prefix}.php" ]['Name'] );
	}

	/**
	 * Tests that a plugin in a folder is found.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_find_a_plugin_in_a_folder() {
		$this->write_plugin( "{$this->prefix}/{$this->prefix}.php", 'In A Folder', array( 'Version' => '3.1' ) );

		$plugins = get_plugins();

		$this->assertArrayHasKey( "{$this->prefix}/{$this->prefix}.php", $plugins );
		$this->assertSame( 'In A Folder', $plugins[ "{$this->prefix}/{$this->prefix}.php" ]['Name'] );
		$this->assertSame( '3.1', $plugins[ "{$this->prefix}/{$this->prefix}.php" ]['Version'] );
	}

	/**
	 * Tests that all the files with a plugin header of a folder are found.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_find_every_plugin_file_of_a_folder() {
		$this->write_plugin( "{$this->prefix}/one.php", 'Folder One' );
		$this->write_plugin( "{$this->prefix}/two.php", 'Folder Two' );

		$plugins = get_plugins();

		$this->assertArrayHasKey( "{$this->prefix}/one.php", $plugins );
		$this->assertArrayHasKey( "{$this->prefix}/two.php", $plugins );
	}

	/**
	 * Tests the files that are not plugins.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_ignore_files_that_are_not_plugins() {
		$this->write_plugin( "{$this->prefix}/deeper/nested/nested.php", 'Nested Too Deep' );
		$this->write_file( "{$this->prefix}/no-header.php", "<?php\n// Not a plugin.\n" );
		$this->write_file( "{$this->prefix}/readme.txt", "Plugin Name: Text File\n" );
		$this->write_plugin( "{$this->prefix}/.hidden.php", 'Hidden File' );
		$this->write_plugin( "{$this->prefix}-dir/.hidden/plugin.php", 'Hidden Folder' );
		$this->write_plugin( ".{$this->prefix}.php", 'Hidden Single File' );
		$this->write_file( "{$this->prefix}.txt", "<?php\n/**\n * Plugin Name: Text File In The Plugins Directory\n */\n" );

		$plugins = get_plugins();

		foreach ( array_keys( $plugins ) as $plugin_file ) {
			$this->assertStringNotContainsString( $this->prefix, $plugin_file, "The file $plugin_file should not be found." );
		}
	}

	/**
	 * Tests that the plugin data is returned without markup.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_return_the_data_without_markup() {
		$this->write_plugin(
			"{$this->prefix}/{$this->prefix}.php",
			'Plain',
			array(
				'Plugin URI' => 'https://example.org/',
				'Author'     => 'Jane Doe',
				'Author URI' => 'https://example.org/jane',
			)
		);

		$plugin = get_plugins()[ "{$this->prefix}/{$this->prefix}.php" ];

		$this->assertSame( 'Plain', $plugin['Title'], 'The title should not be linked.' );
		$this->assertSame( 'Jane Doe', $plugin['Author'], 'The author should not be linked.' );
	}

	/**
	 * Tests that the plugins are sorted by name.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_sort_the_plugins_by_name() {
		$this->write_plugin( "{$this->prefix}/zeta.php", "{$this->prefix} Zeta" );
		$this->write_plugin( "{$this->prefix}/alpha.php", "{$this->prefix} Alpha" );
		$this->write_plugin( "{$this->prefix}/middle.php", "{$this->prefix} Middle" );

		$files = array_values(
			array_filter(
				array_keys( get_plugins() ),
				function ( $plugin_file ) {
					return str_starts_with( $plugin_file, $this->prefix );
				}
			)
		);

		$this->assertSame(
			array( "{$this->prefix}/alpha.php", "{$this->prefix}/middle.php", "{$this->prefix}/zeta.php" ),
			$files
		);
	}

	/**
	 * Tests that only the plugins of a folder are returned for that folder.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_return_the_plugins_of_a_folder() {
		$this->write_plugin( "{$this->prefix}/one.php", 'Folder One' );
		$this->write_plugin( "{$this->prefix}/two.php", 'Folder Two' );
		$this->write_plugin( "{$this->prefix}-other/other.php", 'Other Folder' );

		$plugins = get_plugins( "/{$this->prefix}" );

		$this->assertSame( array( 'one.php', 'two.php' ), array_keys( $plugins ) );
		$this->assertSame( 'Folder One', $plugins['one.php']['Name'] );
	}

	/**
	 * Tests that no plugins are returned for a folder that does not exist.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_return_nothing_for_a_folder_that_does_not_exist() {
		$this->assertSame( array(), get_plugins( "/{$this->prefix}-missing" ) );
	}

	/**
	 * Tests that the plugins are cached, and that clearing the cache finds the changes.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_cache_the_plugins_until_the_cache_is_cleared() {
		$this->write_plugin( "{$this->prefix}/{$this->prefix}.php", 'Cached' );

		$cache = wp_cache_get( 'plugins', 'plugins' );

		$this->assertFalse( $cache, 'The cache should be empty.' );
		$this->assertArrayHasKey( "{$this->prefix}/{$this->prefix}.php", get_plugins() );

		$cache = wp_cache_get( 'plugins', 'plugins' );

		$this->assertArrayHasKey( '', $cache, 'The plugins should be cached for the plugins directory.' );
		$this->assertArrayHasKey( "{$this->prefix}/{$this->prefix}.php", $cache[''] );

		$this->write_plugin( "{$this->prefix}/{$this->prefix}.php", 'Changed' );

		$this->assertSame( 'Cached', get_plugins()[ "{$this->prefix}/{$this->prefix}.php" ]['Name'], 'The cached plugin should be returned.' );

		wp_clean_plugins_cache( false );

		$this->assertSame( 'Changed', get_plugins()[ "{$this->prefix}/{$this->prefix}.php" ]['Name'], 'The plugin should be read again.' );
	}

	/**
	 * Tests that the plugins of a folder are cached separately.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugins
	 */
	public function test_get_plugins_should_cache_each_folder_separately() {
		$this->write_plugin( "{$this->prefix}/one.php", 'Folder One' );

		get_plugins( "/{$this->prefix}" );

		$cache = wp_cache_get( 'plugins', 'plugins' );

		$this->assertSame( array( "/{$this->prefix}" ), array_keys( $cache ), 'Only the folder should be cached.' );
		$this->assertSame( array( 'one.php' ), array_keys( $cache[ "/{$this->prefix}" ] ) );
	}

	/**
	 * Tests that the files of a plugin in a folder are listed.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugin_files
	 */
	public function test_get_plugin_files_should_list_the_files_of_the_folder_of_the_plugin() {
		$this->write_plugin( "{$this->prefix}/{$this->prefix}.php", 'With Files' );
		$this->write_file( "{$this->prefix}/inc/helper.php", "<?php\n" );
		$this->write_file( "{$this->prefix}/assets/style.css", "body {}\n" );

		$files = get_plugin_files( "{$this->prefix}/{$this->prefix}.php" );

		$this->assertSame( "{$this->prefix}/{$this->prefix}.php", $files[0], 'The plugin file should come first.' );
		$this->assertEqualSets(
			array(
				"{$this->prefix}/{$this->prefix}.php",
				"{$this->prefix}/inc/helper.php",
				"{$this->prefix}/assets/style.css",
			),
			$files
		);
	}

	/**
	 * Tests the folders that are left out by default.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugin_files
	 *
	 * @dataProvider data_excluded_folders
	 *
	 * @param string $folder The name of the folder.
	 */
	public function test_get_plugin_files_should_leave_out_some_folders( $folder ) {
		$this->write_plugin( "{$this->prefix}/{$this->prefix}.php", 'With Files' );
		$this->write_file( "{$this->prefix}/{$folder}/file.js", "// File\n" );

		$this->assertSame(
			array( "{$this->prefix}/{$this->prefix}.php" ),
			get_plugin_files( "{$this->prefix}/{$this->prefix}.php" )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_excluded_folders() {
		return array(
			'CVS'              => array( 'CVS' ),
			'node_modules'     => array( 'node_modules' ),
			'vendor'           => array( 'vendor' ),
			'bower_components' => array( 'bower_components' ),
		);
	}

	/**
	 * Tests that the folders that are left out can be filtered.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugin_files
	 */
	public function test_get_plugin_files_should_use_the_plugin_files_exclusions_filter() {
		$this->write_plugin( "{$this->prefix}/{$this->prefix}.php", 'With Files' );
		$this->write_file( "{$this->prefix}/vendor/file.php", "<?php\n" );
		$this->write_file( "{$this->prefix}/tests/test.php", "<?php\n" );

		$received = array();
		add_filter(
			'plugin_files_exclusions',
			static function ( $exclusions ) use ( &$received ) {
				$received = $exclusions;

				return array( 'tests' );
			}
		);

		$files = get_plugin_files( "{$this->prefix}/{$this->prefix}.php" );

		$this->assertSame( array( 'CVS', 'node_modules', 'vendor', 'bower_components' ), $received, 'The filter should receive the default exclusions.' );
		$this->assertContains( "{$this->prefix}/vendor/file.php", $files, 'The folder should be listed once it is no longer excluded.' );
		$this->assertNotContains( "{$this->prefix}/tests/test.php", $files, 'The folder that the filter adds should be left out.' );
	}

	/**
	 * Tests that a plugin file in the plugins directory is listed alone.
	 *
	 * @ticket 66556
	 *
	 * @covers ::get_plugin_files
	 */
	public function test_get_plugin_files_should_not_list_the_plugins_directory() {
		$this->write_plugin( "{$this->prefix}.php", 'Single File' );

		$this->assertSame( array( "{$this->prefix}.php" ), get_plugin_files( "{$this->prefix}.php" ) );
	}

	/**
	 * Removes a file or a folder, including the files and folders that are hidden.
	 *
	 * @param string $path The path.
	 */
	private function remove_path( $path ) {
		if ( is_file( $path ) ) {
			unlink( $path );

			return;
		}

		if ( ! is_dir( $path ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $path );
	}

	/**
	 * Writes a plugin.
	 *
	 * @param string   $path    The path of the plugin file, in the plugins directory.
	 * @param string   $name    The name of the plugin.
	 * @param string[] $headers Optional. Other headers, by name. Default none.
	 */
	private function write_plugin( $path, $name, array $headers = array() ) {
		$content = "<?php\n/**\n * Plugin Name: $name\n";

		foreach ( $headers as $header => $value ) {
			$content .= " * $header: $value\n";
		}

		$this->write_file( $path, $content . " */\n" );
	}

	/**
	 * Writes a file in the plugins directory.
	 *
	 * @param string $path    The path of the file, in the plugins directory.
	 * @param string $content The content of the file.
	 */
	private function write_file( $path, $content ) {
		$full_path = WP_PLUGIN_DIR . '/' . $path;
		$dir       = dirname( $full_path );

		if ( ! is_dir( $dir ) ) {
			$this->assertTrue( wp_mkdir_p( $dir ), 'The directory should be created.' );
		}

		$top = explode( '/', $path )[0];

		if ( ! in_array( $top, $this->created, true ) ) {
			$this->created[] = $top;
		}

		$this->assertNotFalse( file_put_contents( $full_path, $content ), 'The file should be written.' );
	}
}
