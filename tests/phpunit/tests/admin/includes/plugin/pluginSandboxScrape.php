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

		$dir    = WP_PLUGIN_DIR . '/' . self::SLUG;
		$plugin = $dir . '/' . self::SLUG . '.php';

		// Remove this test's fixture if an earlier run was interrupted.
		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}

		$this->assertDirectoryDoesNotExist( $dir, 'The test plugin directory should not exist yet.' );
		$this->assertTrue( mkdir( $dir ), 'The test plugin directory should be created.' );
		$plugin_contents = "<?php\n"
			. "/**\n * Plugin Name: Sandbox Scrape Test Plugin\n */\n"
			. "\$GLOBALS['sandbox_scrape_constant_at_include'] = defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING;\n"
			. "function sandbox_scrape_test_plugin_loaded() {\n\treturn true;\n}\n";

		$this->assertNotFalse(
			file_put_contents( $plugin, $plugin_contents ),
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

		$this->assertTrue(
			$GLOBALS['sandbox_scrape_constant_at_include'] ?? false,
			'Sandbox mode should be on before the plugin is included.'
		);
		$this->assertTrue( WP_SANDBOX_SCRAPING, 'Sandbox mode should be on after the plugin is included.' );
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
		if ( ! function_exists( 'symlink' ) ) {
			$this->markTestSkipped( 'Symbolic links are not available on this system.' );
		}

		$target = get_temp_dir() . 'sandbox-scrape-target-' . wp_rand();
		$link   = WP_PLUGIN_DIR . '/sandbox-scrape-symlinked';

		$target_created = false;
		$plugin_created = false;
		$link_created   = false;

		try {
			$target_created = mkdir( $target );
			$this->assertTrue( $target_created, 'The target directory should be created.' );

			$plugin_contents = "<?php\n"
				. "/**\n * Plugin Name: Symlinked\n */\n"
				. "\$GLOBALS['sandbox_scrape_symlink_basename_at_include'] = plugin_basename( __FILE__ );\n";
			$plugin_created  = false !== file_put_contents( $target . '/plugin.php', $plugin_contents );
			$this->assertTrue( $plugin_created, 'The target plugin should be written.' );
			$this->assertFileDoesNotExist( $link, 'The link should not exist yet.' );
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Probe native symlink support.
			$link_created = @symlink( $target, $link );
			if ( ! $link_created ) {
				$this->markTestSkipped( 'Symbolic links cannot be created on this system.' );
			}

			plugin_sandbox_scrape( 'sandbox-scrape-symlinked/plugin.php' );

			$this->assertSame(
				'sandbox-scrape-symlinked/plugin.php',
				$GLOBALS['sandbox_scrape_symlink_basename_at_include'] ?? null,
				'The plugin should see its public symlinked basename when it is included.'
			);
		} finally {
			if ( $link_created && is_link( $link ) ) {
				if ( 'Windows' === PHP_OS_FAMILY ) {
					rmdir( $link );
				} else {
					unlink( $link );
				}
			}
			if ( $plugin_created && is_file( $target . '/plugin.php' ) ) {
				unlink( $target . '/plugin.php' );
			}
			if ( $target_created && is_dir( $target ) ) {
				rmdir( $target );
			}
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
