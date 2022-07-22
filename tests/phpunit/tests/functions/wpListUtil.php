<?php

/**
 * Test WP_List_Util class.
 *
 * @group functions.php
 */
class Tests_Functions_wpListUtil extends WP_UnitTestCase {

	/**
	 * @covers WP_List_Util::get_input
	 */
	public function test_wp_list_util_get_input() {
		$input = array( 'foo', 'bar' );
		$util  = new WP_List_Util( $input );

		$this->assertSameSets( $input, $util->get_input() );
	}

	/**
	 * @covers WP_List_Util::get_output
	 */
	public function test_wp_list_util_get_output_immediately() {
		$input = array( 'foo', 'bar' );
		$util  = new WP_List_Util( $input );

		$this->assertSameSets( $input, $util->get_output() );
	}

	/**
	 * @covers WP_List_Util::get_output
	 */
	public function test_wp_list_util_get_output() {
		$expected = array(
			(object) array(
				'foo' => 'bar',
				'bar' => 'baz',
			),
		);

		$util   = new WP_List_Util(
			array(
				(object) array(
					'foo' => 'bar',
					'bar' => 'baz',
				),
				(object) array( 'bar' => 'baz' ),
			)
		);
		$actual = $util->filter( array( 'foo' => 'bar' ) );

		$this->assertEqualSets( $expected, $actual );
		$this->assertEqualSets( $expected, $util->get_output() );
	}
}
