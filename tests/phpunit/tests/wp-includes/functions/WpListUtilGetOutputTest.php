<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_List_Util;
use WP_UnitTestCase;

/**
 * Tests for the `WP_List_Util::get_output()` method.
 *
 * @group functions
 *
 * @covers \WP_List_Util::get_output
 */
class WpListUtilGetOutputTest extends WP_UnitTestCase {
	/**
	 */
	public function test_wp_list_util_get_output_immediately() {
		$input = array( 'foo', 'bar' );
		$util  = new WP_List_Util( $input );

		$this->assertSameSets( $input, $util->get_output() );
	}

	/**
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
