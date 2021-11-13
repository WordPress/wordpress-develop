<?php

/**
 * @group compat
 *
 * @covers ::str_contains
 */
class Tests_Compat_strContains extends WP_UnitTestCase {

	/**
	 * Test that str_contains() is always available (either from PHP or WP).
	 *
	 * @ticket 49652
	 */
	public function test_is_str_contains_availability() {
		$this->assertTrue( function_exists( 'str_contains' ) );
	}

	/**
	 * @dataProvider data_str_contains
	 *
	 * @ticket 49652
	 *
	 * @param bool   $expected Whether or not `$haystack` is expected to contain `$needle`.
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in `$haystack`.
	 */
	public function test_str_contains( $expected, $haystack, $needle ) {
		if ( ! function_exists( 'str_contains' ) ) {
			$this->markTestSkipped( 'str_contains() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				str_contains( $haystack, $needle )
			);
		}

	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_str_contains() {
		return array(
			'empty needle'              => array(
				'expected' => true,
				'haystack' => 'This is a Test',
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
			'start of string'           => array(
				'expected' => true,
				'haystack' => 'This is a Test',
				'needle'   => 'This',
			),
			'middle of string'          => array(
				'expected' => true,
				'haystack' => 'The needle in middle of string.',
				'needle'   => 'middle',
			),
			'end of string'             => array(
				'expected' => true,
				'string'   => 'The needle is at end.',
				'needle'   => 'end',
			),
			'lowercase'                 => array(
				'expected' => true,
				'string'   => 'This is a test',
				'needle'   => 'test',
			),
			'uppercase'                 => array(
				'expected' => true,
				'string'   => 'This is a TEST',
				'needle'   => 'TEST',
			),
			'camelCase'                 => array(
				'expected' => true,
				'string'   => 'String contains camelCase.',
				'needle'   => 'camelCase',
			),
			'with hyphen'               => array(
				'expected' => true,
				'string'   => 'String contains foo-bar needle.',
				'needle'   => 'foo-bar',
			),
			'missing'                   => array(
				'expected' => false,
				'haystack' => 'This is a camelcase',
				'needle'   => 'camelCase',
			),
		);
	}
}
