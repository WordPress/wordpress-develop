<?php
/**
 * Unit tests for `wp_get_development_mode()`.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 6.3.0
 *
 * @group load
 *
 * @covers ::wp_get_development_mode
 * @covers ::wp_is_development_mode
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
	 * Tests that `wp_is_development_mode()` returns expected results.
	 *
	 * @ticket 57487
	 * @dataProvider data_wp_is_development_mode
	 */
	public function test_wp_is_development_mode( $current, $given, $expected ) {
		global $_wp_tests_development_mode;

		$_wp_tests_development_mode = $current;

		if ( $expected ) {
			$this->assertTrue( wp_is_development_mode( $given ), "{$given} is expected to pass in {$current} mode" );
		} else {
			$this->assertFalse( wp_is_development_mode( $given ), "{$given} is expected to fail in {$current} mode" );
		}
	}

	/**
	 * Data provider that returns test scenarios for the `test_wp_is_development_mode()` method.
	 *
	 * @return array[]
	 */
	public function data_wp_is_development_mode() {
		return array(
			'core mode, testing for core'              => array(
				'core',
				'core',
				true,
			),
			'plugin mode, testing for plugin'          => array(
				'plugin',
				'plugin',
				true,
			),
			'theme mode, testing for theme'            => array(
				'theme',
				'theme',
				true,
			),
			'core mode, testing for plugin'            => array(
				'core',
				'plugin',
				false,
			),
			'core mode, testing for theme'             => array(
				'core',
				'theme',
				false,
			),
			'plugin mode, testing for core'            => array(
				'plugin',
				'core',
				false,
			),
			'plugin mode, testing for theme'           => array(
				'plugin',
				'theme',
				false,
			),
			'theme mode, testing for core'             => array(
				'theme',
				'core',
				false,
			),
			'theme mode, testing for plugin'           => array(
				'theme',
				'plugin',
				false,
			),
			'all mode, testing for core'               => array(
				'all',
				'core',
				true,
			),
			'all mode, testing for plugin'             => array(
				'all',
				'plugin',
				true,
			),
			'all mode, testing for theme'              => array(
				'all',
				'theme',
				true,
			),
			'all mode, testing for all'                => array(
				'all',
				'all',
				true,
			),
			'all mode, testing for non-standard value' => array(
				'all',
				'random',
				true,
			),
			'invalid mode, testing for core'           => array(
				'invalid',
				'core',
				false,
			),
			'invalid mode, testing for plugin'         => array(
				'invalid',
				'plugin',
				false,
			),
			'invalid mode, testing for theme'          => array(
				'invalid',
				'theme',
				false,
			),
		);
	}
}
