<?php

/**
 * Tests for the wp_parse_list() function.
 *
 * @group functions
 *
 * @covers ::wp_parse_list
 */
class Tests_Functions_wpParseList extends WP_UnitTestCase {

	/**
	 * @ticket 43977
	 * @dataProvider data_wp_parse_list
	 */
	public function test_wp_parse_list( $expected, $actual ) {
		$this->assertSame( $expected, array_values( wp_parse_list( $actual ) ) );
	}

	public function data_wp_parse_list() {
		return array(
			array( array( '1', '2', '3', '4' ), '1,2,3,4' ),
			array( array( 'apple', 'banana', 'carrot', 'dog' ), 'apple,banana,carrot,dog' ),
			array( array( '1', '2', 'apple', 'banana' ), '1,2,apple,banana' ),
			array( array( '1', '2', 'apple', 'banana' ), '1, 2,apple,banana' ),
			array( array( '1', '2', 'apple', 'banana' ), '1,2,apple,,banana' ),
			array( array( '1', '2', 'apple', 'banana' ), ',1,2,apple,banana' ),
			array( array( '1', '2', 'apple', 'banana' ), '1,2,apple,banana,' ),
			array( array( '1', '2', 'apple', 'banana' ), '1,2 ,apple,banana' ),
			array( array(), '' ),
			array( array(), ',' ),
			array( array(), ',,' ),
		);
	}
}
