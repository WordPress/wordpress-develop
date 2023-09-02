<?php

/**
 * Test the insertion of multiple rows.
 *
 * @group wpdb
 */
class Tests_DB_InsertMultiple extends WP_UnitTestCase {
	/**
	 * @var wpdb
	 */
	protected $wpdb;

	public function set_up() {
		parent::set_up();

		$this->wpdb = $GLOBALS['wpdb'];
	}

	/**
	 * @ticket 59269
	 */
	public function test_correct_rows_are_inserted() {
		$table = $this->wpdb->postmeta;
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

		$inserted = $this->wpdb->insert_multiple(
			$table,
			$columns,
			$datas,
			$format
		);

		$rows = $this->wpdb->get_results(
			"
				SELECT post_id, meta_key, meta_value
				FROM $table
				ORDER BY post_id ASC
			",
			ARRAY_A
		);

		$expected_rows = array(
			array(
				'post_id' => '1',
				'meta_key' => 'key1',
				'meta_value' => 'value1',
			),
			array(
				'post_id' => '2',
				'meta_key' => 'key2',
				'meta_value' => 'value2',
			),
			array(
				'post_id' => '3',
				'meta_key' => 'key3',
				'meta_value' => 'value3',
			),
		);

		$this->assertSame( 3, $inserted );
		$this->assertSame( $expected_rows, $rows );
	}
}
