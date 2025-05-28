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

		$inserted = $wpdb->insert_multiple(
			$table,
			$columns,
			$datas,
			$format
		);

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

		$this->assertSame( 3, $inserted );
		$this->assertSame( $expected_rows, $rows );
	}
}
