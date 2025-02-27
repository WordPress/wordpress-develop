<?php

/**
 * @group query
 * @group meta
 */
class Tests_Lazy_Load_Post_Meta extends WP_UnitTestCase {
	/**
	 * Post IDs.
	 *
	 * @var int[]
	 */
	private static $post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Register CPT for use with shared fixtures.
		register_post_type( 'wptests_pt' );

		self::$post_ids = $factory->post->create_many( 5, array( 'post_type' => 'wptests_pt' ) );
		foreach ( self::$post_ids as $post_id ) {
			update_post_meta( $post_id, 'foo', 'bar' );
		}
	}

	/**
	 * @dataProvider data_lazy_load_post_meta
	 * @ticket 57496
	 */
	public function test_lazy_load_post_meta( $query_args ) {
		wp_cache_delete_multiple( self::$post_ids, 'posts' );
		wp_cache_delete_multiple( self::$post_ids, 'post_meta' );
		$action1 = new MockAction();
		$action2 = new MockAction();
		add_filter( 'update_post_metadata_cache', array( $action1, 'filter' ), 10, 2 );
		add_action( 'metadata_lazyloader_queued_objects', array( $action2, 'action' ) );

		new WP_Query( $query_args );

		$args1 = $action1->get_args();
		$args2 = $action2->get_args();
		$last  = end( $args2 );
		$this->assertSameSets( self::$post_ids, $last[0], 'wp_lazyload_post_meta() was not executed.' );
		$this->assertSameSets( array(), $args1, 'update_meta_cache() was executed.' );
		$num_queries = get_num_queries();
		get_post_meta( self::$post_ids[0], 'foo', true );
		$this->assertSame( $num_queries + 1, get_num_queries(), 'wp_lazyload_post_meta() was not executed.' );
		$args1 = $action1->get_args();
		$last  = end( $args1 );
		$this->assertSameSets( self::$post_ids, $last[1], 'update_meta_cache() was not executed.' );
	}

	/**
	 * Provides test data for lazy loading post metadata.
	 *
	 * @return array
	 */
	public function data_lazy_load_post_meta() {
		return array(
			'lazy load post meta'                       => array(
				array(
					'post_type'           => 'wptests_pt',
					'lazy_load_post_meta' => true,
				),
			),
			'lazy load post meta fields id'             => array(
				array(
					'post_type'           => 'wptests_pt',
					'fields'              => 'ids',
					'lazy_load_post_meta' => true,
				),
			),
			'lazy load post meta fields id=>parent'     => array(
				array(
					'post_type'           => 'wptests_pt',
					'fields'              => 'id=>parent',
					'lazy_load_post_meta' => true,
				),
			),
			'lazy load post meta - update_post_meta_cache true' => array(
				array(
					'post_type'              => 'wptests_pt',
					'update_post_meta_cache' => true,
					'lazy_load_post_meta'    => true,
				),
			),
			'lazy load post meta - update_post_meta_cache false' => array(
				array(
					'post_type'              => 'wptests_pt',
					'update_post_meta_cache' => false,
					'lazy_load_post_meta'    => true,
				),
			),
			'lazy load post meta - cache_results false' => array(
				array(
					'post_type'           => 'wptests_pt',
					'cache_results'       => false,
					'lazy_load_post_meta' => true,
				),
			),
		);
	}
}
