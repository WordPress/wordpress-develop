<?php

/**
 * Tests the is_php_version_compatible function.
 *
 * @group functions.php
 * @covers ::is_wp_version_compatible
 */
class Tests_Functions_isWpVersionCompatible extends WP_UnitTestCase {
	/**
	 * Provides test scenarios for all possible scenarios in wp_validate_boolean().
	 *
	 * @return array
	 */
	function data_provider() {
		$wp_version = get_bloginfo( 'version' );

		$more = explode( '.', $wp_version );
		$less = $more;

		-- $less[0];
		++ $more[0];

		return array(
			'greater' => array(
				'test_value' => implode( '.', $more ),
				'expected'   => false,
			),
			'same'    => array(
				'test_value' => $wp_version,
				'expected'   => true,
			),
			'less'    => array(
				'test_value' => implode( '.', $less ),
				'expected'   => true,
			),
		);
	}

	/**
	 * Test wp_validate_boolean().
	 *
	 * @dataProvider data_provider
	 *
	 * @param mixed $test_value
	 * @param bool $expected
	 *
	 * @ticket 30238
	 * @ticket 39868
	 */
	public function test_php_version_compatible( $test_value, $expected ) {
		$this->assertSame( is_wp_version_compatible( $test_value ), $expected );
	}
}
