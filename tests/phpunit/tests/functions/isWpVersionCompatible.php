<?php

/**
 * Tests the is_php_version_compatible function.
 *
 * @group functions.php
 * @covers ::is_wp_version_compatible
 */
class Tests_Functions_isWpVersionCompatible extends WP_UnitTestCase {
	/**
	 * Test is_wp_version_compatible().
	 *
	 * @dataProvider data_is_wp_version_compatible
	 *
	 * @param mixed $test_value
	 * @param bool $expected
	 *
	 * @ticket 54257
	 */
	public function test_is_wp_version_compatible( $test_value, $expected ) {
		$this->assertSame( is_wp_version_compatible( $test_value ), $expected );
	}

	/**
	 * Provides test scenarios test_is_wp_version_compatible.
	 *
	 * @return array
	 */
	function data_is_wp_version_compatible() {
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
}
