<?php
/**
 * Tests that WP_Sync_Post_Meta_Storage bypasses WordPress post meta caches.
 *
 * The storage class uses direct database queries instead of the post meta API
 * to avoid cache invalidation side effects (wp_cache_set_posts_last_changed()
 * and wp_cache_delete() calls). These tests verify that contract.
 *
 * @package WordPress
 * @subpackage Collaboration
 *
 * @group collaboration
 * @group cache
 */
class Tests_Collaboration_WpSyncPostMetaStorageCache extends WP_UnitTestCase {

	protected static $editor_id;
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );
		self::$post_id   = $factory->post->create( array( 'post_author' => self::$editor_id ) );
		update_option( 'wp_collaboration_enabled', 1 );
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$editor_id );
		delete_option( 'wp_collaboration_enabled' );
		wp_delete_post( self::$post_id, true );
	}

	public function set_up() {
		parent::set_up();
		update_option( 'wp_collaboration_enabled', 1 );

		// Reset storage post ID cache to ensure clean state after transaction rollback.
		$reflection = new ReflectionProperty( 'WP_Sync_Post_Meta_Storage', 'storage_post_ids' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}
		$reflection->setValue( null, array() );
	}

	/**
	 * Returns the room identifier for the test post.
	 *
	 * @return string Room identifier.
	 */
	private function get_room(): string {
		return 'postType/post:' . self::$post_id;
	}

	/**
	 * Creates the storage post for the room and returns its ID.
	 *
	 * Adds a seed update to trigger storage post creation, then looks up
	 * the resulting post ID.
	 *
	 * @param WP_Sync_Post_Meta_Storage $storage Storage instance.
	 * @param string                    $room    Room identifier.
	 * @return int Storage post ID.
	 */
	private function create_storage_post( WP_Sync_Post_Meta_Storage $storage, string $room ): int {
		$storage->add_update(
			$room,
			array(
				'type' => 'update',
				'data' => 'seed',
			)
		);

		$posts = get_posts(
			array(
				'post_type'      => 'wp_sync_storage',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'name'           => md5( $room ),
				'fields'         => 'ids',
			)
		);

		$storage_post_id = array_first( $posts );
		$this->assertIsInt( $storage_post_id );

		return $storage_post_id;
	}

	/**
	 * Primes the post meta object cache for a given post and returns the cached value.
	 *
	 * @param int $post_id Post ID.
	 * @return array Cached meta data.
	 */
	private function prime_and_get_meta_cache( int $post_id ): array {
		update_meta_cache( 'post', array( $post_id ) );

		$cached = wp_cache_get( $post_id, 'post_meta' );
		$this->assertNotFalse( $cached, 'Post meta cache should be primed.' );

		return $cached;
	}

	/*
	 * Write operations must not invalidate the post meta object cache.
	 */

	public function test_add_update_does_not_invalidate_post_meta_cache() {
		$storage         = new WP_Sync_Post_Meta_Storage();
		$room            = $this->get_room();
		$storage_post_id = $this->create_storage_post( $storage, $room );
		$cached_before   = $this->prime_and_get_meta_cache( $storage_post_id );

		$storage->add_update(
			$room,
			array(
				'type' => 'update',
				'data' => 'new',
			)
		);

		$cached_after = wp_cache_get( $storage_post_id, 'post_meta' );
		$this->assertSame(
			$cached_before,
			$cached_after,
			'add_update() must not invalidate the post meta cache.'
		);
	}

	public function test_set_awareness_state_insert_does_not_invalidate_post_meta_cache() {
		$storage         = new WP_Sync_Post_Meta_Storage();
		$room            = $this->get_room();
		$storage_post_id = $this->create_storage_post( $storage, $room );
		$cached_before   = $this->prime_and_get_meta_cache( $storage_post_id );

		// First call triggers an INSERT (no existing awareness row).
		$storage->set_awareness_state( $room, array( 1 => array( 'name' => 'Test' ) ) );

		$cached_after = wp_cache_get( $storage_post_id, 'post_meta' );
		$this->assertSame(
			$cached_before,
			$cached_after,
			'set_awareness_state() INSERT path must not invalidate the post meta cache.'
		);
	}

	public function test_set_awareness_state_update_does_not_invalidate_post_meta_cache() {
		$storage         = new WP_Sync_Post_Meta_Storage();
		$room            = $this->get_room();
		$storage_post_id = $this->create_storage_post( $storage, $room );

		// Create initial awareness row (INSERT path).
		$storage->set_awareness_state( $room, array( 1 => array( 'name' => 'Initial' ) ) );

		// Prime cache after the insert.
		$cached_before = $this->prime_and_get_meta_cache( $storage_post_id );

		// Second call triggers an UPDATE (existing awareness row).
		$storage->set_awareness_state( $room, array( 1 => array( 'name' => 'Updated' ) ) );

		$cached_after = wp_cache_get( $storage_post_id, 'post_meta' );
		$this->assertSame(
			$cached_before,
			$cached_after,
			'set_awareness_state() UPDATE path must not invalidate the post meta cache.'
		);
	}

	public function test_remove_updates_before_cursor_does_not_invalidate_post_meta_cache() {
		$storage         = new WP_Sync_Post_Meta_Storage();
		$room            = $this->get_room();
		$storage_post_id = $this->create_storage_post( $storage, $room );

		// Get a cursor after the seed update.
		$storage->get_updates_after_cursor( $room, 0 );
		$cursor = $storage->get_cursor( $room );

		$cached_before = $this->prime_and_get_meta_cache( $storage_post_id );

		$storage->remove_updates_before_cursor( $room, $cursor );

		$cached_after = wp_cache_get( $storage_post_id, 'post_meta' );
		$this->assertSame(
			$cached_before,
			$cached_after,
			'remove_updates_before_cursor() must not invalidate the post meta cache.'
		);
	}

	/*
	 * Write operations must not update the 'posts' last_changed cache marker.
	 */

	public function test_add_update_does_not_update_posts_last_changed() {
		$storage = new WP_Sync_Post_Meta_Storage();
		$room    = $this->get_room();
		$this->create_storage_post( $storage, $room );

		$last_changed_before = wp_cache_get_last_changed( 'posts' );

		$storage->add_update(
			$room,
			array(
				'type' => 'update',
				'data' => 'new',
			)
		);

		$this->assertSame(
			$last_changed_before,
			wp_cache_get_last_changed( 'posts' ),
			'add_update() must not update posts last_changed.'
		);
	}

	public function test_set_awareness_state_does_not_update_posts_last_changed() {
		$storage = new WP_Sync_Post_Meta_Storage();
		$room    = $this->get_room();
		$this->create_storage_post( $storage, $room );

		$last_changed_before = wp_cache_get_last_changed( 'posts' );

		$storage->set_awareness_state( $room, array( 1 => array( 'name' => 'Test' ) ) );

		$this->assertSame(
			$last_changed_before,
			wp_cache_get_last_changed( 'posts' ),
			'set_awareness_state() must not update posts last_changed.'
		);
	}

	public function test_remove_updates_before_cursor_does_not_update_posts_last_changed() {
		$storage = new WP_Sync_Post_Meta_Storage();
		$room    = $this->get_room();
		$this->create_storage_post( $storage, $room );

		$storage->get_updates_after_cursor( $room, 0 );
		$cursor = $storage->get_cursor( $room );

		$last_changed_before = wp_cache_get_last_changed( 'posts' );

		$storage->remove_updates_before_cursor( $room, $cursor );

		$this->assertSame(
			$last_changed_before,
			wp_cache_get_last_changed( 'posts' ),
			'remove_updates_before_cursor() must not update posts last_changed.'
		);
	}

	/*
	 * Read operations must not prime the post meta object cache.
	 */

	public function test_get_awareness_state_does_not_prime_post_meta_cache() {
		$storage         = new WP_Sync_Post_Meta_Storage();
		$room            = $this->get_room();
		$storage_post_id = $this->create_storage_post( $storage, $room );

		// Populate awareness so there is data to read.
		$storage->set_awareness_state( $room, array( 1 => array( 'name' => 'Test' ) ) );

		// Clear any existing cache.
		wp_cache_delete( $storage_post_id, 'post_meta' );
		$this->assertFalse(
			wp_cache_get( $storage_post_id, 'post_meta' ),
			'Post meta cache should be empty before read.'
		);

		$storage->get_awareness_state( $room );

		$this->assertFalse(
			wp_cache_get( $storage_post_id, 'post_meta' ),
			'get_awareness_state() must not prime the post meta cache.'
		);
	}

	public function test_get_updates_after_cursor_drops_malformed_json() {
		global $wpdb;

		$storage         = new WP_Sync_Post_Meta_Storage();
		$room            = $this->get_room();
		$storage_post_id = $this->create_storage_post( $storage, $room );

		// Advance cursor past the seed update from create_storage_post().
		$storage->get_updates_after_cursor( $room, 0 );
		$cursor = $storage->get_cursor( $room );

		// Insert a valid update.
		$valid_update = array(
			'type' => 'update',
			'data' => 'dGVzdA==',
		);
		$this->assertTrue( $storage->add_update( $room, $valid_update ) );

		// Insert a malformed JSON row directly into the database.
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $storage_post_id,
				'meta_key'   => WP_Sync_Post_Meta_Storage::SYNC_UPDATE_META_KEY,
				'meta_value' => '{invalid json',
			),
			array( '%d', '%s', '%s' )
		);

		// Insert another valid update after the malformed one.
		$valid_update_2 = array(
			'type' => 'sync_step1',
			'data' => 'c3RlcDE=',
		);
		$this->assertTrue( $storage->add_update( $room, $valid_update_2 ) );

		$updates = $storage->get_updates_after_cursor( $room, $cursor );

		// The malformed row should be dropped; only the valid updates should appear.
		$this->assertCount( 2, $updates );
		$this->assertSame( $valid_update, $updates[0] );
		$this->assertSame( $valid_update_2, $updates[1] );
	}

	public function test_get_updates_after_cursor_does_not_prime_post_meta_cache() {
		$storage         = new WP_Sync_Post_Meta_Storage();
		$room            = $this->get_room();
		$storage_post_id = $this->create_storage_post( $storage, $room );

		// Clear any existing cache.
		wp_cache_delete( $storage_post_id, 'post_meta' );
		$this->assertFalse(
			wp_cache_get( $storage_post_id, 'post_meta' ),
			'Post meta cache should be empty before read.'
		);

		$storage->get_updates_after_cursor( $room, 0 );

		$this->assertFalse(
			wp_cache_get( $storage_post_id, 'post_meta' ),
			'get_updates_after_cursor() must not prime the post meta cache.'
		);
	}
}
