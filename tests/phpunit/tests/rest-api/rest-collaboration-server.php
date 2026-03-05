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
	 * @param int    $client_id Client ID.
	 * @param int    $cursor    Cursor value for the 'after' parameter.
	 * @param array  $awareness Awareness state.
	 * @param array  $updates   Array of updates.
	 * @return array Room request data.
	 */
	private function build_room( $room, $client_id = 1, $cursor = 0, $awareness = array(), $updates = array() ) {
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
	 * @param array  $rooms     Array of room request data.
	 * @param string $namespace REST namespace to use. Defaults to the primary namespace.
	 * @return WP_REST_Response Response object.
	 */
	private function dispatch_collaboration( $rooms, $namespace = 'wp-collaboration/v1' ) {
		$request = new WP_REST_Request( 'POST', '/' . $namespace . '/updates' );
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
		$this->assertArrayHasKey( '/wp-sync/v1/updates', $routes, 'Deprecated wp-sync/v1 route should still be registered.' );
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
				$this->build_room( $room, 1, 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 requests updates from the beginning.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0 ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'client1' ), array( $update ) ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 should see the sync_step1 update.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0 ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 should see the sync_step2 update.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0 ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'client1' ), $updates ),
			)
		);

		// Client 2 should see both updates.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0 ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 should receive the exact same data.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0 ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0, array( 'user' => 'c2' ), array( $update ) ),
			)
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, 3, 0, array( 'user' => 'c3' ), array( $update ) ),
			)
		);

		// Any client should see total_updates = 3.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 4, 0 ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), array( $update ) ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), $updates ),
			)
		);

		// Client 1 polls again. It is the lowest (only) client, so it is the compactor.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ) ),
			)
		);

		$data = $response->get_data();
		$this->assertTrue( $data['rooms'][0]['should_compact'] );
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), $updates ),
			)
		);

		// Client 2 (higher ID than client 1) should not be the compactor.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0, array( 'user' => 'c2' ) ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), array( $update ) ),
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
				$this->build_room( $room, 2, $end_cursor, array( 'user' => 'c2' ), array( $compaction ) ),
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
				$this->build_room( $room, 3, 0, array( 'user' => 'c3' ), array( $stale_compaction ) ),
			)
		);

		$this->assertSame( 200, $response->get_status() );

		// Verify the newer compaction is preserved and the stale one was not stored.
		$response    = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 4, 0, array( 'user' => 'c4' ) ),
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
				$this->build_room( $this->get_post_room(), 1, 0, $awareness ),
			)
		);

		$data = $response->get_data();
		$this->assertArrayHasKey( 1, $data['rooms'][0]['awareness'] );
		$this->assertSame( $awareness, $data['rooms'][0]['awareness'][1] );
	}

	public function test_collaboration_awareness_shows_multiple_clients() {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 connects.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, 1, 0, array( 'name' => 'Client 1' ) ),
			)
		);

		// Client 2 connects.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0, array( 'name' => 'Client 2' ) ),
			)
		);

		$data      = $response->get_data();
		$awareness = $data['rooms'][0]['awareness'];

		$this->assertArrayHasKey( 1, $awareness );
		$this->assertArrayHasKey( 2, $awareness );
		$this->assertSame( array( 'name' => 'Client 1' ), $awareness[1] );
		$this->assertSame( array( 'name' => 'Client 2' ), $awareness[2] );
	}

	public function test_collaboration_awareness_updates_existing_client() {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Client 1 connects with initial awareness.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, 1, 0, array( 'cursor' => 'start' ) ),
			)
		);

		// Client 1 updates its awareness.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 1, 0, array( 'cursor' => 'updated' ) ),
			)
		);

		$data      = $response->get_data();
		$awareness = $data['rooms'][0]['awareness'];

		// Should have exactly one entry for client 1 with updated state.
		$this->assertCount( 1, $awareness );
		$this->assertSame( array( 'cursor' => 'updated' ), $awareness[1] );
	}

	public function test_collaboration_awareness_client_id_cannot_be_used_by_another_user() {
		wp_set_current_user( self::$editor_id );

		$room = $this->get_post_room();

		// Editor establishes awareness with client_id 1.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, 1, 0, array( 'name' => 'Editor' ) ),
			)
		);

		// A different user tries to use the same client_id.
		$editor_id_2 = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id_2 );

		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 1, 0, array( 'name' => 'Impostor' ) ),
			)
		);

		$this->assertErrorResponse( 'rest_cannot_edit', $response, 403 );
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
				$this->build_room( $room1, 1, 0, array( 'user' => 'client1' ), array( $update ) ),
			)
		);

		// Client 2 queries both rooms.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room1, 2, 0 ),
				$this->build_room( $room2, 2, 0 ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);
		$cursor1   = $response1->get_data()['rooms'][0]['end_cursor'];

		// Second request with more updates.
		$response2 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, $cursor1, array( 'user' => 'c2' ), array( $update ) ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);

		// Client 2 fetches updates and gets a cursor.
		$response1 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0, array( 'user' => 'c2' ) ),
			)
		);
		$data1     = $response1->get_data();
		$cursor1   = $data1['rooms'][0]['end_cursor'];

		$this->assertNotEmpty( $data1['rooms'][0]['updates'], 'First poll should return updates.' );

		// Client 2 polls again using the cursor from the first poll, with no new updates.
		$response2 = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, $cursor1, array( 'user' => 'c2' ) ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), array( $update ) ),
			)
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0, array( 'user' => 'c2' ), array( $update ) ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), $initial_updates ),
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
				$this->build_room( $room, 2, 0, array( 'user' => 'c2' ), array( $concurrent_update ) ),
			)
		);

		// Client 1 sends a compaction update using its cursor.
		$compaction_update = array(
			'type' => 'compaction',
			'data' => base64_encode( 'compacted-state' ),
		);
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, 1, $cursor, array( 'user' => 'c1' ), array( $compaction_update ) ),
			)
		);

		// Client 3 requests all updates from the beginning.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 3, 0, array( 'user' => 'c3' ) ),
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
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), $updates ),
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
				$this->build_room( $room, 1, $cursor, array( 'user' => 'c1' ), array( $compaction ) ),
			)
		);

		// Client 2 checks the state.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0, array( 'user' => 'c2' ) ),
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
	 * @param string       $label          A label stored in the update_value for identification.
	 */
	private function insert_collaboration_row( int $age_in_seconds, string $label = 'test' ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'         => $this->get_post_room(),
				'update_value' => wp_json_encode(
					array(
						'type' => 'update',
						'data' => $label,
					)
				),
				'created_at'   => gmdate( 'Y-m-d H:i:s', time() - $age_in_seconds ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Returns the number of rows in the collaboration table.
	 *
	 * @return positive-int Row count.
	 */
	private function get_collaboration_row_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->collaboration}" );
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
		$remaining = $wpdb->get_col( "SELECT update_value FROM {$wpdb->collaboration}" );

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
		update_option( 'db_version', 61697 );

		// Reset the global REST server so rest_get_server() builds a fresh instance.
		$GLOBALS['wp_rest_server'] = null;

		$server = rest_get_server();
		$routes = $server->get_routes();

		$this->assertArrayNotHasKey( '/wp-collaboration/v1/updates', $routes, 'Collaboration routes should not be registered when db_version is below 61698.' );
		$this->assertArrayNotHasKey( '/wp-sync/v1/updates', $routes, 'Deprecated sync routes should not be registered when db_version is below 61698.' );

		// Reset again so subsequent tests get a server with the correct db_version.
		$GLOBALS['wp_rest_server'] = null;
	}

	/*
	 * Deprecated route tests.
	 *
	 * The wp-sync/v1 namespace is retained as a deprecated alias for
	 * backward compatibility with the Gutenberg plugin, which still
	 * uses it during its transition to wp-collaboration/v1.
	 */

	/**
	 * Verifies the deprecated wp-sync/v1 route includes a deprecation header
	 * so Gutenberg plugin consumers are informed of the namespace change.
	 *
	 * @ticket 64696
	 */
	public function test_deprecated_sync_route_returns_deprecation_header(): void {
		wp_set_current_user( self::$editor_id );

		$response = $this->dispatch_collaboration(
			array( $this->build_room( $this->get_post_room() ) ),
			'wp-sync/v1'
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			'wp-sync/v1 is deprecated, use wp-collaboration/v1',
			$response->get_headers()['X-WP-Deprecated']
		);
	}

	/**
	 * Verifies the deprecated wp-sync/v1 route still processes requests
	 * correctly, ensuring Gutenberg plugin compatibility during the transition.
	 *
	 * @ticket 64696
	 */
	public function test_deprecated_sync_route_functions_correctly(): void {
		wp_set_current_user( self::$editor_id );

		$room   = $this->get_post_room();
		$update = array(
			'type' => 'update',
			'data' => 'dGVzdA==',
		);

		// Send update via deprecated route.
		$this->dispatch_collaboration(
			array(
				$this->build_room( $room, 1, 0, array( 'user' => 'c1' ), array( $update ) ),
			),
			'wp-sync/v1'
		);

		// Retrieve via primary route.
		$response = $this->dispatch_collaboration(
			array(
				$this->build_room( $room, 2, 0 ),
			)
		);

		$data = $response->get_data();
		$this->assertNotEmpty( $data['rooms'][0]['updates'], 'Updates sent via deprecated route should be retrievable via primary route.' );
	}
}
