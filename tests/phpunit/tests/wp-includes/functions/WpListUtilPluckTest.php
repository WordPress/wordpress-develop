<?php

namespace WordPress\Tests\WP_Includes\Functions;

use ArrayIterator;
use WP_List_Util;
use WP_UnitTestCase;

/**
 * Tests for the `WP_List_Util::pluck()` method.
 *
 * @group functions
 *
 * @covers \WP_List_Util::pluck
 */
class WpListUtilPluckTest extends WP_UnitTestCase {
	/**
	 * @ticket 55300
	 *
	 * @dataProvider data_wp_list_util_pluck
	 *
	 *
	 * @param array  $target_array The array to create the list from.
	 * @param string $target_key   The key to pluck.
	 * @param array  $expected     The expected array.
	 * @param string $index_key    Optional. Field from the element to use as keys for the new array.
	 *                             Default null.
	 */
	public function test_wp_list_util_pluck( $target_array, $target_key, $expected, $index_key = null ) {
		$util   = new WP_List_Util( $target_array );
		$actual = $util->pluck( $target_key, $index_key );

		$this->assertEqualSetsWithIndex(
			$expected,
			$actual,
			'The plucked value did not match the expected value.'
		);

		$this->assertEqualSetsWithIndex(
			$expected,
			$util->get_output(),
			'::get_output() did not return the expected value.'
		);
	}

	/**
	 * Data provider for test_wp_list_util_pluck().
	 *
	 * @return array[]
	 */
	public function data_wp_list_util_pluck() {
		return array(
			'simple'        => array(
				'target_array' => array(
					0 => array( 'foo' => 'bar' ),
				),
				'target_key'   => 'foo',
				'expected'     => array( 'bar' ),
			),
			'simple_object' => array(
				'target_array' => array(
					0 => (object) array( 'foo' => 'bar' ),
				),
				'target_key'   => 'foo',
				'expected'     => array( 'bar' ),
			),
		);
	}

	/**
	 * Tests that wp_list_pluck() throws _doing_it_wrong() with invalid input.
	 *
	 * @ticket 56650
	 *
	 * @dataProvider data_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input
	 *
	 *
	 * @expectedIncorrectUsage WP_List_Util::pluck
	 *
	 * @param array $input An invalid input array.
	 */
	public function test_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input( $input ) {
		$this->assertSame( array(), wp_list_pluck( $input, 'a_field' ) );
	}

	/**
	 * Tests that wp_list_pluck() throws _doing_it_wrong() with an index key and invalid input.
	 *
	 * @ticket 56650
	 *
	 * @dataProvider data_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input
	 *
	 *
	 * @expectedIncorrectUsage WP_List_Util::pluck
	 *
	 * @param array $input An invalid input array.
	 */
	public function test_wp_list_pluck_should_throw_doing_it_wrong_with_index_key_and_invalid_input( $input ) {
		$this->assertSame( array(), wp_list_pluck( $input, 'a_field', 'an_index_key' ) );
	}

	/**
	 * Data provider that provides invalid input arrays.
	 *
	 * @return array[]
	 */
	public function data_wp_list_pluck_should_throw_doing_it_wrong_with_invalid_input() {
		return array(
			'int[] 0'                   => array( array( 0 ) ),
			'int[] 1'                   => array( array( 1 ) ),
			'int[] -1'                  => array( array( -1 ) ),
			'float[] 0.0'               => array( array( 0.0 ) ),
			'float[] 1.0'               => array( array( 1.0 ) ),
			'float[] -1.0'              => array( array( -1.0 ) ),
			'string[] and empty string' => array( array( '' ) ),
			'string[] and "0"'          => array( array( '0' ) ),
			'string[] and "1"'          => array( array( '1' ) ),
			'string[] and "-1"'         => array( array( '-1' ) ),
			'array and null'            => array( array( null ) ),
			'array and false'           => array( array( false ) ),
			'array and true'            => array( array( true ) ),
		);
	}
}
