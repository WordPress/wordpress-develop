<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_List_Util;
use WP_UnitTestCase;

/**
 * Tests for the `WP_List_Util::get_input()` method.
 *
 * @group functions
 *
 * @covers \WP_List_Util::get_input
 */
class WpListUtilGetInputTest extends WP_UnitTestCase {
	/**
	 */
	public function test_wp_list_util_get_input() {
		$input = array( 'foo', 'bar' );
		$util  = new WP_List_Util( $input );

		$this->assertSameSets( $input, $util->get_input() );
	}
}
