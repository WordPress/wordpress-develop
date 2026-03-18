<?php
/**
 * Tests for the `invalidates_query_cache` parameter of `register_meta()`.
 *
 * @group meta
 * @group cache
 *
 * @ticket 64696
 */
class Tests_Meta_InvalidatesQueryCache extends WP_UnitTestCase {

	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
	}

	public function tear_down() {
		unregister_meta_key( 'post', 'nocache_meta' );
		unregister_meta_key( 'post', 'nocache_meta', 'post' );
		unregister_meta_key( 'post', 'normal_meta' );
		parent::tear_down();
	}

	/**
	 * The `invalidates_query_cache` argument should default to true.
	 */
	public function test_default_value_is_true() {
		register_post_meta( '', 'normal_meta', array() );

		$meta_keys = get_registered_meta_keys( 'post' );
		$this->assertTrue( $meta_keys['normal_meta']['invalidates_query_cache'] );
	}

	/**
	 * The `invalidates_query_cache` argument should be stored when set to false.
	 */
	public function test_registered_as_false() {
		register_post_meta(
			'',
			'nocache_meta',
			array( 'invalidates_query_cache' => false )
		);

		$meta_keys = get_registered_meta_keys( 'post' );
		$this->assertFalse( $meta_keys['nocache_meta']['invalidates_query_cache'] );
	}

	/**
	 * Adding post meta for a non-cacheable key should not bump last_changed.
	 */
	public function test_add_post_meta_does_not_invalidate_cache() {
		register_post_meta(
			'',
			'nocache_meta',
			array( 'invalidates_query_cache' => false )
		);

		// Prime the last_changed value.
		wp_cache_set_last_changed( 'posts' );
		$before = wp_cache_get_last_changed( 'posts' );

		usleep( 1000 );
		add_post_meta( self::$post_id, 'nocache_meta', 'value1' );

		$after = wp_cache_get_last_changed( 'posts' );
		$this->assertSame( $before, $after, 'last_changed should not change for non-cacheable meta.' );
	}

	/**
	 * Updating post meta for a non-cacheable key should not bump last_changed.
	 */
	public function test_update_post_meta_does_not_invalidate_cache() {
		register_post_meta(
			'',
			'nocache_meta',
			array( 'invalidates_query_cache' => false )
		);

		add_post_meta( self::$post_id, 'nocache_meta', 'value1' );

		wp_cache_set_last_changed( 'posts' );
		$before = wp_cache_get_last_changed( 'posts' );

		usleep( 1000 );
		update_post_meta( self::$post_id, 'nocache_meta', 'value2' );

		$after = wp_cache_get_last_changed( 'posts' );
		$this->assertSame( $before, $after, 'last_changed should not change for non-cacheable meta.' );
	}

	/**
	 * Deleting post meta for a non-cacheable key should not bump last_changed.
	 */
	public function test_delete_post_meta_does_not_invalidate_cache() {
		register_post_meta(
			'',
			'nocache_meta',
			array( 'invalidates_query_cache' => false )
		);

		add_post_meta( self::$post_id, 'nocache_meta', 'value1' );

		wp_cache_set_last_changed( 'posts' );
		$before = wp_cache_get_last_changed( 'posts' );

		usleep( 1000 );
		delete_post_meta( self::$post_id, 'nocache_meta' );

		$after = wp_cache_get_last_changed( 'posts' );
		$this->assertSame( $before, $after, 'last_changed should not change for non-cacheable meta.' );
	}

	/**
	 * Regular meta should still invalidate the cache as before.
	 */
	public function test_regular_meta_still_invalidates_cache() {
		wp_cache_set_last_changed( 'posts' );
		$before = wp_cache_get_last_changed( 'posts' );

		// Small sleep to ensure microtime differs.
		usleep( 1000 );
		add_post_meta( self::$post_id, 'regular_unregistered_meta', 'value1' );

		$after = wp_cache_get_last_changed( 'posts' );
		$this->assertNotSame( $before, $after, 'last_changed should change for regular meta.' );
	}

	/**
	 * Meta registered with invalidates_query_cache true should still invalidate.
	 */
	public function test_registered_cacheable_meta_still_invalidates_cache() {
		register_post_meta(
			'',
			'normal_meta',
			array( 'invalidates_query_cache' => true )
		);

		wp_cache_set_last_changed( 'posts' );
		$before = wp_cache_get_last_changed( 'posts' );

		usleep( 1000 );
		add_post_meta( self::$post_id, 'normal_meta', 'value1' );

		$after = wp_cache_get_last_changed( 'posts' );
		$this->assertNotSame( $before, $after, 'last_changed should change for cacheable meta.' );
	}

	/**
	 * WP_Meta_Query should refuse to query by a non-cacheable meta key.
	 *
	 * @expectedIncorrectUsage WP_Meta_Query::get_sql_for_clause
	 */
	public function test_meta_query_refuses_non_cacheable_key() {
		register_post_meta(
			'',
			'nocache_meta',
			array( 'invalidates_query_cache' => false )
		);

		$meta_query = new WP_Meta_Query(
			array(
				array(
					'key'   => 'nocache_meta',
					'value' => 'test',
				),
			)
		);

		$sql = $meta_query->get_sql( 'post', 'wp_posts', 'ID' );

		$this->assertStringNotContainsString( 'nocache_meta', $sql['where'], 'Non-cacheable meta key should not appear in WHERE clause.' );
		$this->assertStringNotContainsString( 'nocache_meta', $sql['join'], 'Non-cacheable meta key should not appear in JOIN clause.' );
	}

	/**
	 * WP_Meta_Query should work normally for regular meta keys.
	 */
	public function test_meta_query_allows_regular_key() {
		$meta_query = new WP_Meta_Query(
			array(
				array(
					'key'   => 'some_regular_key',
					'value' => 'test',
				),
			)
		);

		$sql = $meta_query->get_sql( 'post', 'wp_posts', 'ID' );

		$this->assertStringContainsString( 'some_regular_key', $sql['where'], 'Regular meta key should appear in WHERE clause.' );
	}

	/**
	 * A key registered for a specific post type should skip cache invalidation.
	 */
	public function test_subtype_registration_skips_cache_invalidation() {
		register_post_meta(
			'post',
			'nocache_meta',
			array( 'invalidates_query_cache' => false )
		);

		wp_cache_set_last_changed( 'posts' );
		$before = wp_cache_get_last_changed( 'posts' );

		usleep( 1000 );
		add_post_meta( self::$post_id, 'nocache_meta', 'value1' );

		$after = wp_cache_get_last_changed( 'posts' );
		$this->assertSame( $before, $after, 'last_changed should not change for non-cacheable meta registered on a specific post type.' );
	}

	/**
	 * A key registered for a specific post type should be refused in meta queries.
	 *
	 * @expectedIncorrectUsage WP_Meta_Query::get_sql_for_clause
	 */
	public function test_subtype_registration_refuses_meta_query() {
		register_post_meta(
			'post',
			'nocache_meta',
			array( 'invalidates_query_cache' => false )
		);

		$meta_query = new WP_Meta_Query(
			array(
				array(
					'key'   => 'nocache_meta',
					'value' => 'test',
				),
			)
		);

		$sql = $meta_query->get_sql( 'post', 'wp_posts', 'ID' );

		$this->assertStringNotContainsString( 'nocache_meta', $sql['where'], 'Non-cacheable meta key should not appear in WHERE clause.' );
	}
}
