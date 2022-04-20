<?php

/**
 * Tests the is_php_version_compatible function.
 *
 * @group functions.php
 * @covers ::is_php_version_compatible
 */
class Tests_Functions_isPhpVersionCompatible extends WP_UnitTestCase {
	/**
	 * Tests is_php_version_compatible().
	 *
	 * @dataProvider data_is_php_version_compatible
	 *
	 * @param mixed $test_value
	 * @param bool $expected
	 *
	 * @ticket 54257
	 */
	public function test_is_php_version_compatible( $test_value, $expected ) {
		$this->assertSame( is_php_version_compatible( $test_value ), $expected );
	}

	/**
	 * Provides test scenarios for test_php_version_compatible.
	 *
	 * @return array
	 */
	function data_is_php_version_compatible() {
		$php_version = phpversion();

		$more = explode( '.', $php_version );
		$less = $more;

		-- $less[ count( $less ) - 1 ];
		++ $more[ count( $less ) - 1 ];

		return array(
			'greater' => array(
				'test_value' => implode( '.', $more ),
				'expected'   => false,
			),
			'same'    => array(
				'test_value' => $php_version,
				'expected'   => true,
			),
			'less'    => array(
				'test_value' => implode( '.', $less ),
				'expected'   => true,
			),
		);
	}
}
