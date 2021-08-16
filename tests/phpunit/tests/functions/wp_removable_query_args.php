<?php

/**
 * @group functions.php
 * @covers ::wp_removable_query_args
 */
class Tests_Functions_wp_removable_query_args extends WP_UnitTestCase {

	/**
	 * @ticket 53651
	 */
	public function test_wp_removable_query_args() {

		$this->assertNotEmpty( wp_removable_query_args() );
	}

	/**
	 * @ticket 53651
	 */
	public function test_wp_removable_query_args_applies_filter() {
		add_filter( 'removable_query_args', static function( $args ) { return array(); } );

		$this->assertSame( array(), wp_removable_query_args() );
	}
}
