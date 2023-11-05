<?php
/**
 * Tests for get_num_queries().
 *
 * @group functions
 * @group wpdb
 *
 * @covers ::get_num_queries
 */
class Tests_Functions_getNumQueries extends WP_UnitTestCase {
	/**
	 * Tests that making a database query increases the number of queries.
	 *
	 * @ticket 54490
	 */
	public function test_get_num_queries() {
		global $wpdb;

		$current_count = get_num_queries();
		$this->assertIsInt( $current_count, 'get_num_queries() did not return an integer.' );

		// Do a single database query.
		$wpdb->query( 'SELECT NOW();' );

		// Check the count increased by one.
		$this->assertSame( $current_count + 1, get_num_queries(), 'The number of queries did not increase by one.' );
	}
}
