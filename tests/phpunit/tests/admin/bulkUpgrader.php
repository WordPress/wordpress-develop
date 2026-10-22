<?php
/**
 * Tests for `Plugin_Upgrader::bulk_upgrade()` and `Theme_Upgrader::bulk_upgrade()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';

/**
 * Test skin for capturing upgrader output silently.
 */
class Bulk_Upgrader_Test_Skin extends WP_Upgrader_Skin {

	public function header() {}
	public function footer() {}
	public function bulk_header() {}
	public function bulk_footer() {}
	public function before( $title = '' ) {}
	public function after( $title = '' ) {}
	public function error( $errors ) {}
	public function feedback( $feedback, ...$args ) {}

	public function request_filesystem_credentials( $error = false, $context = '', $allow_relaxed_file_ownership = false ) {
		return true;
	}
}

/**
 * Testable Plugin_Upgrader that mocks fs_connect and captures run() arguments.
 */
class Testable_Plugin_Upgrader extends Plugin_Upgrader {

	/**
	 * Captured arguments passed to run().
	 *
	 * @var array[]
	 */
	public $run_calls = array();

	public function fs_connect( $directories = array(), $allow_relaxed_file_ownership = false ) {
		return true;
	}

	public function run( $options ) {
		$this->run_calls[] = $options;
		return true;
	}
}

/**
 * Testable Theme_Upgrader that mocks fs_connect and captures run() arguments.
 */
class Testable_Theme_Upgrader extends Theme_Upgrader {

	/**
	 * Captured arguments passed to run().
	 *
	 * @var array[]
	 */
	public $run_calls = array();

	public function fs_connect( $directories = array(), $allow_relaxed_file_ownership = false ) {
		return true;
	}

	public function run( $options ) {
		$this->run_calls[] = $options;
		return true;
	}
}

/**
 * Tests the concurrent bulk upgrade functionality.
 *
 * @group admin
 * @group upgrade
 *
 * @covers Plugin_Upgrader::bulk_upgrade
 * @covers Theme_Upgrader::bulk_upgrade
 */
class Tests_Admin_BulkUpgrader extends WP_UnitTestCase {

	/**
	 * Clean up filters and transients after each test.
	 */
	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );

		parent::tear_down();
	}

	/**
	 * Tests that Plugin_Upgrader::bulk_upgrade() pre-downloads packages concurrently
	 * and supplies the local file path to run().
	 *
	 * @ticket 37459
	 */
	public function test_plugin_bulk_upgrade_uses_concurrent_pre_downloads() {
		$downloaded_files = array();

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) use ( &$downloaded_files ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], 'PK...' );
					$downloaded_files[] = $parsed_args['filename'];
				}

				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/zip',
					),
				);
			},
			10,
			3
		);

		$plugin_a = 'hello.php';
		$plugin_b = 'internationalized-plugin.php';

		$update_plugins           = new stdClass();
		$update_plugins->response = array(
			$plugin_a => (object) array(
				'package'     => 'https://downloads.wordpress.org/plugin/alpha.1.1.zip',
				'new_version' => '1.1',
			),
			$plugin_b => (object) array(
				'package'     => 'https://downloads.wordpress.org/plugin/beta.2.0.zip',
				'new_version' => '2.0',
			),
		);
		set_site_transient( 'update_plugins', $update_plugins );

		$skin     = new Bulk_Upgrader_Test_Skin();
		$upgrader = new Testable_Plugin_Upgrader( $skin );

		$results = $upgrader->bulk_upgrade( array( $plugin_a, $plugin_b ) );

		$this->assertIsArray( $results );
		$this->assertCount( 2, $upgrader->run_calls );

		// The packages passed to run() should be local file paths, not the remote URLs.
		$package_a = $upgrader->run_calls[0]['package'];
		$package_b = $upgrader->run_calls[1]['package'];

		$this->assertNotSame( 'https://downloads.wordpress.org/plugin/alpha.1.1.zip', $package_a );
		$this->assertNotSame( 'https://downloads.wordpress.org/plugin/beta.2.0.zip', $package_b );
		$this->assertFileExists( $package_a );
		$this->assertFileExists( $package_b );

		// Clean up created files.
		foreach ( $downloaded_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Tests that Theme_Upgrader::bulk_upgrade() pre-downloads packages concurrently
	 * and supplies the local file path to run().
	 *
	 * @ticket 37459
	 */
	public function test_theme_bulk_upgrade_uses_concurrent_pre_downloads() {
		$downloaded_files = array();

		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) use ( &$downloaded_files ) {
				if ( ! empty( $parsed_args['filename'] ) ) {
					file_put_contents( $parsed_args['filename'], 'PK...' );
					$downloaded_files[] = $parsed_args['filename'];
				}

				return array(
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'headers'  => array(
						'content-type' => 'application/zip',
					),
				);
			},
			10,
			3
		);

		$theme_a = 'twentytwentyfive';
		$theme_b = 'twentytwentyfour';

		$update_themes           = new stdClass();
		$update_themes->response = array(
			$theme_a => array(
				'package'     => 'https://downloads.wordpress.org/theme/twentytwentyfive.1.1.zip',
				'new_version' => '1.1',
			),
			$theme_b => array(
				'package'     => 'https://downloads.wordpress.org/theme/twentytwentyfour.1.2.zip',
				'new_version' => '1.2',
			),
		);
		set_site_transient( 'update_themes', $update_themes );

		$skin     = new Bulk_Upgrader_Test_Skin();
		$upgrader = new Testable_Theme_Upgrader( $skin );

		$results = $upgrader->bulk_upgrade( array( $theme_a, $theme_b ) );

		$this->assertIsArray( $results );
		$this->assertCount( 2, $upgrader->run_calls );

		// The packages passed to run() should be local file paths, not the remote URLs.
		$package_a = $upgrader->run_calls[0]['package'];
		$package_b = $upgrader->run_calls[1]['package'];

		$this->assertNotSame( 'https://downloads.wordpress.org/theme/twentytwentyfive.1.1.zip', $package_a );
		$this->assertNotSame( 'https://downloads.wordpress.org/theme/twentytwentyfour.1.2.zip', $package_b );
		$this->assertFileExists( $package_a );
		$this->assertFileExists( $package_b );

		// Clean up created files.
		foreach ( $downloaded_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Tests that when a batch download fails for an item, the upgrader falls back to the original package URL.
	 *
	 * @ticket 37459
	 */
	public function test_plugin_bulk_upgrade_falls_back_to_url_on_download_failure() {
		add_filter(
			'pre_http_request',
			static function ( $response, $parsed_args, $url ) {
				return array(
					'response' => array(
						'code'    => 500,
						'message' => 'Internal Server Error',
					),
				);
			},
			10,
			3
		);

		$plugin_slug              = 'hello.php';
		$update_plugins           = new stdClass();
		$update_plugins->response = array(
			$plugin_slug => (object) array(
				'package' => 'https://downloads.wordpress.org/plugin/error-plugin.1.0.zip',
			),
		);
		set_site_transient( 'update_plugins', $update_plugins );

		$skin     = new Bulk_Upgrader_Test_Skin();
		$upgrader = new Testable_Plugin_Upgrader( $skin );

		$upgrader->bulk_upgrade( array( $plugin_slug ) );

		$this->assertCount( 1, $upgrader->run_calls );
		// When batch download failed, it falls back to the remote URL for normal sequential handling.
		$this->assertSame(
			'https://downloads.wordpress.org/plugin/error-plugin.1.0.zip',
			$upgrader->run_calls[0]['package']
		);
	}
}
