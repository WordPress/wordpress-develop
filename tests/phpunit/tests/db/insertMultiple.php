<?php

/**
 * Test the insertion of multiple rows.
 *
 * @group wpdb
 *
 * @covers wpdb::insert_multiple
 */
class Tests_DB_InsertMultiple extends WP_UnitTestCase {

	/**
	 * @ticket 59269
	 */
	public function test_correct_rows_are_inserted() {
		global $wpdb;

		$table = $wpdb->postmeta;

		$columns = array(
			'post_id',
			'meta_key',
			'meta_value',
		);

		$datas = array(
			array( 1, 'key1', 'value1' ),
			array( 2, 'key2', 'value2' ),
			array( 3, 'key3', 'value3' ),
		);

		$format = array(
			'%d',
			'%s',
			'%s',
		);

		$query_count_before = $wpdb->num_queries;

		$inserted = $wpdb->insert_multiple(
			$table,
			$columns,
			$datas,
			$format
		);

		$query_count_after_insert = $wpdb->num_queries;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT post_id, meta_key, meta_value FROM %i ORDER BY post_id ASC',
				$table
			),
			ARRAY_A
		);

		$expected_rows = array(
			array(
				'post_id'    => '1',
				'meta_key'   => 'key1',
				'meta_value' => 'value1',
			),
			array(
				'post_id'    => '2',
				'meta_key'   => 'key2',
				'meta_value' => 'value2',
			),
			array(
				'post_id'    => '3',
				'meta_key'   => 'key3',
				'meta_value' => 'value3',
			),
		);

		$queries_used = $query_count_after_insert - $query_count_before;

		$this->assertSame( 3, $inserted );
		$this->assertSame( $expected_rows, $rows );
		
		// insert_multiple should use at most 2 queries: 
		// 1 for charset info (if not cached) + 1 for the bulk insert.
		// When run in isolation, it uses 2 queries. When run as part of the full wpdb test suite,
		// charset info is cached from previous tests, so it only uses 1 query.
		$this->assertLessThanOrEqual( 2, $queries_used, "Expected insert_multiple to use at most 2 queries for bulk insert" );
	}
}
