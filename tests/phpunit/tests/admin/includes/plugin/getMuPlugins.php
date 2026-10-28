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
	 * Sets up the environment before any tests in this class run.
	 */
	public static function wpSetUpBeforeClass() {
		self::_back_up_mu_plugins();
	}

	/**
	 * Restores the environment after all tests in this class have run.
	 */
	public static function wpTearDownAfterClass() {
		self::_restore_mu_plugins();
	}

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
	 * Tests that get_mu_plugins() unsets index.php when at or below the 30-byte threshold.
	 *
	 * @ticket 65819
	 */
	public function test_get_mu_plugins_unsets_silence_is_golden_index_file() {
		// Exactly 30 bytes (the boundary threshold to unset as silence-is-golden).
		$silence_content = "<?php\n// 12345678901234567890\n";
		$this->assertSame( 30, strlen( $silence_content ) );

		$this->create_mu_plugin_file( 'index.php', $silence_content );

		$mu_plugins = get_mu_plugins();

		$this->assertArrayNotHasKey( 'index.php', $mu_plugins, 'An index.php file of 30 bytes or less must be unset.' );
	}

	/**
	 * Tests that get_mu_plugins() sorts by plugin Name naturally and case-insensitively,
	 * independent of fixture filenames.
	 *
	 * @ticket 65819
	 */
	public function test_get_mu_plugins_sorts_by_plugin_name_naturally_and_case_insensitively() {
		// Fixture filenames are chosen so their alphabetical sort order conflicts with their plugin Names.
		// Filename order: a-plugin.php, b-plugin.php, m-plugin.php, z-plugin.php.
		// Plugin Name order: "alpha 2", "Alpha 10", "beta 1", "BETA 2".
		$this->create_mu_plugin_file( 'z-plugin.php', "<?php\n/**\n * Plugin Name: alpha 2\n */\n" );
		$this->create_mu_plugin_file( 'b-plugin.php', "<?php\n/**\n * Plugin Name: Alpha 10\n */\n" );
		$this->create_mu_plugin_file( 'a-plugin.php', "<?php\n/**\n * Plugin Name: beta 1\n */\n" );
		$this->create_mu_plugin_file( 'm-plugin.php', "<?php\n/**\n * Plugin Name: BETA 2\n */\n" );

		$expected_keys = array( 'z-plugin.php', 'b-plugin.php', 'a-plugin.php', 'm-plugin.php' );
		$mu_plugins    = get_mu_plugins();

		// Restrict assertions to this test's fixtures while preserving their returned sort order.
		$fixture_plugins = array_intersect_key( $mu_plugins, array_flip( $expected_keys ) );

		$this->assertSame(
			$expected_keys,
			array_keys( $fixture_plugins ),
			'MU plugins should be sorted by plugin Name rather than filename.'
		);

		$expected_names = array( 'alpha 2', 'Alpha 10', 'beta 1', 'BETA 2' );
		$actual_names   = array_column( $fixture_plugins, 'Name' );
		$this->assertSame(
			$expected_names,
			$actual_names,
			'MU plugins should be naturally and case-insensitively sorted by plugin Name.'
		);
	}

	/**
	 * Moves existing mu-plugins to wp-content/mu-plugin-backup.
	 */
	private static function _back_up_mu_plugins() {
		if ( is_dir( WPMU_PLUGIN_DIR ) ) {
			$mu_bu_dir = WP_CONTENT_DIR . '/mu-plugin-backup';
			rename( WPMU_PLUGIN_DIR, $mu_bu_dir );
		}
	}

	/**
	 * Restores backed-up mu-plugins.
	 */
	private static function _restore_mu_plugins() {
		$mu_bu_dir = WP_CONTENT_DIR . '/mu-plugin-backup';

		if ( is_dir( WPMU_PLUGIN_DIR ) ) {
			$files = glob( WPMU_PLUGIN_DIR . '/*' );
			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_dir( $file ) ) {
						rmdir( $file );
					} elseif ( is_file( $file ) ) {
						unlink( $file );
					}
				}
			}
			rmdir( WPMU_PLUGIN_DIR );
		}

		if ( is_dir( $mu_bu_dir ) ) {
			rename( $mu_bu_dir, WPMU_PLUGIN_DIR );
		}
	}
}
