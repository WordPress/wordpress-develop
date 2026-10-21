<?php

/**
 * Tests for plugin_sandbox_scrape().
 *
 * The tests run in separate processes, because the function defines the
 * WP_SANDBOX_SCRAPING constant, which other tests check, and includes the plugin.
 *
 * @group admin
 * @group plugins
 *
 * @covers ::plugin_sandbox_scrape
 */
class Tests_Admin_Includes_Plugin_PluginSandboxScrape extends WP_UnitTestCase {

	/**
	 * The slug of the test plugin.
	 *
	 * @var string
	 */
	const SLUG = 'sandbox-scrape-test-plugin';

	/**
	 * Creates the test plugin.
	 */
	public function set_up() {
		parent::set_up();

		$dir = WP_PLUGIN_DIR . '/' . self::SLUG;
		$this->assertDirectoryDoesNotExist( $dir, 'The test plugin directory should not exist yet.' );
		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$this->assertNotFalse(
			file_put_contents(
				$dir . '/' . self::SLUG . '.php',
				"<?php\n/**\n * Plugin Name: Sandbox Scrape Test Plugin\n */\nfunction sandbox_scrape_test_plugin_loaded() {\n\treturn true;\n}\n"
			),
			'The test plugin should be written.'
		);
	}

	/**
	 * Removes the test plugin.
	 */
	public function tear_down() {
		$dir = WP_PLUGIN_DIR . '/' . self::SLUG;
		if ( is_dir( $dir ) ) {
			unlink( $dir . '/' . self::SLUG . '.php' );
			rmdir( $dir );
		}

		parent::tear_down();
	}

	/**
	 * Tests that the plugin is loaded in sandbox mode.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_load_plugin_in_sandbox_mode() {
		$this->assertFalse( defined( 'WP_SANDBOX_SCRAPING' ), 'Sandbox mode should not be on yet.' );

		plugin_sandbox_scrape( self::SLUG . '/' . self::SLUG . '.php' );

		$this->assertTrue( WP_SANDBOX_SCRAPING, 'Sandbox mode should be on.' );
		$this->assertTrue( function_exists( 'sandbox_scrape_test_plugin_loaded' ), 'The plugin should be loaded.' );
	}

	/**
	 * Tests that the real path of a symlinked plugin is registered.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_register_real_path_of_symlinked_plugin() {
		$target = get_temp_dir() . 'sandbox-scrape-target-' . wp_rand();
		$link   = WP_PLUGIN_DIR . '/sandbox-scrape-symlinked';

		$this->assertTrue( mkdir( $target ), 'The target directory should be created.' );
		$this->assertNotFalse( file_put_contents( $target . '/plugin.php', "<?php\n/**\n * Plugin Name: Symlinked\n */\n" ), 'The target plugin should be written.' );
		$this->assertFileDoesNotExist( $link, 'The link should not exist yet.' );
		$this->assertTrue( symlink( $target, $link ), 'The plugin should be linked.' );

		try {
			plugin_sandbox_scrape( 'sandbox-scrape-symlinked/plugin.php' );

			$this->assertSame(
				wp_normalize_path( realpath( $target ) ),
				$GLOBALS['wp_plugin_paths'][ wp_normalize_path( $link ) ] ?? null
			);
		} finally {
			unlink( $link );
			unlink( $target . '/plugin.php' );
			rmdir( $target );
		}
	}

	/**
	 * Tests that an existing sandbox constant is kept.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_should_keep_existing_sandbox_constant() {
		define( 'WP_SANDBOX_SCRAPING', false );

		plugin_sandbox_scrape( self::SLUG . '/' . self::SLUG . '.php' );

		$this->assertFalse( WP_SANDBOX_SCRAPING, 'The existing constant should be kept.' );
		$this->assertTrue( function_exists( 'sandbox_scrape_test_plugin_loaded' ), 'The plugin should be loaded.' );
	}
}
