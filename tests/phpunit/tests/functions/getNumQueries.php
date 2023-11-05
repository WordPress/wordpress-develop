<?php

/**
 * @group functions.php
 * @covers ::get_num_queries
 */
class Tests_Functions_getNumQueries extends WP_UnitTestCase {
	/**
	 * @ticket 54490
	 */
	public function test_wp_get_num_queries() {
		global $wpdb;

		$current_count = get_num_queries();
		$this->assertIsInt( $current_count, 'get_num_queries() did not return an integer.' );

		// do a single db query
		$wpdb->query( "select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='tableName'" );

		// check the count updated by 1
		$this->assertSame( $current_count + 1, get_num_queries(), 'The number of queries did not increase by 1.' );
	}
}
