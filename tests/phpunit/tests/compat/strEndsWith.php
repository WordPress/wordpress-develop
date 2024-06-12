<?php

/**
 * @group compat
 *
 * @covers ::str_ends_with
 */
class Tests_Compat_StrEndsWith extends WP_UnitTestCase {

	/**
	 * Test that str_ends_with() is always available (either from PHP or WP).
	 *
	 * @ticket 54377
	 */
	public function test_str_ends_with_availability() {
		$this->assertTrue( function_exists( 'str_ends_with' ) );
	}

	/**
	 * @dataProvider data_str_ends_with
	 *
	 * @ticket 54377
	 *
	 * @param bool   $expected Whether or not `$haystack` is expected to end with `$needle`.
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for at the end of `$haystack`.
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
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_str_ends_with() {
		return array(
			'empty needle'              => array(
				'expected' => true,
				'haystack' => 'This is a test',
				'needle'   => '',
			),
			'empty haystack and needle' => array(
				'expected' => true,
				'haystack' => '',
				'needle'   => '',
			),
			'empty haystack'            => array(
				'expected' => false,
				'haystack' => '',
				'needle'   => 'test',
			),
			'lowercase'                 => array(
				'expected' => true,
				'haystack' => 'This is a test',
				'needle'   => 'test',
			),
			'uppercase'                 => array(
				'expected' => true,
				'haystack' => 'This is a TEST',
				'needle'   => 'TEST',
			),
			'first letter uppercase'    => array(
				'expected' => true,
				'haystack' => 'This is a Test',
				'needle'   => 'Test',
			),
			'camelCase'                 => array(
				'expected' => true,
				'haystack' => 'This is a camelCase',
				'needle'   => 'camelCase',
			),
			'null'                      => array(
				'expected' => true,
				'haystack' => 'This is a null \x00test',
				'needle'   => '\x00test',
			),
			'trademark'                 => array(
				'expected' => true,
				'haystack' => 'This is a trademark\x2122',
				'needle'   => 'trademark\x2122',
			),
			'not camelCase'             => array(
				'expected' => false,
				'haystack' => 'This is a cammelcase',
				'needle'   => 'cammelCase',
			),
			'missing'                   => array(
				'expected' => false,
				'haystack' => 'This is a cammelcase',
				'needle'   => 'cammelCase',
			),
			'not end'                   => array(
				'expected' => false,
				'haystack' => 'This is a test extra',
				'needle'   => 'test',
			),
			'extra space'               => array(
				'expected' => false,
				'haystack' => 'This is a test ',
				'needle'   => 'test',
			),

		);
	}
}
