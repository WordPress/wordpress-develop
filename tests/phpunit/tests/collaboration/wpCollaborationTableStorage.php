<?php
/**
 * Tests for the WP_Collaboration_Table_Storage class.
 *
 * Covers the storage implementation contract: cache bypass, data integrity,
 * malformed data handling, and race-condition safety.
 *
 * @package WordPress
 * @subpackage Collaboration
 *
 * @group collaboration
 * @group cache
 */
class Tests_Collaboration_WpCollaborationTableStorage extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_option_wp_collaboration_enabled', '__return_true' );
	}

	/**
	 * Returns the number of awareness rows in the collaboration table.
	 *
	 * @return positive-int Row count.
	 */
	private function get_awareness_row_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE type = 'awareness'" );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_storage_add_update_rejects_empty_room(): void {
		$storage = new WP_Collaboration_Table_Storage();
		$result  = $storage->add_update(
			'',
			array(
				'type'      => 'update',
				'client_id' => '1',
				'data'      => 'test',
			)
		);
		$this->assertFalse( $result, 'add_update should reject an empty room.' );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_storage_add_update_rejects_empty_type(): void {
		$storage = new WP_Collaboration_Table_Storage();
		$result  = $storage->add_update(
			'postType/post:1',
			array(
				'type'      => '',
				'client_id' => '1',
				'data'      => 'test',
			)
		);
		$this->assertFalse( $result, 'add_update should reject an empty type.' );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_storage_add_update_rejects_empty_client_id(): void {
		$storage = new WP_Collaboration_Table_Storage();
		$result  = $storage->add_update(
			'postType/post:1',
			array(
				'type'      => 'update',
				'client_id' => '',
				'data'      => 'test',
			)
		);
		$this->assertFalse( $result, 'add_update should reject an empty client_id.' );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_storage_set_awareness_rejects_empty_room(): void {
		$storage = new WP_Collaboration_Table_Storage();
		$result  = $storage->set_awareness_state( '', '1', array( 'user' => 'test' ), 1 );
		$this->assertFalse( $result, 'set_awareness_state should reject an empty room.' );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_storage_set_awareness_rejects_empty_client_id(): void {
		$storage = new WP_Collaboration_Table_Storage();
		$result  = $storage->set_awareness_state( 'postType/post:1', '', array( 'user' => 'test' ), 1 );
		$this->assertFalse( $result, 'set_awareness_state should reject an empty client_id.' );
	}
	/**
	 * Ensure awareness updates are not stored in the DB for sites using a persistent cache.
	 */
	public function test_awareness_uses_persistent_object_cache() {
		if ( ! wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is in use.' );
		}

		$storage          = new WP_Collaboration_Table_Storage();
		$db_calls_initial = get_num_queries();
		$storage->set_awareness_state( 'test-room', 'test-client', array( 'name' => 'Test Client' ), 1 );
		$db_calls_after = get_num_queries();

		$this->assertSame( 0, $db_calls_after - $db_calls_initial, 'Awareness update should not trigger database queries when using persistent object cache.' );
		$this->assertSame( 0, $this->get_awareness_row_count(), 'Awareness row should not be stored in database when using persistent object cache.' );
	}

	/**
	 * Ensure awareness retrieval uses in-memory cache within a single request, even when a persistent cache is in use.
	 */
	public function test_awareness_uses_in_memory_cache() {
		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}

		$storage          = new WP_Collaboration_Table_Storage();
		$db_calls_initial = get_num_queries();
		$storage->set_awareness_state( 'test-room', 'test-client', array( 'name' => 'Test Client' ), 1 );
		$db_calls_after = get_num_queries();

		$this->assertSame( 3, $db_calls_after - $db_calls_initial, 'Awareness update should not trigger database queries when using persistent object cache.' );
		$this->assertSame( 1, $this->get_awareness_row_count(), 'Awareness row should not be stored in database when using persistent object cache.' );

		$db_calls_initial = get_num_queries();
		$storage->get_awareness_state( 'test-room' );
		$db_calls_after = get_num_queries();

		$this->assertSame( 1, $db_calls_after - $db_calls_initial, 'Initial awareness retrieval should query database.' );

		$db_calls_initial = get_num_queries();
		$storage->get_awareness_state( 'test-room' );
		$db_calls_after = get_num_queries();

		$this->assertSame( 0, $db_calls_after - $db_calls_initial, 'Subsequent awareness retrieval should use in-memory cache and not query database.' );
	}

	/**
	 * Ensure adding subsequent client does not remove existing clients from room.
	 */
	public function test_awareness_updates_for_multiple_users() {
		$storage = new WP_Collaboration_Table_Storage();

		// User 1 sets awareness.
		$storage->set_awareness_state( 'test-room', 'client-1', array( 'name' => 'Client 1' ), 1 );

		// User 2 sets awareness.
		$storage->set_awareness_state( 'test-room', 'client-2', array( 'name' => 'Client 2' ), 2 );

		// Retrieve awareness state and verify both users are present.
		$awareness = $storage->get_awareness_state( 'test-room' );
		$clients   = wp_list_pluck( $awareness, 'client_id' );

		$this->assertContains( 'client-1', $clients, 'Client 1 should be present in awareness state.' );
		$this->assertContains( 'client-2', $clients, 'Client 2 should be present in awareness state.' );
	}

	/**
	 * Ensure awareness does not include out of date clients from cached results.
	 */
	public function test_awareness_excludes_expired_clients_from_cached_results() {
		$storage     = new WP_Collaboration_Table_Storage();
		$cached_data = array(
			array(
				'client_id' => 'client-1',
				'state'     => array( 'name' => 'Client 1' ),
				'timestamp' => time() - 120, // Simulate expired client.
			),
			array(
				'client_id' => 'client-2',
				'state'     => array( 'name' => 'Client 2' ),
				'timestamp' => time(), // Active client.
			),
		);

		// Manually set cached awareness data.
		wp_cache_set( 'awareness::test-room', $cached_data, 'collaboration', HOUR_IN_SECONDS );

		$awareness = $storage->get_awareness_state( 'test-room' );
		$clients   = wp_list_pluck( $awareness, 'client_id' );

		$this->assertNotContains( 'client-1', $clients, 'Expired client should not be present in awareness state.' );
		$this->assertContains( 'client-2', $clients, 'Active client should be present in awareness state.' );
		$this->assertCount( 1, $awareness, 'Only one active client should be present in awareness state.' );
	}
}
