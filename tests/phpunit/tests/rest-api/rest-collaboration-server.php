<?php
/**
 * Tests for the WP_HTTP_Polling_Collaboration_Server REST endpoint.
 *
 * @package WordPress
 * @subpackage REST API
 *
 * @group restapi
 */
class WP_Test_REST_Collaboration_Server extends WP_Test_REST_Controller_Testcase {

	protected static $editor_id;
	protected static $subscriber_id;
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id     = $factory->user->create( array( 'role' => 'editor' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$post_id       = $factory->post->create( array( 'post_author' => self::$editor_id ) );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$editor_id );
		self::delete_user( self::$subscriber_id );
		wp_delete_post( self::$post_id, true );
	}

	public function set_up() {
		parent::set_up();

		// Uses DELETE (not TRUNCATE) to preserve transaction rollback support
		// in the test suite. TRUNCATE implicitly commits the transaction.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->collaboration}" );
	}

	/**
	 * Builds a room request array for the collaboration endpoint.
	 *
	 * @param string $room      Room identifier.
	 * @param string $client_id Client ID.
	 * @param int    $cursor    Cursor value for the 'after' parameter.
	 * @param array  $awareness Awareness state.
	 * @param array  $updates   Array of updates.
	 * @return array Room request data.
	 */
	private function build_room( $room, $client_id = '1', $cursor = 0, $awareness = array(), $updates = array() ) {
		if ( empty( $awareness ) ) {
			$awareness = array( 'user' => 'test' );
		}

		return array(
			'after'     => $cursor,
			'awareness' => $awareness,
			'client_id' => $client_id,
			'room'      => $room,
			'updates'   => $updates,
		);
	}

	/**
	 * Dispatches a collaboration request with the given rooms.
	 *
	 * @param array  $rooms      Array of room request data.
	 * @param string $_namespace REST namespace to use. Defaults to the primary namespace.
	 * @return WP_REST_Response Response object.
	 */
	private function dispatch_collaboration( $rooms, $_namespace = 'wp-collaboration/v1' ) {
		$request = new WP_REST_Request( 'POST', '/' . $_namespace . '/updates' );
		$request->set_body_params( array( 'rooms' => $rooms ) );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Returns the default room identifier for the test post.
	 *
	 * @return string Room identifier.
	 */
	private function get_post_room() {
		return 'postType/post:' . self::$post_id;
	}

	/*
	 * Required abstract method implementations.
	 *
	 * The collaboration endpoint is a single POST endpoint, not a standard CRUD controller.
	 * Methods that don't apply are stubbed with @doesNotPerformAssertions.
	 */

	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-collaboration/v1/updates', $routes );
	}

	/**
	 * Verifies the collaboration route is registered when relying on the option's default
	 * value (option not stored in the database).
	 *
	 * This covers the upgrade scenario where a site has never explicitly saved
	 * the collaboration setting.
	 *
	 * @ticket 64814
	 */
	public function test_register_routes_with_default_option() {
		global $wp_rest_server;

		// Ensure the option is not in the database.
		delete_option( 'wp_enable_real_time_collaboration' );

		// Reset the REST server so routes are re-registered from scratch.
		$wp_rest_server = null;

		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp-collaboration/v1/updates', $routes );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_context_param() {
		// Not applicable for collaboration endpoint.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_items() {
		// Not applicable for collaboration endpoint.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Not applicable for collaboration endpoint.
	}

	public function test_create_item() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( $this->get_post_room() ) ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_update_item() {
		// Not applicable for collaboration endpoint.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_delete_item() {
		// Not applicable for collaboration endpoint.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_prepare_item() {
		// Not applicable for collaboration endpoint.
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
		// Not applicable for collaboration endpoint.
	}

	/*
	 * Permission tests.
	 */

	public function test_collaboration_requires_authentication() {
		wp_set_current_user( 0 );

		$response = $this->dispatch_collaboration( array( $this->build_room( $this->get_post_room() ) ) );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 401 );
	}

	public function test_collaboration_post_requires_edit_capability() {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( $this->get_post_room() ) ) );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_collaboration_post_allowed_with_edit_capability() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( $this->get_post_room() ) ) );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_collaboration_post_type_collection_requires_edit_posts_capability() {
		wp_set_current_user( self::$subscriber_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( 'postType/post' ) ) );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_collaboration_post_type_collection_allowed_with_edit_posts_capability() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( 'postType/post' ) ) );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_collaboration_root_collection_allowed() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( 'root/site' ) ) );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_collaboration_taxonomy_collection_allowed() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( 'taxonomy/category' ) ) );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_collaboration_unknown_collection_kind_rejected() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( 'unknown/entity' ) ) );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_collaboration_non_posttype_entity_with_object_id_rejected() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( 'root/site:123' ) ) );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_collaboration_nonexistent_post_rejected() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( 'postType/post:999999' ) ) );

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	public function test_collaboration_permission_checked_per_room() {
		wp_set_current_user( self::$editor_id );

		// First room is allowed, second room is forbidden.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $this->get_post_room() ),
				$this->build_room( 'unknown/entity' ),
			)
		);

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/*
	 * Validation tests.
	 */

	public function test_collaboration_invalid_room_format_rejected() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( 'invalid-room-format' ),
			)
		);

		$this->assertSame( 400, $response->get_status() );
	}

	/*
	 * Response format tests.
	 */

	public function test_collaboration_response_structure() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( $this->get_post_room() ) ) );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'rooms', $data );
		$this->assertCount( 1, $data['rooms'] );

		$room_data = $data['rooms'][0];
		$this->assertArrayHasKey( 'room', $room_data );
		$this->assertArrayHasKey( 'awareness', $room_data );
		$this->assertArrayHasKey( 'updates', $room_data );
		$this->assertArrayHasKey( 'end_cursor', $room_data );
		$this->assertArrayHasKey( 'total_updates', $room_data );
		$this->assertArrayHasKey( 'should_compact', $room_data );
	}

	public function test_collaboration_response_room_matches_request() {
		wp_set_current_user( self::$editor_id );

		$room     = $this->get_post_room();
		$response = $this->dispatch_collaboration( array( $this->build_room( $room ) ) );

		$data = $response->get_data();
		$this->assertSame( $room, $data['rooms'][0]['room'] );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_end_cursor_is_non_negative_integer() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( $this->get_post_room() ) ) );

		$data = $response->get_data();
		$this->assertIsInt( $data['rooms'][0]['end_cursor'] );
		// Cursor is 0 for an empty room (no rows in the table yet).
		$this->assertGreaterThanOrEqual( 0, $data['rooms'][0]['end_cursor'] );
	}

	public function test_collaboration_empty_updates_returns_zero_total() {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( $this->get_post_room() ) ) );

		$data = $response->get_data();
		$this->assertSame( 0, $data['rooms'][0]['total_updates'] );
		$this->assertEmpty( $data['rooms'][0]['updates'] );
	}

	/*
	 * Update tests.
	 */

	public function test_collaboration_update_delivered_to_other_client() {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'dGVzdCBkYXRh',
		);

		// Client 1 sends an update.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 requests updates from the beginning.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0 ),
			)
		);

		$data    = $response->get_data();
		$updates = $data['rooms'][0]['updates'];

		$this->assertNotEmpty( $updates );

		$types = wp_list_pluck( $updates, 'type' );
		$this->assertContains( 'update', $types );
	}

	public function test_collaboration_own_updates_not_returned() {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'b3duIGRhdGE=',
		);

		// Client 1 sends an update.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		$data    = $response->get_data();
		$updates = $data['rooms'][0]['updates'];

		// Client 1 should not see its own non-compaction update.
		$this->assertEmpty( $updates );
	}

	public function test_collaboration_step1_update_stored_and_returned() {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'sync_step1',
			'data' => 'c3RlcDE=',
		);

		// Client 1 sends sync_step1.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 should see the sync_step1 update.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0 ),
			)
		);

		$data  = $response->get_data();
		$types = wp_list_pluck( $data['rooms'][0]['updates'], 'type' );
		$this->assertContains( 'sync_step1', $types );
	}

	public function test_collaboration_step2_update_stored_and_returned() {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'sync_step2',
			'data' => 'c3RlcDI=',
		);

		// Client 1 sends sync_step2.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 should see the sync_step2 update.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0 ),
			)
		);

		$data  = $response->get_data();
		$types = wp_list_pluck( $data['rooms'][0]['updates'], 'type' );
		$this->assertContains( 'sync_step2', $types );
	}

	public function test_collaboration_multiple_updates_in_single_request() {
		wp_set_current_user( self::$editor_id );

		$room    = $this->get_post_room();
		$updates = array(
			array(
				'type' => 'sync_step1',
				'data' => 'c3RlcDE=',
			),
			array(
				'type' => 'update',
				'data' => 'dXBkYXRl',
			),
		);

		// Client 1 sends multiple updates.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'client1' ), $updates ),
			)
		);

		// Client 2 should see both updates.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0 ),
			)
		);

		$data         = $response->get_data();
		$room_updates = $data['rooms'][0]['updates'];

		$this->assertCount( 2, $room_updates );
		$this->assertSame( 2, $data['rooms'][0]['total_updates'] );
	}

	public function test_collaboration_update_data_preserved() {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'cHJlc2VydmVkIGRhdGE=',
		);

		// Client 1 sends an update.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 should receive the exact same data.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0 ),
			)
		);

		$data         = $response->get_data();
		$room_updates = $data['rooms'][0]['updates'];

		$this->assertSame( 'cHJlc2VydmVkIGRhdGE=', $room_updates[0]['data'] );
		$this->assertSame( 'update', $room_updates[0]['type'] );
	}

	public function test_collaboration_total_updates_increments() {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'dGVzdA==',
		);

		// Send three updates from different clients.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'user' => 'c2' ), array( $update ) ),
			)
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '3', 0, array( 'user' => 'c3' ), array( $update ) ),
			)
		);

		// Any client should see total_updates = 3.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '4', 0 ),
			)
		);

		$data = $response->get_data();
		$this->assertSame( 3, $data['rooms'][0]['total_updates'] );
	}

	/*
	 * Compaction tests.
	 */

	public function test_collaboration_should_compact_is_false_below_threshold() {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'dGVzdA==',
		);

		// Client 1 sends a single update.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);

		$data = $response->get_data();
		$this->assertFalse( $data['rooms'][0]['should_compact'] );
	}

	public function test_collaboration_should_compact_is_true_above_threshold_for_compactor() {
		wp_set_current_user( self::$editor_id );

		$room    = $this->get_post_room();
		$updates = array();
		for ( $i = 0; $i < 51; $i++ ) {
			$updates[] = array(
				'type' => 'update',
				'data' => base64_encode( "update-$i" ),
			);
		}

		// Client 1 sends enough updates to exceed the compaction threshold.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), $updates ),
			)
		);

		// Client 1 polls again. It is the lowest (only) client, so it is the compactor.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ) ),
			)
		);

		$data = $response->get_data();
		$this->assertTrue( $data['rooms'][0]['should_compact'] );
	}

	/**
	 * Verifies that a caught-up compactor client still receives the
	 * should_compact signal when the room has accumulated updates
	 * beyond the compaction threshold.
	 *
	 * Regression test: the update count was previously cached as 0
	 * when the cursor matched the latest update ID, preventing
	 * compaction from ever triggering for idle rooms.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_should_compact_when_compactor_is_caught_up() {
		wp_set_current_user( self::$editor_id );

		$room    = $this->get_post_room();
		$updates = array();
		for ( $i = 0; $i < 51; $i++ ) {
			$updates[] = array(
				'type' => 'update',
				'data' => base64_encode( "update-$i" ),
			);
		}

		// Client 1 sends enough updates to exceed the compaction threshold.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), $updates ),
			)
		);

		// Grab the end_cursor so the client is fully caught up.
		$data       = $response->get_data();
		$end_cursor = $data['rooms'][0]['end_cursor'];

		// Client 1 polls again with cursor = end_cursor (caught up, no new updates).
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', $end_cursor, array( 'user' => 'c1' ) ),
			)
		);

		$data = $response->get_data();
		$this->assertTrue( $data['rooms'][0]['should_compact'], 'Compactor should receive should_compact even when caught up.' );
	}

	public function test_collaboration_should_compact_is_false_for_non_compactor() {
		wp_set_current_user( self::$editor_id );

		$room    = $this->get_post_room();
		$updates = array();
		for ( $i = 0; $i < 51; $i++ ) {
			$updates[] = array(
				'type' => 'update',
				'data' => base64_encode( "update-$i" ),
			);
		}

		// Client 1 sends enough updates to exceed the compaction threshold.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), $updates ),
			)
		);

		// Client 2 (higher ID than client 1) should not be the compactor.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'user' => 'c2' ) ),
			)
		);

		$data = $response->get_data();
		$this->assertFalse( $data['rooms'][0]['should_compact'] );
	}

	public function test_collaboration_stale_compaction_succeeds_when_newer_compaction_exists() {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'dGVzdA==',
		);

		// Client 1 sends an update to seed the room.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);

		$end_cursor = $response->get_data()['rooms'][0]['end_cursor'];

		// Client 2 sends a compaction at the current cursor.
		$compaction = array(
			'type' => 'compaction',
			'data' => 'Y29tcGFjdGVk',
		);

		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', $end_cursor, array( 'user' => 'c2' ), array( $compaction ) ),
			)
		);

		// Client 3 sends a stale compaction at cursor 0. The server should find
		// client 2's compaction in the updates after cursor 0 and silently discard
		// this one.
		$stale_compaction = array(
			'type' => 'compaction',
			'data' => 'c3RhbGU=',
		);
		$response         = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '3', 0, array( 'user' => 'c3' ), array( $stale_compaction ) ),
			)
		);

		$this->assertSame( 200, $response->get_status() );

		// Verify the newer compaction is preserved and the stale one was not stored.
		$response    = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '4', 0, array( 'user' => 'c4' ) ),
			)
		);
		$update_data = wp_list_pluck( $response->get_data()['rooms'][0]['updates'], 'data' );

		$this->assertContains( 'Y29tcGFjdGVk', $update_data, 'The newer compaction should be preserved.' );
		$this->assertNotContains( 'c3RhbGU=', $update_data, 'The stale compaction should not be stored.' );
	}

	/*
	 * Awareness tests.
	 */

	public function test_collaboration_awareness_returned() {
		wp_set_current_user( self::$editor_id );

		$awareness = array( 'name' => 'Editor' );
		$response  = $this->dispatch_collaboration(
			array(
				$this->build_room( $this->get_post_room(), '1', 0, $awareness ),
			)
		);

		$data = $response->get_data();
		$this->assertArrayHasKey( '1', $data['rooms'][0]['awareness'] );
		$this->assertSame( $awareness, $data['rooms'][0]['awareness'][1] );
	}

	public function test_collaboration_awareness_shows_multiple_clients() {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 connects.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'name' => 'Client 1' ) ),
			)
		);

		// Client 2 connects.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'name' => 'Client 2' ) ),
			)
		);

		$data      = $response->get_data();
		$awareness = $data['rooms'][0]['awareness'];

		$this->assertArrayHasKey( '1', $awareness );
		$this->assertArrayHasKey( '2', $awareness );
		$this->assertSame( array( 'name' => 'Client 1' ), $awareness['1'] );
		$this->assertSame( array( 'name' => 'Client 2' ), $awareness['2'] );
	}

	public function test_collaboration_awareness_updates_existing_client() {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 connects with initial awareness.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'start' ) ),
			)
		);

		// Client 1 updates its awareness.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'updated' ) ),
			)
		);

		$data      = $response->get_data();
		$awareness = $data['rooms'][0]['awareness'];

		// Should have exactly one entry for client 1 with updated state.
		$this->assertCount( 1, $awareness );
		$this->assertSame( array( 'cursor' => 'updated' ), $awareness['1'] );
	}

	public function test_collaboration_awareness_client_id_cannot_be_used_by_another_user() {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Editor establishes awareness with client_id 1.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'name' => 'Editor' ) ),
			)
		);

		// A different user tries to use the same client_id.
		$editor_id_2 = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id_2 );

		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'name' => 'Impostor' ) ),
			)
		);

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * Verifies that a client can reactivate with the same client ID after
	 * its awareness entry has expired (e.g., laptop closed and reopened).
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_awareness_client_reactivates_after_expiry() {
		wp_set_current_user( self::$editor_id );
		global $wpdb;

		$room = $this->get_post_room();

		// Client 1 registers awareness.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'before-sleep' ) ),
			)
		);

		// Simulate the client going idle beyond the awareness timeout
		// by backdating its awareness row.
		$wpdb->update(
			$wpdb->collaboration,
			array( 'date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 120 ) ),
			array(
				'room'      => $room,
				'type'      => 'awareness',
				'client_id' => '1',
			)
		);

		// Flush the object cache so get_awareness_state() hits the DB.
		wp_cache_flush();

		// Another client polls — the expired client should not appear.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response  = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'cursor' => 'observer' ) ),
			)
		);
		$awareness = $response->get_data()['rooms'][0]['awareness'];
		$this->assertArrayNotHasKey( '1', $awareness, 'Expired client should not appear in awareness.' );

		// Original user returns and reconnects with the same client_id.
		wp_set_current_user( self::$editor_id );
		wp_cache_flush();

		$response  = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'after-sleep' ) ),
			)
		);
		$awareness = $response->get_data()['rooms'][0]['awareness'];

		$this->assertSame( 200, $response->get_status(), 'Reactivation should succeed.' );
		$this->assertArrayHasKey( '1', $awareness, 'Reactivated client should appear in awareness.' );
		$this->assertSame( array( 'cursor' => 'after-sleep' ), $awareness['1'], 'Reactivated client should have updated state.' );

		// Verify no duplicate rows were created.
		$row_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE type = 'awareness' AND room = %s AND client_id = %s",
				$room,
				'1'
			)
		);
		$this->assertSame( 1, $row_count, 'Should have exactly one awareness row after reactivation.' );
	}

	/*
	 * Multiple rooms tests.
	 */

	public function test_collaboration_multiple_rooms_in_single_request() {
		wp_set_current_user( self::$editor_id );

		$room1 = $this->get_post_room();
		$room2 = 'taxonomy/category';

		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room1 ),
				$this->build_room( $room2 ),
			)
		);

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 2, $data['rooms'] );
		$this->assertSame( $room1, $data['rooms'][0]['room'] );
		$this->assertSame( $room2, $data['rooms'][1]['room'] );
	}

	public function test_collaboration_rooms_are_isolated() {
		wp_set_current_user( self::$editor_id );

		$post_id_2 = self::factory()->post->create( array( 'post_author' => self::$editor_id ) );
		$room1     = $this->get_post_room();
		$room2     = 'postType/post:' . $post_id_2;

		$update = array(
			'type' => 'update',
			'data' => 'cm9vbTEgb25seQ==',
		);

		// Client 1 sends an update to room 1 only.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room1, '1', 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 queries both rooms.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room1, '2', 0 ),
				$this->build_room( $room2, '2', 0 ),
			)
		);

		$data = $response->get_data();

		// Room 1 should have the update.
		$this->assertNotEmpty( $data['rooms'][0]['updates'] );

		// Room 2 should have no updates.
		$this->assertEmpty( $data['rooms'][1]['updates'] );
	}

	/*
	 * Cursor tests.
	 */

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_empty_room_cursor_is_zero(): void {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration( array( $this->build_room( $this->get_post_room() ) ) );

		$data = $response->get_data();
		$this->assertSame( 0, $data['rooms'][0]['end_cursor'] );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_cursor_advances_monotonically(): void {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'dGVzdA==',
		);

		// First request.
		$response1 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);
		$cursor1   = $response1->get_data()['rooms'][0]['end_cursor'];

		// Second request with more updates.
		$response2 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', $cursor1, array( 'user' => 'c2' ), array( $update ) ),
			)
		);
		$cursor2   = $response2->get_data()['rooms'][0]['end_cursor'];

		$this->assertGreaterThan( $cursor1, $cursor2, 'Cursor should advance monotonically with new updates.' );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_cursor_prevents_re_delivery(): void {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => base64_encode( 'first-batch' ),
		);

		// Client 1 sends an update.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);

		// Client 2 fetches updates and gets a cursor.
		$response1 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'user' => 'c2' ) ),
			)
		);
		$data1     = $response1->get_data();
		$cursor1   = $data1['rooms'][0]['end_cursor'];

		$this->assertNotEmpty( $data1['rooms'][0]['updates'], 'First poll should return updates.' );

		// Client 2 polls again using the cursor from the first poll, with no new updates.
		$response2 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', $cursor1, array( 'user' => 'c2' ) ),
			)
		);
		$data2     = $response2->get_data();

		$this->assertEmpty( $data2['rooms'][0]['updates'], 'Second poll with cursor should not re-deliver updates.' );
	}

	/*
	 * Cache thrashing tests.
	 */

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_operations_do_not_affect_posts_last_changed(): void {
		wp_set_current_user( self::$editor_id );

		// Prime the posts last changed cache.
		wp_cache_set_posts_last_changed();
		$last_changed_before = wp_cache_get_last_changed( 'posts' );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'dGVzdA==',
		);

		// Perform several collaboration operations.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'user' => 'c2' ), array( $update ) ),
			)
		);

		$last_changed_after = wp_cache_get_last_changed( 'posts' );

		$this->assertSame( $last_changed_before, $last_changed_after, 'Collaboration operations should not invalidate the posts last changed cache.' );
	}

	/*
	 * Race condition tests.
	 */

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_compaction_does_not_lose_concurrent_updates(): void {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 sends an initial batch of updates.
		$initial_updates = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$initial_updates[] = array(
				'type' => 'update',
				'data' => base64_encode( "initial-$i" ),
			);
		}

		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), $initial_updates ),
			)
		);

		$data   = $response->get_data();
		$cursor = $data['rooms'][0]['end_cursor'];

		// Client 2 sends a new update (simulating a concurrent write).
		$concurrent_update = array(
			'type' => 'update',
			'data' => base64_encode( 'concurrent' ),
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'user' => 'c2' ), array( $concurrent_update ) ),
			)
		);

		// Client 1 sends a compaction update using its cursor.
		$compaction_update = array(
			'type' => 'compaction',
			'data' => base64_encode( 'compacted-state' ),
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', $cursor, array( 'user' => 'c1' ), array( $compaction_update ) ),
			)
		);

		// Client 3 requests all updates from the beginning.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '3', 0, array( 'user' => 'c3' ) ),
			)
		);

		$data         = $response->get_data();
		$room_updates = $data['rooms'][0]['updates'];
		$update_data  = wp_list_pluck( $room_updates, 'data' );

		// The concurrent update must not be lost.
		$this->assertContains( base64_encode( 'concurrent' ), $update_data, 'Concurrent update should not be lost during compaction.' );

		// The compaction update should be present.
		$this->assertContains( base64_encode( 'compacted-state' ), $update_data, 'Compaction update should be present.' );
	}

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_compaction_reduces_total_updates(): void {
		wp_set_current_user( self::$editor_id );

		$room    = $this->get_post_room();
		$updates = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$updates[] = array(
				'type' => 'update',
				'data' => base64_encode( "update-$i" ),
			);
		}

		// Client 1 sends 10 updates.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'c1' ), $updates ),
			)
		);

		$data   = $response->get_data();
		$cursor = $data['rooms'][0]['end_cursor'];

		// Client 1 sends a compaction to replace the 10 updates.
		$compaction = array(
			'type' => 'compaction',
			'data' => base64_encode( 'compacted' ),
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', $cursor, array( 'user' => 'c1' ), array( $compaction ) ),
			)
		);

		// Client 2 checks the state.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'user' => 'c2' ) ),
			)
		);

		$data = $response->get_data();
		$this->assertLessThan( 10, $data['rooms'][0]['total_updates'], 'Compaction should reduce the total update count.' );
	}

	/*
	 * Cron cleanup tests.
	 */

	/**
	 * Inserts a row directly into the collaboration table with a given age.
	 *
	 * @param positive-int $age_in_seconds How old the row should be.
	 * @param string       $label          A label stored in the data column for identification.
	 */
	private function insert_collaboration_row( int $age_in_seconds, string $label = 'test' ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => $this->get_post_room(),
				'type'      => 'update',
				'client_id' => '1',
				'data'      => wp_json_encode(
					array(
						'type' => 'update',
						'data' => $label,
					)
				),
				'date_gmt'  => gmdate( 'Y-m-d H:i:s', time() - $age_in_seconds ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Returns the number of non-awareness rows in the collaboration table.
	 *
	 * @return positive-int Row count.
	 */
	private function get_collaboration_row_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE type != 'awareness'" );
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
	public function test_cron_cleanup_deletes_old_rows(): void {
		$this->insert_collaboration_row( 8 * DAY_IN_SECONDS );

		$this->assertSame( 1, $this->get_collaboration_row_count() );

		wp_delete_old_collaboration_data();

		$this->assertSame( 0, $this->get_collaboration_row_count() );
	}

	/**
	 * @ticket 64696
	 */
	public function test_cron_cleanup_preserves_recent_rows(): void {
		$this->insert_collaboration_row( DAY_IN_SECONDS );

		wp_delete_old_collaboration_data();

		$this->assertSame( 1, $this->get_collaboration_row_count() );
	}

	/**
	 * @ticket 64696
	 */
	public function test_cron_cleanup_boundary_at_exactly_seven_days(): void {
		$this->insert_collaboration_row( WEEK_IN_SECONDS + 1, 'expired' );
		$this->insert_collaboration_row( WEEK_IN_SECONDS - 1, 'just-inside' );

		wp_delete_old_collaboration_data();

		global $wpdb;
		$remaining = $wpdb->get_col( "SELECT data FROM {$wpdb->collaboration}" );

		$this->assertCount( 1, $remaining, 'Only the row within the 7-day window should remain.' );
		$this->assertStringContainsString( 'just-inside', $remaining[0], 'The surviving row should be the one inside the window.' );
	}

	/**
	 * @ticket 64696
	 */
	public function test_cron_cleanup_selectively_deletes_mixed_rows(): void {
		// 3 expired rows.
		$this->insert_collaboration_row( 10 * DAY_IN_SECONDS );
		$this->insert_collaboration_row( 10 * DAY_IN_SECONDS );
		$this->insert_collaboration_row( 10 * DAY_IN_SECONDS );

		// 2 recent rows.
		$this->insert_collaboration_row( HOUR_IN_SECONDS );
		$this->insert_collaboration_row( HOUR_IN_SECONDS );

		$this->assertSame( 5, $this->get_collaboration_row_count() );

		wp_delete_old_collaboration_data();

		$this->assertSame( 2, $this->get_collaboration_row_count(), 'Only the 2 recent rows should survive cleanup.' );
	}

	/**
	 * @ticket 64696
	 */
	public function test_cron_cleanup_hook_is_registered(): void {
		$this->assertSame(
			10,
			has_action( 'wp_delete_old_collaboration_data', 'wp_delete_old_collaboration_data' ),
			'The wp_delete_old_collaboration_data action should be hooked in default-filters.php.'
		);
	}

	/*
	 * Route registration guard tests.
	 */

	/**
	 * @ticket 64696
	 */
	public function test_collaboration_routes_not_registered_when_db_version_is_old(): void {
		update_option( 'db_version', 61839 );

		// Reset the global REST server so rest_get_server() builds a fresh instance.
		$GLOBALS['wp_rest_server'] = null;

		$server = rest_get_server();
		$routes = $server->get_routes();

		$this->assertArrayNotHasKey( '/wp-collaboration/v1/updates', $routes, 'Collaboration routes should not be registered when db_version is below 61841.' );

		// Reset again so subsequent tests get a server with the correct db_version.
		$GLOBALS['wp_rest_server'] = null;
	}

	/*
	 * Awareness race condition tests.
	 */

	/**
	 * Awareness state set by separate clients should be preserved across sequential dispatches.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_awareness_preserved_across_separate_upserts(): void {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 sets awareness.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'pos-a' ) ),
			)
		);

		// Client 2 sets awareness (simulating a concurrent request).
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'cursor' => 'pos-b' ) ),
			)
		);

		$awareness = $response->get_data()['rooms'][0]['awareness'];

		$this->assertArrayHasKey( '1', $awareness, 'Client 1 awareness should be present.' );
		$this->assertArrayHasKey( '2', $awareness, 'Client 2 awareness should be present.' );
		$this->assertSame( array( 'cursor' => 'pos-a' ), $awareness['1'] );
		$this->assertSame( array( 'cursor' => 'pos-b' ), $awareness['2'] );
	}

	/**
	 * Awareness rows should not affect get_updates_after_cursor() or get_cursor().
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_awareness_rows_do_not_affect_cursor(): void {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 sets awareness (creates awareness row in table).
		$response1 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'pos-a' ) ),
			)
		);

		// With no updates, cursor should be 0.
		$data1 = $response1->get_data();
		$this->assertSame( 0, $data1['rooms'][0]['end_cursor'], 'Awareness rows should not affect the cursor.' );
		$this->assertSame( 0, $data1['rooms'][0]['total_updates'], 'Awareness rows should not count as updates.' );
		$this->assertEmpty( $data1['rooms'][0]['updates'], 'Awareness rows should not appear as updates.' );

		// Now add an update.
		$update    = array(
			'type' => 'update',
			'data' => 'dGVzdA==',
		);
		$response2 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'pos-a' ), array( $update ) ),
			)
		);

		$data2 = $response2->get_data();
		$this->assertSame( 1, $data2['rooms'][0]['total_updates'], 'Only updates should count toward total.' );
	}

	/**
	 * Compaction (remove_updates_before_cursor) should not delete awareness rows.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_compaction_does_not_delete_awareness_rows(): void {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 sets awareness.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'pos-a' ) ),
			)
		);

		// Client 2 sends updates.
		$updates = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$updates[] = array(
				'type' => 'update',
				'data' => base64_encode( "update-$i" ),
			);
		}
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'cursor' => 'pos-b' ), $updates ),
			)
		);

		$cursor = $response->get_data()['rooms'][0]['end_cursor'];

		// Client 2 sends a compaction.
		$compaction = array(
			'type' => 'compaction',
			'data' => base64_encode( 'compacted' ),
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', $cursor, array( 'cursor' => 'pos-b' ), array( $compaction ) ),
			)
		);

		// Client 3 checks awareness — client 1 should still be present.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '3', 0, array( 'cursor' => 'pos-c' ) ),
			)
		);

		$awareness = $response->get_data()['rooms'][0]['awareness'];
		$this->assertArrayHasKey( '1', $awareness, 'Client 1 awareness should survive compaction.' );
	}

	/**
	 * Expired awareness rows should be filtered from results but remain in the
	 * table until cron cleanup runs.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_expired_awareness_rows_cleaned_up(): void {
		wp_set_current_user( self::$editor_id );

		global $wpdb;

		$room = $this->get_post_room();

		// Insert an awareness row clearly older than the 60-second cron threshold.
		$wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => $room,
				'type'      => 'awareness',
				'client_id' => '99',
				'user_id'   => self::$editor_id,
				'data'      => wp_json_encode( array( 'cursor' => 'stale' ) ),
				'date_gmt'  => gmdate( 'Y-m-d H:i:s', time() - 120 ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		// Client 1 polls — the expired row should not appear in results.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'pos-a' ) ),
			)
		);

		$awareness = $response->get_data()['rooms'][0]['awareness'];
		$this->assertArrayNotHasKey( '99', $awareness, 'Expired awareness entry should not appear.' );
		$this->assertArrayHasKey( '1', $awareness, 'Fresh client awareness should appear.' );

		// The expired row still exists in the table (no inline DELETE on the read path).
		$expired_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE type = 'awareness' AND room = %s AND client_id = %s",
				$room,
				'99'
			)
		);
		$this->assertSame( 1, $expired_count, 'Expired awareness row should still exist in the table until cron runs.' );

		// Cron cleanup removes the expired row.
		wp_delete_old_collaboration_data();

		$post_cron_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE type = 'awareness' AND room = %s AND client_id = %s",
				$room,
				'99'
			)
		);
		$this->assertSame( 0, $post_cron_count, 'Expired awareness row should be deleted after cron cleanup.' );
	}

	/**
	 * Cron cleanup should remove expired awareness rows.
	 *
	 * @ticket 64696
	 */
	public function test_cron_cleanup_deletes_expired_awareness_rows(): void {
		global $wpdb;

		// Insert an awareness row older than 60 seconds.
		$wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => $this->get_post_room(),
				'type'      => 'awareness',
				'client_id' => '42',
				'user_id'   => self::$editor_id,
				'data'      => wp_json_encode( array( 'cursor' => 'old' ) ),
				'date_gmt'  => gmdate( 'Y-m-d H:i:s', time() - 120 ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		// Insert a recent collaboration row (should survive).
		$this->insert_collaboration_row( HOUR_IN_SECONDS );

		$this->assertSame( 1, $this->get_collaboration_row_count(), 'Collaboration table should have 1 sync row.' );
		$this->assertSame( 1, $this->get_awareness_row_count(), 'Collaboration table should have 1 awareness row.' );

		wp_delete_old_collaboration_data();

		$this->assertSame( 1, $this->get_collaboration_row_count(), 'Only the recent sync row should survive cron cleanup.' );
		$this->assertSame( 0, $this->get_awareness_row_count(), 'Expired awareness row should be deleted after cron cleanup.' );
	}

	/**
	 * Verifies that user_id is stored as a dedicated column,
	 * not embedded inside the data JSON blob.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_awareness_user_id_round_trip() {
		global $wpdb;

		wp_set_current_user( self::$editor_id );

		$room  = $this->get_post_room();
		$rooms = array( $this->build_room( $room, '1', 0, array( 'cursor' => array( 'x' => 10 ) ) ) );

		$response = $this->dispatch_collaboration( $rooms );
		$this->assertSame( 200, $response->get_status(), 'Dispatch should succeed.' );

		// Query the collaboration table directly for the awareness row.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, data FROM {$wpdb->collaboration} WHERE room = %s AND type = 'awareness' AND client_id = %s",
				$room,
				'1'
			)
		);

		$this->assertNotNull( $row, 'Awareness row should exist.' );
		$this->assertSame( self::$editor_id, (int) $row->user_id, 'user_id column should match the editor.' );
		$this->assertStringNotContainsString( 'user_id', $row->data, 'data column should not contain user_id.' );
	}

	/**
	 * Verifies that the is_array() guard in get_awareness_state() skips
	 * rows where the data column contains valid JSON that is not an array.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_awareness_non_array_json_ignored() {
		global $wpdb;

		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Insert a malformed awareness row with a JSON string (not an array).
		$wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => $room,
				'type'      => 'awareness',
				'client_id' => '99',
				'user_id'   => self::$editor_id,
				'data'      => '"hello"',
				'date_gmt'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		// Dispatch as a different client so the response includes other clients' awareness.
		$rooms    = array( $this->build_room( $room, '2', 0, array( 'cursor' => 'here' ) ) );
		$response = $this->dispatch_collaboration( $rooms );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$awareness = $data['rooms'][0]['awareness'];

		$this->assertArrayNotHasKey( '99', $awareness, 'Non-array JSON row should not appear in awareness.' );
		$this->assertArrayHasKey( '2', $awareness, 'The dispatching client should appear in awareness.' );
	}

	/**
	 * Validates that REST accepts room names at the column width boundary (191 chars).
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_room_name_at_max_length_accepted() {
		wp_set_current_user( self::$editor_id );

		// 191 characters using a collection room: 'root/' (5) + 186 chars.
		$room = 'root/' . str_repeat( 'a', 186 );
		$this->assertSame( 191, strlen( $room ), 'Room name should be 191 characters.' );

		$rooms    = array( $this->build_room( $room ) );
		$response = $this->dispatch_collaboration( $rooms );

		$this->assertSame( 200, $response->get_status(), 'REST should accept room names at 191 characters.' );
	}

	/**
	 * Validates that REST rejects room names exceeding the column width (191 chars).
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_room_name_max_length_rejected() {
		wp_set_current_user( self::$editor_id );

		// 192 characters: 'postType/' (9) + 183 chars.
		$long_room = 'postType/' . str_repeat( 'a', 183 );
		$this->assertSame( 192, strlen( $long_room ), 'Room name should be 192 characters.' );

		$rooms    = array( $this->build_room( $long_room ) );
		$response = $this->dispatch_collaboration( $rooms );

		$this->assertSame( 400, $response->get_status(), 'REST should reject room names exceeding 191 characters.' );
	}

	/**
	 * Verifies that sending awareness as null reads existing state without writing.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_null_awareness_skips_write() {
		global $wpdb;

		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 dispatches with awareness state (writes a row).
		$rooms = array( $this->build_room( $room, '1', 0, array( 'cursor' => 'active' ) ) );
		$this->dispatch_collaboration( $rooms );

		// Client 2 dispatches with awareness = null (should not write).
		$request = new WP_REST_Request( 'POST', '/wp-collaboration/v1/updates' );
		$request->set_body_params(
			array(
				'rooms' => array(
					array(
						'after'     => 0,
						'awareness' => null,
						'client_id' => '2',
						'room'      => $room,
						'updates'   => array(),
					),
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Null awareness dispatch should succeed.' );

		// Assert collaboration table has exactly 1 awareness row (client 1 only).
		$row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE type = 'awareness'" );
		$this->assertSame( 1, $row_count, 'Only client 1 should have an awareness row.' );

		// Assert response still contains client 1's awareness (read still works).
		$data      = $response->get_data();
		$awareness = $data['rooms'][0]['awareness'];
		$this->assertArrayHasKey( '1', $awareness, 'Client 1 awareness should be readable by client 2.' );
		$this->assertSame( array( 'cursor' => 'active' ), $awareness['1'], 'Client 1 awareness state should match.' );
	}

	/*
	 * Cache tests.
	 */

	/**
	 * Verifies that a normal awareness write updates the cache in-place
	 * so the next client's poll hits the cache instead of the database.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_awareness_cache_hit_after_write(): void {
		global $wpdb;

		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 polls with awareness — primes cache via get, then
		// updates it in-place via set.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'pos-a' ) ),
			)
		);

		// Client 2 polls — awareness read should hit the warm cache.
		$queries_before = $wpdb->num_queries;

		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '2', 0, array( 'cursor' => 'pos-b' ) ),
			)
		);

		$queries_after = $wpdb->num_queries;

		// With cache hit: awareness read is free, so:
		//   awareness UPDATE (1) + snapshot SELECT (1) + awareness INSERT (1) = 3.
		// Without cache: adds awareness SELECT = 4.
		$this->assertLessThanOrEqual(
			3,
			$queries_after - $queries_before,
			'Awareness cache hit should skip the awareness SELECT query.'
		);
	}

	/**
	 * Verifies that the in-place cache update after a write produces
	 * correct data, not stale state.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_awareness_cache_reflects_latest_write(): void {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 sets initial awareness.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'initial' ) ),
			)
		);

		// Client 1 updates awareness to a new value.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'cursor' => 'updated' ) ),
			)
		);

		$awareness = $response->get_data()['rooms'][0]['awareness'];
		$this->assertSame(
			array( 'cursor' => 'updated' ),
			$awareness['1'],
			'Awareness should reflect the updated state, not a stale cache.'
		);
	}

	/*
	 * Query count tests.
	 */

	/*
	 * Payload limit and permission hardening tests.
	 */

	/**
	 * Verifies that a request body exceeding MAX_BODY_SIZE returns a 413 error.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_oversized_body_rejected(): void {
		wp_set_current_user( self::$editor_id );

		$request = new WP_REST_Request( 'POST', '/wp-collaboration/v1/updates' );
		// Set a body larger than MAX_BODY_SIZE (16 MB).
		$request->set_body( str_repeat( 'x', 16 * MB_IN_BYTES + 1 ) );
		$request->set_body_params(
			array(
				'rooms' => array(
					$this->build_room( $this->get_post_room() ),
				),
			)
		);

		$server = new WP_HTTP_Polling_Collaboration_Server(
			new WP_Collaboration_Table_Storage()
		);

		$result = $server->validate_request( $request );

		$this->assertWPError( $result );
		$this->assertSame( 'rest_collaboration_body_too_large', $result->get_error_code() );
		$this->assertSame( 413, $result->get_error_data()['status'] );
	}

	/**
	 * Verifies that more than MAX_ROOMS_PER_REQUEST rooms is rejected by schema validation.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_too_many_rooms_rejected(): void {
		wp_set_current_user( self::$editor_id );

		$rooms = array();
		for ( $i = 0; $i <= WP_HTTP_Polling_Collaboration_Server::MAX_ROOMS_PER_REQUEST; $i++ ) {
			$post_id = self::factory()->post->create( array( 'post_author' => self::$editor_id ) );
			$rooms[] = $this->build_room( 'postType/post:' . $post_id, (string) $i );
		}

		$response = $this->dispatch_collaboration( $rooms );

		$this->assertSame( 400, $response->get_status(), 'Exceeding MAX_ROOMS_PER_REQUEST should return 400.' );
	}

	/**
	 * Verifies that a non-numeric object ID in a room name is rejected.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_non_numeric_object_id_rejected(): void {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( 'postType/post:abc' ),
			)
		);

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * Verifies that a post type mismatch (room says page but post is a post) is rejected.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_post_type_mismatch_rejected(): void {
		wp_set_current_user( self::$editor_id );

		// self::$post_id is a 'post', but the room claims 'page'.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( 'postType/page:' . self::$post_id ),
			)
		);

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * Verifies that a taxonomy term that doesn't exist is rejected.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_nonexistent_taxonomy_term_rejected(): void {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( 'taxonomy/category:999999' ),
			)
		);

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * Verifies that a taxonomy term in the wrong taxonomy is rejected.
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_taxonomy_term_wrong_taxonomy_rejected(): void {
		wp_set_current_user( self::$editor_id );

		// Create a term in 'category' taxonomy.
		$term = self::factory()->term->create( array( 'taxonomy' => 'category' ) );

		// Try to access it as a 'post_tag' term.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( 'taxonomy/post_tag:' . $term ),
			)
		);

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
	}

	/**
	 * An idle poll (no new updates) should use at most 4 queries per room:
	 * 1. SELECT … FROM collaboration WHERE type = 'awareness' (read + ownership check)
	 * 2. UPDATE … collaboration (awareness upsert — update path)
	 * 3. SELECT MAX(id), COUNT(*) FROM collaboration (snapshot + count)
	 * 4. INSERT … collaboration (awareness upsert — insert path, only on first poll)
	 *
	 * @ticket 64696
	 */
	public function test_collaboration_idle_poll_query_count(): void {
		global $wpdb;

		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Prime awareness so subsequent polls are idle heartbeats.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', 0, array( 'user' => 'test' ) ),
			)
		);

		$cursor = 0;

		// Count queries for an idle poll (no updates to fetch).
		$queries_before = $wpdb->num_queries;

		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, '1', $cursor, array( 'user' => 'test' ) ),
			)
		);

		$this->assertSame( 200, $response->get_status(), 'Idle poll should succeed.' );

		$query_count = $wpdb->num_queries - $queries_before;

		$this->assertLessThanOrEqual(
			4,
			$query_count,
			sprintf( 'Idle poll should use at most 4 queries per room, used %d.', $query_count )
		);
	}
}
