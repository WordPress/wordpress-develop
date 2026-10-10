<?php

/**
 * Unit tests for `wp_debug_mode()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 5.9.0
 *
 * @group load
 * @group wp-debug-mode
 *
 * @covers ::wp_debug_mode
 */
class Test_WP_Debug_Mode extends WP_UnitTestCase {
	/**
	 * Original error_reporting level.
	 *
	 * @var int
	 */
	private $original_error_reporting;

	/**
	 * Original display_errors setting.
	 *
	 * @var string|false
	 */
	private $original_display_errors;

	/**
	 * Original log_errors setting.
	 *
	 * @var string|false
	 */
	private $original_log_errors;

	/**
	 * Original error_log setting.
	 *
	 * @var string|false
	 */
	private $original_error_log;

	public function set_up() {
		parent::set_up();

		$this->original_error_reporting = error_reporting();
		$this->original_display_errors  = ini_get( 'display_errors' );
		$this->original_log_errors      = ini_get( 'log_errors' );
		$this->original_error_log       = ini_get( 'error_log' );
	}

	public function tear_down() {
		error_reporting( $this->original_error_reporting );
		ini_set( 'display_errors', $this->original_display_errors );
		ini_set( 'log_errors', $this->original_log_errors );
		ini_set( 'error_log', $this->original_error_log );

		parent::tear_down();
	}

	/**
	 * Test: `wp_debug_mode()` should log, but not display, errors for `ms-files.php`.
	 *
	 * @ticket 53493
	 *
	 * @since 5.9.0
	 */
	public function test_ms_files_logs_but_doesnt_display_errors() {
		/*
		 * Global constants can't be mocked in PHPUnit, so this can only run with the expected
		 * values already set in `wp-tests-config.php`. Unfortunately, that means it won't run in
		 * automated workflows, but it's still useful when testing locally.
		 *
		 * It may be possible to enable automated workflows by mocking `define()`, or by setting up
		 * addition automated flows that initialize the tests with different values for the constants.
		 * At the moment, though, neither of those seem to provide enough benefit to justify the time
		 * investment.
		 *
		 * @link https://theaveragedev.com/mocking-constants-in-tests/
		 */
		if ( true !== WP_DEBUG || true !== WP_DEBUG_DISPLAY || true !== WP_DEBUG_LOG ) {
			$this->markTestSkipped( 'Test requires setting `WP_DEBUG_*` constants in `wp-tests-config.php` to expected values.' );
		}

		// `display_errors` should be _on_ because of `WP_DEBUG_DISPLAY`.
		wp_debug_mode();

		$this->assertSame( E_ALL, (int) ini_get( 'error_reporting' ) );
		$this->assertSame( '1', ini_get( 'display_errors' ) );
		$this->assertSame( '1', ini_get( 'log_errors' ) );
		$this->assertStringContainsString( 'debug.log', ini_get( 'error_log' ) );

		// `display_errors` should be _off_ now, because of `MS_FILES_REQUEST`.
		define( 'MS_FILES_REQUEST', true );
		wp_debug_mode();

		$this->assertSame( E_ALL, (int) ini_get( 'error_reporting' ) );
		$this->assertSame( '0', ini_get( 'display_errors' ) );
		$this->assertSame( '1', ini_get( 'log_errors' ) );
		$this->assertStringContainsString( 'debug.log', ini_get( 'error_log' ) );
	}
}
