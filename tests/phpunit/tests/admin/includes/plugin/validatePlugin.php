<?php

/**
 * Tests for validate_plugin().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::validate_plugin
 */
class Tests_Admin_Includes_Plugin_ValidatePlugin extends WP_UnitTestCase {

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
	 * Helper to create a test file in WP_PLUGIN_DIR.
	 *
	 * @param string $relative_path Path relative to WP_PLUGIN_DIR.
	 * @param string $content       File contents.
	 * @return string Relative path.
	 */
	private function create_plugin_file( $relative_path, $content ) {
		$full_path = WP_PLUGIN_DIR . '/' . $relative_path;
		$dir       = dirname( $full_path );

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			$this->created_dirs[] = $dir;
		}

		file_put_contents( $full_path, $content );
		$this->created_files[] = $full_path;

		wp_cache_delete( 'plugins', 'plugins' );

		return $relative_path;
	}

	/**
	 * Tests that validate_plugin() returns 0 for a valid plugin in a subdirectory.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_success() {
		$content = "<?php\n/**\n * Plugin Name: Valid Subdirectory Plugin\n */\n";
		$plugin  = $this->create_plugin_file( 'valid-sub-plugin/valid-sub-plugin.php', $content );

		$this->assertSame( 0, validate_plugin( $plugin ) );
	}

	/**
	 * Tests that validate_plugin() returns 0 for a valid single-file plugin.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_success_single_file() {
		$content = "<?php\n/**\n * Plugin Name: Valid Single File Plugin\n */\n";
		$plugin  = $this->create_plugin_file( 'valid-single-plugin.php', $content );

		$this->assertSame( 0, validate_plugin( $plugin ) );
	}

	/**
	 * Tests that validate_plugin() returns WP_Error when the path is invalid.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_plugin_paths
	 *
	 * @param string $invalid_path Invalid plugin file path.
	 */
	public function test_validate_plugin_returns_error_for_invalid_path( $invalid_path ) {
		$result = validate_plugin( $invalid_path );

		$this->assertWPError( $result );
		$this->assertSame( 'plugin_invalid', $result->get_error_code() );
	}

	/**
	 * Data provider for test_validate_plugin_returns_error_for_invalid_path.
	 *
	 * @return array[]
	 */
	public function data_invalid_plugin_paths() {
		return array(
			'directory traversal at start' => array( '../outside/plugin.php' ),
			'nested directory traversal'   => array( 'dir/../../file.php' ),
			'windows drive path'           => array( 'c:/plugins/plugin.php' ),
			'unc network share path'       => array( '//server/share/plugin.php' ),
		);
	}

	/**
	 * Tests that validate_plugin() returns WP_Error when the plugin file does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_returns_error_when_file_not_found() {
		$result = validate_plugin( 'nonexistent-plugin/nonexistent.php' );

		$this->assertWPError( $result );
		$this->assertSame( 'plugin_not_found', $result->get_error_code() );
	}

	/**
	 * Tests that validate_plugin() returns WP_Error when the plugin file exists but has no valid plugin header.
	 *
	 * @ticket 65819
	 */
	public function test_validate_plugin_returns_error_when_missing_plugin_header() {
		$content = "<?php\n// Simple script without a Plugin Name header comment.\necho 'Hello World';\n";
		$plugin  = $this->create_plugin_file( 'no-header-plugin/no-header.php', $content );

		$result = validate_plugin( $plugin );

		$this->assertWPError( $result );
		$this->assertSame( 'no_plugin_header', $result->get_error_code() );
	}
}
