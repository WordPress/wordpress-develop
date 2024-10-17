<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::download_package()
 */
class Admin_WpUpgrader_DownloadPackage_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::download_package()` returns early when
	 * the 'upgrader_pre_download' filter returns a non-false value.
	 *
	 * @ticket 54245
	 */
	public function test_download_package_should_exit_early_when_the_upgrader_pre_download_filter_returns_non_false() {
		self::$upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );

		add_filter(
			'upgrader_pre_download',
			static function () {
				return 'a non-false value';
			}
		);

		$result = self::$instance->download_package( 'package' );

		$this->assertSame( 'a non-false value', $result );
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` should apply
	 * 'upgrader_pre_download' filters with expected arguments.
	 *
	 * @ticket 54245
	 */
	public function test_download_package_should_apply_upgrader_pre_download_filter_with_arguments() {
		self::$upgrader_skin_mock->expects( $this->never() )->method( 'feedback' );

		add_filter(
			'upgrader_pre_download',
			function ( $reply, $package, $upgrader, $hook_extra ) {
				$this->assertFalse( $reply, '"$reply" was not false' );

				$this->assertSame(
					'package',
					$package,
					'The package file name was not "package"'
				);

				$this->assertSame(
					self::$instance,
					$upgrader,
					'The wrong WP_Upgrader instance was passed'
				);

				$this->assertSameSets(
					array( 'hook_extra' ),
					$hook_extra,
					'The "$hook_extra" array was not the expected array'
				);

				return ! $reply;
			},
			10,
			4
		);

		$result = self::$instance->download_package( 'package', false, array( 'hook_extra' ) );

		$this->assertTrue(
			$result,
			'WP_Upgrader::download_package() did not return true'
		);
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns an existing file.
	 *
	 * @ticket 54245
	 */
	public function test_download_package_should_return_an_existing_file() {
		$result = self::$instance->download_package( __FILE__ );

		$this->assertSame( __FILE__, $result );
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns a WP_Error object
	 * for an empty package.
	 *
	 * @ticket 59712
	 */
	public function test_download_package_should_return_a_wp_error_object_for_an_empty_package() {
		self::$instance->init();

		$result = self::$instance->download_package( '' );

		$this->assertWPError(
			$result,
			'WP_Upgrader::download_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'no_package',
			$result->get_error_code(),
			'Unexpected WP_Error code'
		);
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns a file with the
	 * package name in it.
	 *
	 * @ticket 54245
	 */
	public function test_download_package_should_return_a_file_with_the_package_name() {
		add_filter(
			'pre_http_request',
			static function () {
				return array( 'response' => array( 'code' => 200 ) );
			}
		);

		$result = self::$instance->download_package( 'wordpress-seo' );

		$this->assertStringContainsString( '/wordpress-seo-', $result );
	}

	/**
	 * Tests that `WP_Upgrader::download_package()` returns a package URL error
	 * as a `WP_Error` object.
	 *
	 * @ticket 54245
	 */
	public function test_download_package_should_return_a_wp_error_object() {
		self::$instance->generic_strings();

		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'response' => array(
						'code'    => 400,
						'message' => 'error',
					),
				);
			}
		);

		$result = self::$instance->download_package( 'wordpress-seo' );

		$this->assertWPError(
			$result,
			'WP_Upgrader::download_package() did not return a WP_Error object'
		);

		$this->assertSame(
			'download_failed',
			$result->get_error_code(),
			'Unexpected WP_Error code'
		);
	}
}
