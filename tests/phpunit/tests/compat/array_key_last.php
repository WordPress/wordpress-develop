<?php

/**
 * @group compat
 *
 * @covers ::array_key_last
 */
class Tests_Compat_array_key_last extends WP_UnitTestCase {

	/**
	 * Test that array_key_last() is always available (either from PHP or WP).
	 */
	public function test_array_key_last_availability() {
		if( ! function_exists( 'array_key_last' ) ) {
			$this->markTestSkipped( 'array_key_last() is not available.' );
		} else {
			$this->assertTrue( function_exists( 'array_key_last' ) );
		}
	}


	/**
	 * @dataProvider array_key_last_provider
	 */
	public function test_array_key_last( $expected, $array ) {
		if ( ! function_exists( 'array_key_last' ) ) {
			$this->markTestSkipped( 'array_key_last() is not available.' );
		} else {
			$this->assertSame(
				$expected,
				array_key_last( $array)
			);
		}

	}

	public function array_key_last_provider() {
		return array(
			'string_key'  => array(
				'expected' => 'key2',
				'array'    => array(
					'key1' => 'val1',
					'key2' => 'val2',
				),
			),
			'int_key'     => array(
				'expected' => 1,
				'array'    => array(
					99 => 'val1',
					1  => 'val2',
				),
			),
			'no_key'      => array(
				'expected' => 1,
				'array'    => array( 'val1', 'val2' ),
			),
			'multi_array' => array(
				'expected' => 1,
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
