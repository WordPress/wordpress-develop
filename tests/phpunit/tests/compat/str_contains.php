<?php

/**
 * @group compat
 *
 * @covers ::str_contains
 */
class Tests_Compat_str_contains extends WP_UnitTestCase {

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
	 * @param bool $expected Whether or not `$haystack` is expected to contain `$needle`.
	 * @param string $haystack The string to search in.
	 * @param string $needle The substring to search for in `$haystack`.
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
	 * Data provider for test_str_contains.
	 *
	 * @return array
	 */
	public function data_str_contains() {
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
			'camelcase'             => array(
				'expected' => true,
				'haystack' => 'This is a Test',
				'needle'   => 'Test',
			),
			'first_letter_uppercase' => array(
				'expected' => true,
				'haystack' => 'This is a Test',
				'needle'   => 'Test',
			),
			'not_camelCase'         => array(
				'expected' => false,
				'haystack' => 'This is a camelcase',
				'needle'   => 'camelCase',
			),
			'missing'                => array(
				'expected' => false,
				'haystack' => 'This is a camelcase',
				'needle'   => 'camelCase',
			),
		);
	}
}
