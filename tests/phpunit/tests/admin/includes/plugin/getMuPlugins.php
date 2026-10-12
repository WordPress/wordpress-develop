<?php

/**
 * Tests for get_mu_plugins().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::get_mu_plugins
 */
class Tests_Admin_Includes_Plugin_GetMuPlugins extends WP_UnitTestCase {

	/**
	 * Created test files to clean up in tear_down.
	 *
	 * @var string[]
	 */
	private $created_files = array();

	/**
	 * Created test directories to clean up in tear_down.
	 *
	 * @var string[]
	 */
	private $created_dirs = array();

	/**
	 * Sets up the environment before each test.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	/**
	 * Cleans up the environment after each test.
	 */
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

		wp_cache_delete( 'plugins', 'plugins' );

		parent::tear_down();
	}

	/**
	 * Helper to create a test file in WPMU_PLUGIN_DIR.
	 *
	 * @param string $filename File name relative to WPMU_PLUGIN_DIR.
	 * @param string $content  File contents.
	 * @return string Full path to the created file.
	 */
	private function create_mu_plugin_file( $filename, $content ) {
		$dir = WPMU_PLUGIN_DIR;
		if ( ! is_dir( $dir ) ) {
			$this->assertTrue( wp_mkdir_p( $dir ), "Failed to create directory: {$dir}" );
			$this->created_dirs[] = $dir;
		}

		$full_path = $dir . '/' . $filename;
		$file_dir  = dirname( $full_path );
		if ( ! is_dir( $file_dir ) ) {
			$this->assertTrue( wp_mkdir_p( $file_dir ), "Failed to create directory: {$file_dir}" );
			$this->created_dirs[] = $file_dir;
		}

		$this->assertFileDoesNotExist( $full_path, "MU plugin fixture already exists: {$full_path}" );

		$this->assertNotFalse( file_put_contents( $full_path, $content ), "Failed to write MU plugin fixture: {$full_path}" );
		$this->created_files[] = $full_path;

		wp_cache_delete( 'plugins', 'plugins' );

		return $full_path;
	}

	/**
	 * Tests that get_mu_plugins() detects a valid MU plugin and extracts header data.
	 *
	 * @ticket 65819
	 */
	public function test_get_mu_plugins_detects_plugin_and_extracts_headers() {
		$content = "<?php\n/**\n * Plugin Name: Sample MU Plugin\n * Description: A sample must-use plugin.\n * Version: 1.0.0\n * Author: WordPress Contributor\n */\n";
		$this->create_mu_plugin_file( 'sample-mu-plugin.php', $content );

		$mu_plugins = get_mu_plugins();

		$this->assertArrayHasKey( 'sample-mu-plugin.php', $mu_plugins );
		$this->assertSame( 'Sample MU Plugin', $mu_plugins['sample-mu-plugin.php']['Name'] );
		$this->assertSame( 'A sample must-use plugin.', $mu_plugins['sample-mu-plugin.php']['Description'] );
		$this->assertSame( '1.0.0', $mu_plugins['sample-mu-plugin.php']['Version'] );
		$this->assertSame( 'WordPress Contributor', $mu_plugins['sample-mu-plugin.php']['Author'] );
	}

	/**
	 * Tests that get_mu_plugins() falls back to using the file name when no Plugin Name header is present.
	 *
	 * @ticket 65819
	 */
	public function test_get_mu_plugins_falls_back_to_filename_when_no_header() {
		$content = "<?php\n// Simple script without plugin header comments.\n";
		$this->create_mu_plugin_file( 'unnamed-mu-plugin.php', $content );

		$mu_plugins = get_mu_plugins();

		$this->assertArrayHasKey( 'unnamed-mu-plugin.php', $mu_plugins );
		$this->assertSame( 'unnamed-mu-plugin.php', $mu_plugins['unnamed-mu-plugin.php']['Name'] );
	}

	/**
	 * Tests that get_mu_plugins() ignores non-PHP files and subdirectory files.
	 *
	 * @ticket 65819
	 */
	public function test_get_mu_plugins_ignores_non_php_files() {
		$this->create_mu_plugin_file( 'readme.txt', 'This is a text file.' );
		$this->create_mu_plugin_file( 'config.json', '{"key": "value"}' );

		$mu_plugins = get_mu_plugins();

		$this->assertArrayNotHasKey( 'readme.txt', $mu_plugins );
		$this->assertArrayNotHasKey( 'config.json', $mu_plugins );
	}

	/**
	 * Tests that get_mu_plugins() unsets standard silence-is-golden index.php (<= 30 bytes).
	 *
	 * @ticket 65819
	 */
	public function test_get_mu_plugins_unsets_silence_is_golden_index_file() {
		// Standard silence-is-golden file is typically "<?php\n// Silence is golden.\n" (25 bytes).
		$silence_content = "<?php\n// Silence is golden.\n";
		$this->assertLessThanOrEqual( 30, strlen( $silence_content ) );

		$this->create_mu_plugin_file( 'index.php', $silence_content );

		$mu_plugins = get_mu_plugins();

		$this->assertArrayNotHasKey( 'index.php', $mu_plugins, 'Standard silence is golden index.php must be unset.' );
	}

	/**
	 * Tests that get_mu_plugins() preserves index.php when it contains an actual plugin (> 30 bytes).
	 *
	 * @ticket 65819
	 */
	public function test_get_mu_plugins_keeps_index_file_when_exceeds_threshold() {
		$plugin_content = "<?php\n/**\n * Plugin Name: MU Index Plugin\n * Description: Real plugin in index.php\n */\n";
		$this->assertGreaterThan( 30, strlen( $plugin_content ) );

		$this->create_mu_plugin_file( 'index.php', $plugin_content );

		$mu_plugins = get_mu_plugins();

		$this->assertArrayHasKey( 'index.php', $mu_plugins );
		$this->assertSame( 'MU Index Plugin', $mu_plugins['index.php']['Name'] );
	}

	/**
	 * Tests that get_mu_plugins() sorts results naturally and case-insensitively.
	 *
	 * @ticket 65819
	 */
	public function test_get_mu_plugins_sorts_keys_naturally() {
		$this->create_mu_plugin_file( 'zebra-mu.php', "<?php\n/**\n * Plugin Name: Zebra\n */\n" );
		$this->create_mu_plugin_file( 'alpha-mu.php', "<?php\n/**\n * Plugin Name: Alpha\n */\n" );

		$mu_plugins = get_mu_plugins();
		$keys       = array_keys( $mu_plugins );

		$alpha_pos = array_search( 'alpha-mu.php', $keys, true );
		$zebra_pos = array_search( 'zebra-mu.php', $keys, true );

		$this->assertNotFalse( $alpha_pos );
		$this->assertNotFalse( $zebra_pos );
		$this->assertLessThan( $zebra_pos, $alpha_pos, 'alpha-mu.php should precede zebra-mu.php when sorted.' );
	}
}
