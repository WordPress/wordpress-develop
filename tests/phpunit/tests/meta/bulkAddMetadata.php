<?php

/**
 * @group meta
 *
 * @covers bulk_add_metadata
 */
class Tests_Meta_BulkAddMetadata extends WP_UnitTestCase {

	/*
	 * It is important to test with both even and odd numbered slashes,
	 * as KSES does a strip-then-add slashes in some of its function calls.
	 */

	const SLASH_1 = 'String with 1 slash \\';
	const SLASH_2 = 'String with 2 slashes \\\\';
	const SLASH_3 = 'String with 3 slashes \\\\\\';
	const SLASH_4 = 'String with 4 slashes \\\\\\\\';
	const SLASH_5 = 'String with 5 slashes \\\\\\\\\\';
	const SLASH_6 = 'String with 6 slashes \\\\\\\\\\\\';
	const SLASH_7 = 'String with 7 slashes \\\\\\\\\\\\\\';

	/**
	 * @ticket 59269
	 * @dataProvider data_meta_types
	 */
	public function test_all_meta_fields_should_be_added( string $meta_type ) {
		global $wpdb;

		// Create the object.
		$object_id = self::factory()->{$meta_type}->create();

		// Prepare the meta values to add in bulk.
		$meta = array(
			'key1' => '1',
			'key2' => '2',
			'key3' => '3',
		);

		// Track the mid before adding new meta.
		$next_mid = self::get_autoincrement( $meta_type );

		// Set up mock actions and filters to track calls.
		$action1 = new MockAction();
		$action2 = new MockAction();
		$action3 = new MockAction();
		add_filter( "add_{$meta_type}_metadata", array( $action1, 'filter' ), 10, 5 );
		add_action( "add_{$meta_type}_meta", array( $action2, 'action' ) );
		add_action( "added_{$meta_type}_meta", array( $action3, 'action' ) );

		// Bulk add the meta.
		$result = bulk_add_metadata( $meta_type, $object_id, $meta );

		// Read back the actual meta values.
		$actual_vals = array(
			'key1' => get_metadata( $meta_type, $object_id, 'key1', true ),
			'key2' => get_metadata( $meta_type, $object_id, 'key2', true ),
			'key3' => get_metadata( $meta_type, $object_id, 'key3', true ),
		);

		// Prepare expected meta IDs.
		$expected_mids = array(
			'key1' => ( $next_mid ),
			'key2' => ( $next_mid + 1 ),
			'key3' => ( $next_mid + 2 ),
		);

		$this->assertSame( $meta, $actual_vals, 'Actual meta values should match expected values.' );
		$this->assertSame( $expected_mids, $result, 'Actual meta IDs should match expected meta IDs.' );
		$this->assertSame( 3, $action1->get_call_count(), "'add_{$meta_type}_metadata' filter should be called the correct number of times." );
		$this->assertSame( 3, $action2->get_call_count(), "'add_{$meta_type}_meta' action should be called the correct number of times." );
		$this->assertSame( 3, $action3->get_call_count(), "'added_{$meta_type}_meta' action should be called the correct number of times." );
	}

	/**
	 * @ticket 59269
	 * @dataProvider data_meta_types
	 */
	public function test_correct_mids_should_be_returned_when_filter_is_in_place( string $meta_type ) {
		global $wpdb;

		// Set up a filter to modify the mid for 'key2'.
		add_filter(
			"add_{$meta_type}_metadata",
			static function ( $check, $object_id, $meta_key ) {
				return ( 'key2' === $meta_key ) ? 123456 : $check;
			},
			10,
			3
		);

		// Create the object.
		$object_id = self::factory()->{$meta_type}->create();

		// Prepare the meta values to add in bulk.
		$meta = array(
			'key1' => '1',
			'key2' => '2',
			'key3' => '3',
		);

		// Track the mid before adding new meta.
		$next_mid = self::get_autoincrement( $meta_type );

		// Set up mock actions and filters to track calls.
		$action1 = new MockAction();
		$action2 = new MockAction();
		$action3 = new MockAction();
		add_filter( "add_{$meta_type}_metadata", array( $action1, 'filter' ), 10, 5 );
		add_action( "add_{$meta_type}_meta", array( $action2, 'action' ) );
		add_action( "added_{$meta_type}_meta", array( $action3, 'action' ) );

		// Bulk add the meta.
		$result = bulk_add_metadata( $meta_type, $object_id, $meta );

		// Prepare expected meta values.
		$expected_vals = array(
			'key1' => '1',
			'key2' => '',
			'key3' => '3',
		);

		// Read back the actual meta values.
		$actual_vals = array(
			'key1' => get_metadata( $meta_type, $object_id, 'key1', true ),
			'key2' => get_metadata( $meta_type, $object_id, 'key2', true ),
			'key3' => get_metadata( $meta_type, $object_id, 'key3', true ),
		);

		// Prepare expected meta IDs.
		$expected_mids = array(
			'key2' => 123456,
			'key1' => ( $next_mid ),
			'key3' => ( $next_mid + 1 ),
		);

		$this->assertSame( $expected_vals, $actual_vals, 'Actual meta values should match expected values.' );
		$this->assertSame( $expected_mids, $result , 'Actual meta IDs should match expected meta IDs.' );
		$this->assertSame( 3, $action1->get_call_count(), "'add_{$meta_type}_metadata' filter should be called the correct number of times." );
		$this->assertSame( 2, $action2->get_call_count(), "'add_{$meta_type}_meta' action should be called the correct number of times." );
		$this->assertSame( 2, $action3->get_call_count(), "'added_{$meta_type}_meta' action should be called the correct number of times." );
	}

	/**
	 * @ticket 59269
	 * @dataProvider data_meta_types
	 */
	public function test_slashed_data_should_be_handled_correctly( string $meta_type ) {
		// Create the object.
		$object_id = self::factory()->{$meta_type}->create();

		// Prepare the meta values to add in bulk.
		$meta = array(
			'key1' => addslashes( self::SLASH_1 ),
			'key2' => addslashes( self::SLASH_2 ),
			'key3' => addslashes( self::SLASH_3 ),
			'key4' => addslashes( self::SLASH_4 ),
			'key5' => addslashes( self::SLASH_5 ),
			'key6' => addslashes( self::SLASH_6 ),
			'key7' => addslashes( self::SLASH_7 ),
		);

		// Bulk add the meta.
		$result = bulk_add_metadata( $meta_type, $object_id, $meta );

		// Read back the actual meta values.
		$actual_vals = array(
			'key1' => get_metadata( $meta_type, $object_id, 'key1', true ),
			'key2' => get_metadata( $meta_type, $object_id, 'key2', true ),
			'key3' => get_metadata( $meta_type, $object_id, 'key3', true ),
			'key4' => get_metadata( $meta_type, $object_id, 'key4', true ),
			'key5' => get_metadata( $meta_type, $object_id, 'key5', true ),
			'key6' => get_metadata( $meta_type, $object_id, 'key6', true ),
			'key7' => get_metadata( $meta_type, $object_id, 'key7', true ),
		);

		// Prepare expected meta values.
		$expected_vals = array(
			'key1' => self::SLASH_1,
			'key2' => self::SLASH_2,
			'key3' => self::SLASH_3,
			'key4' => self::SLASH_4,
			'key5' => self::SLASH_5,
			'key6' => self::SLASH_6,
			'key7' => self::SLASH_7,
		);

		$this->assertCount( 7, $result, 'The correct number of meta IDs should be returned.' );
		$this->assertSame( $expected_vals, $actual_vals, 'Actual meta values should match expected values.' );
	}

	/**
	 * @return array<string,array<string>>
	 */
	protected function data_meta_types(): array {
		$types = [
			'post'    => [ 'post' ],
			'user'    => [ 'user' ],
			'comment' => [ 'comment' ],
			'term'    => [ 'term' ],
		];

		if ( is_multisite() ) {
			$types['blog'] = [ 'blog' ];
		}

		return $types;
	}

	private static function get_autoincrement( string $meta_type ): int {
		global $wpdb;

		$table = "{$meta_type}meta";
		$sql   = "
			SELECT AUTO_INCREMENT
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME = %s
		";

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				$sql,
				$wpdb->$table
			)
		);
	}
}
