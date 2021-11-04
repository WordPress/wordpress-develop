<?php

/**
 * @group compat
 *
 * @covers ::str_contains
 */
class Tests_Compat_str_contains extends WP_UnitTestCase {

	/**
	 * Test that is_iterable() is always available (either from PHP or WP).
	 *
	 * @ticket 43619
	 */
	public function test_is_iterable_availability() {
		if ( ! function_exists( 'str_contains' ) ) {
			$this->markTestSkipped( 'str_contains() is not available.' );
		} else {
			$this->assertTrue( function_exists( 'str_contains' ) );
		}
	}
	/**
	 * @dataProvider str_contains_provider
	 */
	public function test_str_contains( $expected, $string, $needle ) {
		if ( ! function_exists( 'str_contains' ) ) {
			$this->markTestSkipped( 'str_contains() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				str_contains( $string, $needle )
			);
		}

	}

	public function str_contains_provider() {
		return array(
			'lowercase'              => array(
				'expected' => true,
				'string'   => 'This is a test',
				'needle'   => 'test',
			),
			'uppercase'              => array(
				'expected' => true,
				'string'   => 'This is a TEST',
				'needle'   => 'TEST',
			),
			'cammelcase'             => array(
				'expected' => true,
				'string'   => 'This is a Test',
				'needle'   => 'Test',
			),
			'first_leter_upprercase' => array(
				'expected' => true,
				'string'   => 'This is a Test',
				'needle'   => 'Test',
			),
			'not_cammelCase'         => array(
				'expected' => false,
				'string'   => 'This is a cammelcase',
				'needle'   => 'cammelCase',
			),
			'missing'                => array(
				'expected' => false,
				'string'   => 'This is a cammelcase',
				'needle'   => 'cammelCase',
			),
		);
	}
}
