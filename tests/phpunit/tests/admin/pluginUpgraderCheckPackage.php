<?php

/**
 * Tests for Plugin_Upgrader::check_package().
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers Plugin_Upgrader::check_package
 */
class Tests_Admin_PluginUpgraderCheckPackage extends WP_UnitTestCase {

	/**
	 * The name of the temporary package directory in the content directory.
	 *
	 * @var string
	 */
	const PACKAGE = 'check-package-test';

	/**
	 * The upgrader.
	 *
	 * @var Plugin_Upgrader
	 */
	private $upgrader;

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
	 * Loads the upgrader classes.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	}

	/**
	 * Sets up the upgrader and a filesystem that maps the content directory to itself.
	 */
	public function set_up() {
		parent::set_up();

		$this->remove_package();

		$this->had_filesystem      = array_key_exists( 'wp_filesystem', $GLOBALS );
		$this->original_filesystem = $this->had_filesystem ? $GLOBALS['wp_filesystem'] : null;

		$this->set_filesystem_content_dir( trailingslashit( WP_CONTENT_DIR ) );

		$this->upgrader = new Plugin_Upgrader( $this->createMock( 'WP_Upgrader_Skin' ) );
		$this->upgrader->generic_strings();
	}

	/**
	 * Restores the filesystem global and removes the package directory.
	 */
	public function tear_down() {
		if ( $this->had_filesystem ) {
			$GLOBALS['wp_filesystem'] = $this->original_filesystem;
		} else {
			unset( $GLOBALS['wp_filesystem'] );
		}

		$this->remove_package();

		parent::tear_down();
	}

	/**
	 * Tests that an error source is returned unchanged and the uploaded plugin data is reset.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_error_source_and_reset_plugin_data() {
		$this->upgrader->new_plugin_data = array( 'Name' => 'Previous Plugin' );

		$source = new WP_Error( 'download_failed', 'Download failed.' );

		$this->assertSame( $source, $this->upgrader->check_package( $source ), 'The error should be returned.' );
		$this->assertSame( array(), $this->upgrader->new_plugin_data, 'The uploaded plugin data should be reset.' );
	}

	/**
	 * Tests that a source that is not a local directory is returned unchanged.
	 *
	 * @ticket 65819
	 */
	public function test_should_return_source_when_directory_does_not_exist() {
		$source = trailingslashit( WP_CONTENT_DIR ) . self::PACKAGE . '/';

		$this->assertSame( $source, $this->upgrader->check_package( $source ) );
	}

	/**
	 * Tests that a package without a valid plugin is rejected.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_packages_without_plugin
	 *
	 * @param array $files The package files, keyed by file name.
	 */
	public function test_should_reject_package_without_plugin( $files ) {
		$source = $this->create_package( $files );

		$result = $this->upgrader->check_package( $source );

		$this->assertWPError( $result, 'The package should be rejected.' );
		$this->assertSame( 'incompatible_archive_no_plugins', $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'The package could not be installed.', $result->get_error_message(), 'The error message should match.' );
		$this->assertSame( 'No valid plugins were found.', $result->get_error_data(), 'The error data should explain why.' );
		$this->assertSame( array(), $this->upgrader->new_plugin_data, 'No plugin data should be stored.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_packages_without_plugin() {
		return array(
			'empty package'                => array( array() ),
			'no PHP files'                 => array( array( 'readme.txt' => "Plugin Name: Not A PHP File\n" ) ),
			'PHP file without plugin name' => array( array( 'functions.php' => "<?php\n// No headers.\n" ) ),
			'plugin header in a subfolder' => array( array( 'includes/plugin.php' => "<?php\n/**\n * Plugin Name: Nested Plugin\n */\n" ) ),
		);
	}

	/**
	 * Tests that a package with a valid plugin is accepted and its data is stored.
	 *
	 * @ticket 65819
	 */
	public function test_should_accept_package_with_plugin() {
		$source = $this->create_package(
			array(
				'a-functions.php' => "<?php\n// No headers.\n",
				'b-plugin.php'    => "<?php\n/**\n * Plugin Name: Check Package Plugin\n * Version: 1.2.3\n * Requires at least: 5.0\n * Requires PHP: 7.4\n */\n",
				'c-other.php'     => "<?php\n/**\n * Plugin Name: Other Plugin\n */\n",
			)
		);

		$this->assertSame( $source, $this->upgrader->check_package( $source ), 'The source should be returned.' );
		$this->assertSame( 'Check Package Plugin', $this->upgrader->new_plugin_data['Name'], 'The first plugin should be stored.' );
		$this->assertSame( '1.2.3', $this->upgrader->new_plugin_data['Version'], 'The plugin version should be stored.' );
	}

	/**
	 * Tests that a remote filesystem path is mapped to the local content directory.
	 *
	 * @ticket 65819
	 */
	public function test_should_map_remote_path_to_local_content_directory() {
		$this->create_package( array( 'plugin.php' => "<?php\n/**\n * Plugin Name: Remote Plugin\n */\n" ) );
		$this->set_filesystem_content_dir( '/remote/wp-content/' );

		$source = '/remote/wp-content/' . self::PACKAGE . '/';

		$this->assertSame( $source, $this->upgrader->check_package( $source ), 'The remote source should be returned.' );
		$this->assertSame( 'Remote Plugin', $this->upgrader->new_plugin_data['Name'], 'The plugin should be read from the local directory.' );
	}

	/**
	 * Tests that a plugin whose requirements are not met is rejected.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_unmet_requirements
	 *
	 * @param string $headers  The requirement headers.
	 * @param string $code     The expected error code.
	 * @param string $reason   The expected reason, as a format string for PHP_VERSION and the WordPress version.
	 */
	public function test_should_reject_plugin_with_unmet_requirements( $headers, $code, $reason ) {
		$source = $this->create_package( array( 'plugin.php' => "<?php\n/**\n * Plugin Name: Demanding Plugin\n{$headers} */\n" ) );

		$result = $this->upgrader->check_package( $source );

		$this->assertWPError( $result, 'The package should be rejected.' );
		$this->assertSame( $code, $result->get_error_code(), 'The error code should match.' );
		$this->assertSame( 'The package could not be installed.', $result->get_error_message(), 'The error message should match.' );
		$this->assertSame( sprintf( $reason, PHP_VERSION, wp_get_wp_version() ), $result->get_error_data(), 'The error data should explain why.' );
		$this->assertSame( 'Demanding Plugin', $this->upgrader->new_plugin_data['Name'], 'The plugin data should still be stored.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unmet_requirements() {
		$php = 'The PHP version on your server is %1$s, however the uploaded plugin requires 99.0.';
		$wp  = 'Your WordPress version is %2$s, however the uploaded plugin requires 99.0.';

		return array(
			'PHP'                                => array( " * Requires PHP: 99.0\n", 'incompatible_php_required_version', $php ),
			'WordPress'                          => array( " * Requires at least: 99.0\n", 'incompatible_wp_required_version', $wp ),
			'PHP and WordPress, PHP is reported' => array( " * Requires PHP: 99.0\n * Requires at least: 99.0\n", 'incompatible_php_required_version', $php ),
		);
	}

	/**
	 * Creates the package directory with the given files.
	 *
	 * @param array $files The file contents, keyed by file name relative to the package.
	 * @return string The package directory, with a trailing slash.
	 */
	private function create_package( $files ) {
		$dir = trailingslashit( WP_CONTENT_DIR ) . self::PACKAGE . '/';

		$this->assertTrue( mkdir( $dir ), 'The package directory should be created.' );

		foreach ( $files as $name => $contents ) {
			$file = $dir . $name;

			if ( ! is_dir( dirname( $file ) ) ) {
				$this->assertTrue( mkdir( dirname( $file ), 0777, true ), 'The package subdirectory should be created.' );
			}

			$this->assertNotFalse( file_put_contents( $file, $contents ), 'The package file should be written.' );
		}

		return $dir;
	}

	/**
	 * Removes the package directory.
	 */
	private function remove_package() {
		$dir = trailingslashit( WP_CONTENT_DIR ) . self::PACKAGE;

		if ( is_dir( $dir ) ) {
			$this->rmdir( $dir );
			$this->delete_folders( $dir );
		}
	}

	/**
	 * Sets a filesystem whose content directory is the given path.
	 *
	 * @param string $content_dir The content directory path of the filesystem.
	 */
	private function set_filesystem_content_dir( $content_dir ) {
		$filesystem = $this->createMock( 'WP_Filesystem_Base' );
		$filesystem->method( 'wp_content_dir' )->willReturn( $content_dir );

		$GLOBALS['wp_filesystem'] = $filesystem;
	}
}
