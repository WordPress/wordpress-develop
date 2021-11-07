<?php

/**
 * @group compat
 *
 *
 * @covers ::str_ends_with
 */
class Tests_Compat_str_ends_with extends WP_UnitTestCase {

	/**
	 * Test that str_ends_with() is always available (either from PHP or WP).
	 * @ticket 54377
	 */
	public function test_str_ends_with_availability() {
		$this->assertTrue( function_exists( 'str_ends_with' ) );
	}
	/**
	 * @ticket 54377
	 * @dataProvider data_str_ends_with
	 * @param bool $expected Whether or not `$haystack` is expected to end with `$needle`.
	 * @param string $haystack The string to search in.
	 * @param string $needle The substring to search for at the end of `$haystack`.
	 */
	public function test_str_ends_with( $expected, $haystack, $needle ) {
		if ( ! function_exists( 'str_ends_with' ) ) {
			$this->markTestSkipped( 'str_ends_with() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				str_ends_with( $haystack, $needle )
			);
		}

	}

	/**
	 * Data provider for test_str_ends_with.
	 *
	 * @return array[]
	 */
	public function data_str_ends_with() {
		return array(
			'lowercase'              => array(
				'expected' => true,
				'haystack' => 'This is a test',
				'needle'   => 'test',
			),
			'uppercase'              => array(
				'expected' => true,
				'haystack' => 'This is a TEST',
				'needle'   => 'TEST',
			),
			'first_leter_upprercase' => array(
				'expected' => true,
				'haystack' => 'This is a Test',
				'needle'   => 'Test',
			),
			'cammelCase'             => array(
				'expected' => true,
				'haystack' => 'This is a cammelCase',
				'needle'   => 'cammelCase',
			),
			'null'                   => array(
				'expected' => true,
				'haystack' => 'This is a null \x00test',
				'needle'   => '\x00test',
			),
			'trademark'              => array(
				'expected' => true,
				'haystack' => 'This is a trademark\x2122',
				'needle'   => 'trademark\x2122',
			),
			'not_cammelCase'         => array(
				'expected' => false,
				'haystack' => 'This is a cammelcase',
				'needle'   => 'cammelCase',
			),
			'missing'                => array(
				'expected' => false,
				'haystack' => 'This is a cammelcase',
				'needle'   => 'cammelCase',
			),
			'not end'                => array(
				'expected' => false,
				'haystack' => 'This is a test extra',
				'needle'   => 'test',
			),
			'extra_space'            => array(
				'expected' => false,
				'haystack' => 'This is a test ',
				'needle'   => 'test',
			),

		);
	}
}
