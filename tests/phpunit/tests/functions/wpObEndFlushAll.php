<?php

/**
 * @group functions
 * @group query
 *
 * @covers ::wp_ob_end_flush_all
 */
class Tests_Functions_wpObEndFlushAll extends WP_UnitTestCase {
	public function test_wp_sets_global_vars() {
		// Clear any previous errors that could lead to false failures.
		error_clear_last();

		function my_ob_cb( $output ) {
			return $output;
		}

		ob_start( 'my_ob_cb', 0, 0 );

		wp_ob_end_flush_all();

		$this->assertNull( error_get_last() );
	}
}
