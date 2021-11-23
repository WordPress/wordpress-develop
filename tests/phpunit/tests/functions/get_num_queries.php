<?php

/**
 * @group functions.php
 * @covers ::get_num_queries
 */
class Tests_Functions_get_num_queries extends WP_UnitTestCase {

	public function test_wp_get_num_queries() {
		global $wpdb;

		$current_count = get_num_queries();
		$this->assertIsInt( $current_count );
		// do a single db query
		$wpdb->query( "select * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='tableName'" );

		// check the count updated by 1
		$this->assertEquals( $current_count + 1, get_num_queries() );
	}

}
