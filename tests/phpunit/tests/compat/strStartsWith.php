<?php

/**
 * @group compat
 *
 * @covers ::str_starts_with
 */
class Tests_Compat_StrStartsWith extends WP_UnitTestCase {

	/**
	 * Test that str_starts_with() is always available (either from PHP or WP).
	 *
	 * @ticket 54377
	 */
	public function test_str_starts_with_availability() {
		$this->assertTrue( function_exists( 'str_starts_with' ) );
	}

	/**
	 * @dataProvider data_str_starts_with
	 *
	 * @ticket 54377
	 *
	 * @param bool   $expected Whether or not `$haystack` is expected to start with `$needle`.
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for at the start of `$haystack`.
	 */
	public function test_str_starts_with( $expected, $haystack, $needle ) {
		if ( ! function_exists( 'str_starts_with' ) ) {
			$this->markTestSkipped( 'str_starts_with() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				str_starts_with( $haystack, $needle )
			);
		}

	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_str_starts_with() {
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
				'haystack' => 'this is a test',
				'needle'   => 'this',
			),
			'uppercase'                 => array(
				'expected' => true,
				'haystack' => 'THIS is a TEST',
				'needle'   => 'THIS',
			),
			'first letter uppercase'    => array(
				'expected' => true,
				'haystack' => 'This is a Test',
				'needle'   => 'This',
			),
			'case mismatch'             => array(
				'expected' => false,
				'haystack' => 'This is a test',
				'needle'   => 'this',
			),
			'camelCase'                 => array(
				'expected' => true,
				'haystack' => 'camelCase is the start',
				'needle'   => 'camelCase',
			),
			'null'                      => array(
				'expected' => true,
				'haystack' => 'This\x00is a null test ',
				'needle'   => 'This\x00is',
			),
			'trademark'                 => array(
				'expected' => true,
				'haystack' => 'trademark\x2122 is a null test ',
				'needle'   => 'trademark\x2122',
			),
			'not camelCase'             => array(
				'expected' => false,
				'haystack' => ' cammelcase is the start',
				'needle'   => 'cammelCase',
			),
			'missing'                   => array(
				'expected' => false,
				'haystack' => 'This is a test',
				'needle'   => 'camelCase',
			),
			'not start'                 => array(
				'expected' => false,
				'haystack' => 'This is a test extra',
				'needle'   => 'test',
			),
			'extra_space'               => array(
				'expected' => false,
				'haystack' => ' This is a test',
				'needle'   => 'This',
			),
		);
	}
}
