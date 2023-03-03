<?php

/**
 * @group compat
 *
 * @covers ::array_key_last
 */
class Tests_Compat_ArrayKeyLast extends WP_UnitTestCase {

	/**
	 * Test that array_key_last() is always available (either from PHP or WP).
	 *
	 * @ticket 45055
	 */
	public function test_array_key_last_availability() {
		$this->assertTrue( function_exists( 'array_key_last' ) );
	}

	/**
	 * @dataProvider data_array_key_last
	 *
	 * @ticket 45055
	 *
	 * @param bool  $expected The value of the key extracted to extracted from given array.
	 * @param array $arr      The array to get last key from.
	 */
	public function test_array_key_last( $expected, $arr ) {
		if ( ! function_exists( 'array_key_last' ) ) {
			$this->markTestSkipped( 'array_key_last() is not available.' );
		} else {
			$this->assertSame( $expected, array_key_last( $arr ) );
		}
	}

	/**
	 * Data provider for test_array_key_last().
	 *
	 * @return array
	 */
	public function data_array_key_last() {
		return array(
			'string key'  => array(
				'expected' => 'key2',
				'arr'      => array(
					'key1' => 'val1',
					'key2' => 'val2',
				),
			),
			'int key'     => array(
				'expected' => 1,
				'arr'      => array(
					99 => 'val1',
					1  => 'val2',
				),
			),
			'no key'      => array(
				'expected' => 1,
				'arr'      => array( 'val1', 'val2' ),
			),
			'multi array' => array(
				'expected' => 1,
				'arr'      => array(
					99 => array( 22 => 'val1' ),
					1  => 'val2',
				),
			),
			'mixed keys'  => array(
				'expected' => 1,
				'arr'      => array(
					'val1',
					'key2' => 'val2',
					'val3',
				),
			),
			'empty array' => array(
				'expected' => null,
				'arr'      => array(),
			),
		);
	}
}
