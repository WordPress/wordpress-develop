<?php

/**
 * @group compat
 *
 * @ticket 54377
 *
 * @covers ::str_ends_with
 */
class Tests_Compat_str_ends_with extends WP_UnitTestCase {

	/**
	 * Test that is_iterable() is always available (either from PHP or WP).
	 *
	 * @ticket 43619
	 */
	public function test_str_ends_with_availability() {
		$this->assertTrue( function_exists( 'str_ends_with' ) );
	}
	/**
	 * @dataProvider str_ends_with_provider
	 */
	public function test_str_ends_with( $expected, $string, $needle ) {
		if ( ! function_exists( 'str_ends_with' ) ) {
			$this->markTestSkipped( 'str_ends_with() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				str_ends_with( $string, $needle )
			);
		}

	}

	/**
	 * Data provider for test_str_ends_with.
	 *
	 * @return array[]
	 */
	public function str_ends_with_provider() {
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
			'first_leter_upprercase' => array(
				'expected' => true,
				'string'   => 'This is a Test',
				'needle'   => 'Test',
			),
			'cammelCase'             => array(
				'expected' => true,
				'string'   => 'This is a cammelCase',
				'needle'   => 'cammelCase',
			),
			'null'                   => array(
				'expected' => true,
				'string'   => 'This is a null \x00test',
				'needle'   => '\x00test',
			),
			'trademark'              => array(
				'expected' => true,
				'string'   => 'This is a trademark\x2122',
				'needle'   => 'trademark\x2122',
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
			'not end'                => array(
				'expected' => false,
				'string'   => 'This is a test extra',
				'needle'   => 'test',
			),
			'extra_space'            => array(
				'expected' => false,
				'string'   => 'This is a test ',
				'needle'   => 'test',
			),

		);
	}
}
