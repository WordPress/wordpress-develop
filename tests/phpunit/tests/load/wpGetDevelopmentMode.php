<?php
/**
 * Unit tests for `wp_get_development_mode()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.3.0
 *
 * @group load.php
 * @covers ::wp_get_development_mode
 */
class Test_WP_Get_Development_Mode extends WP_UnitTestCase {

	/**
	 * Tests that `wp_get_development_mode()` returns the value of the `WP_DEVELOPMENT_MODE` constant.
	 *
	 * @ticket 57487
	 */
	public function test_wp_get_development_mode_constant() {
		$this->assertSame( WP_DEVELOPMENT_MODE, wp_get_development_mode() );
	}

	/**
	 * Tests that `wp_get_development_mode()` allows test overrides.
	 *
	 * @ticket 57487
	 */
	public function test_wp_get_development_mode_test_overrides() {
		global $_wp_tests_development_mode;

		$_wp_tests_development_mode = 'plugin';
		$this->assertSame( 'plugin', wp_get_development_mode() );
	}

	/**
	 * Tests that `wp_get_development_mode()` ignores invalid filter values.
	 *
	 * @ticket 57487
	 */
	public function test_wp_get_development_mode_filter_invalid_value() {
		global $_wp_tests_development_mode;

		$_wp_tests_development_mode = 'invalid';
		$this->assertSame( '', wp_get_development_mode() );
	}
}
