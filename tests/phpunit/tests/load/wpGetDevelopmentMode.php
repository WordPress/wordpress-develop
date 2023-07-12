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
 * @covers ::wp_in_development_mode
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

	/**
	 * Tests that `wp_in_development_mode()` returns expected results.
	 *
	 * @ticket 57487
	 * @dataProvider data_wp_in_development_mode
	 */
	public function test_wp_in_development_mode( $current, $given, $expected ) {
		global $_wp_tests_development_mode;

		$_wp_tests_development_mode = $current;

		if ( $expected ) {
			$this->assertTrue( wp_in_development_mode( $given ) );
		} else {
			$this->assertFalse( wp_in_development_mode( $given ) );
		}
	}

	/**
	 * Data provider that returns test scenarios for the `test_wp_in_development_mode()` method.
	 *
	 * @return array Test scenarios, each one with 3 entries for currently set development mode (string), given
	 *               development mode (string), and expected result (boolean).
	 */
	public function data_wp_in_development_mode() {
		return array(
			array(
				'core',
				'core',
				true,
			),
			array(
				'plugin',
				'plugin',
				true,
			),
			array(
				'theme',
				'theme',
				true,
			),
			array(
				'core',
				'plugin',
				false,
			),
			array(
				'core',
				'theme',
				false,
			),
			array(
				'plugin',
				'core',
				false,
			),
			array(
				'plugin',
				'theme',
				false,
			),
			array(
				'theme',
				'core',
				false,
			),
			array(
				'theme',
				'plugin',
				false,
			),
			array(
				'all',
				'core',
				true,
			),
			array(
				'all',
				'plugin',
				true,
			),
			array(
				'all',
				'theme',
				true,
			),
			array(
				'all',
				'all',
				true,
			),
			array(
				'all',
				'random',
				true,
			),
			array(
				'invalid',
				'core',
				false,
			),
			array(
				'invalid',
				'plugin',
				false,
			),
			array(
				'invalid',
				'theme',
				false,
			),
		);
	}
}
