<?php

/**
 * @group compat
 *
 * @covers ::array_key_first
 */
class Tests_Compat_array_key_first extends WP_UnitTestCase {

	/**
	 * Test that array_key_first() is always available (either from PHP or WP).
	 * @ticket 45055
	 */
	public function test_array_key_first_availability() {
		$this->assertTrue( function_exists( 'array_key_first' ) );
	}

	/**
	 * @dataProvider array_key_first_provider
	 * @ticket 45055
	 */
	public function test_array_key_first( $expected, $array ) {
		if ( ! function_exists( 'array_key_first' ) ) {
			$this->markTestSkipped( 'array_key_first() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				array_key_first( $array )
			);
		}

	}

	/**
	 * Data provider for test_array_key_first().
	 *
	 * @return array[]
	 */
	public function array_key_first_provider() {
		return array(
			'string_key'  => array(
				'expected' => 'key1',
				'array'    => array(
					'key1' => 'val1',
					'key2' => 'val2',
				),
			),
			'int_key'     => array(
				'expected' => 99,
				'array'    => array(
					99 => 'val1',
					1  => 'val2',
				),
			),
			'no_key'      => array(
				'expected' => 0,
				'array'    => array( 'val1', 'val2' ),
			),
			'multi_array' => array(
				'expected' => 99,
				'array'    => array(
					99 => array( 22 => 'val1' ),
					1  => 'val2',
				),
			),
			'empty_array' => array(
				'expected' => null,
				'array'    => array(),
			),
		);
	}

}
