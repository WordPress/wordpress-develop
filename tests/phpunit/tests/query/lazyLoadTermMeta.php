<?php

/**
 * @group query
 * @group taxonomy
 * @group meta
 */
class Test_Lazy_Load_Term_Meta extends WP_UnitTestCase {
	/**
	 * @var array
	 */
	protected static $post_ids = array();
	/**
	 * @var array
	 */
	protected static $term_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$post_type      = 'post';
		self::$post_ids = $factory->post->create_many(
			3,
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		);
		$taxonomies     = get_object_taxonomies( $post_type, 'object' );
		foreach ( self::$post_ids  as $post_id ) {
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! $taxonomy->_builtin ) {
					continue;
				}
				$terms          = $factory->term->create_many( 3, array( 'taxonomy' => $taxonomy->name ) );
				self::$term_ids = array_merge( self::$term_ids, $terms );
				foreach ( $terms as $term ) {
					add_term_meta( $term, wp_rand(), 'test' );
				}
				wp_set_object_terms( $post_id, $terms, $taxonomy->name );
			}
		}
	}

	/**
	 * @ticket 57150
	 * @covers ::wp_queue_posts_for_term_meta_lazyload
	 */
	public function test_wp_queue_posts_for_term_meta_lazyload() {
		$filter = new MockAction();
		add_filter( 'update_term_metadata_cache', array( $filter, 'filter' ), 10, 2 );
		new WP_Query(
			array(
				'post__in'            => self::$post_ids,
				'lazy_load_term_meta' => true,
			)
		);

		get_term_meta( end( self::$term_ids ) );

		$args     = $filter->get_args();
		$first    = reset( $args );
		$term_ids = end( $first );
		$this->assertSameSets( $term_ids, self::$term_ids );
	}

	/**
	 * @ticket 57150
	 * @covers ::wp_queue_posts_for_term_meta_lazyload
	 */
	public function test_wp_queue_posts_for_term_meta_lazyload_update_post_term_cache() {
		$filter = new MockAction();
		add_filter( 'update_term_metadata_cache', array( $filter, 'filter' ), 10, 2 );
		new WP_Query(
			array(
				'post__in'               => self::$post_ids,
				'lazy_load_term_meta'    => true,
				'update_post_term_cache' => false,
			)
		);

		get_term_meta( end( self::$term_ids ) );

		$args     = $filter->get_args();
		$first    = reset( $args );
		$term_ids = end( $first );
		$this->assertSameSets( $term_ids, self::$term_ids );
	}

	/**
	 * @ticket 57150
	 * @covers ::wp_queue_posts_for_term_meta_lazyload
	 */
	public function test_wp_queue_posts_for_term_meta_lazyload_false() {
		$filter = new MockAction();
		add_filter( 'update_term_metadata_cache', array( $filter, 'filter' ), 10, 2 );
		new WP_Query(
			array(
				'post__in'            => self::$post_ids,
				'lazy_load_term_meta' => false,
			)
		);

		$term_id = end( self::$term_ids );
		get_term_meta( $term_id );

		$args     = $filter->get_args();
		$first    = reset( $args );
		$term_ids = end( $first );
		$this->assertSameSets( $term_ids, array( $term_id ) );
	}


	/**
	 * @ticket 57901
	 *
	 * @covers ::wp_queue_posts_for_term_meta_lazyload
	 */
	public function test_wp_queue_posts_for_term_meta_lazyload_insert_term() {
		$filter = new MockAction();
		add_filter( 'update_term_metadata_cache', array( $filter, 'filter' ), 10, 2 );

		register_taxonomy( 'wptests_tax', 'post' );

		$t1      = wp_insert_term( 'Foo', 'wptests_tax' );
		$term_id = $t1['term_id'];

		new WP_Query(
			array(
				'post__in'            => self::$post_ids,
				'lazy_load_term_meta' => true,
			)
		);

		get_term_meta( $term_id );

		$args     = $filter->get_args();
		$first    = reset( $args );
		$term_ids = end( $first );
		$this->assertContains( $term_id, $term_ids );
	}

	/**
	 * @ticket 57150
	 * @covers ::wp_queue_posts_for_term_meta_lazyload
	 */
	public function test_wp_queue_posts_for_term_meta_lazyload_delete_term() {
		$filter = new MockAction();
		add_filter( 'update_term_metadata_cache', array( $filter, 'filter' ), 10, 2 );

		$remove_term_id = end( self::$term_ids );
		$term           = get_term( $remove_term_id );
		wp_delete_term( $remove_term_id, $term->taxonomy );

		new WP_Query(
			array(
				'post__in'            => self::$post_ids,
				'lazy_load_term_meta' => true,
			)
		);

		$term_id = end( self::$term_ids );
		get_term_meta( $term_id );

		$args     = $filter->get_args();
		$first    = reset( $args );
		$term_ids = end( $first );
		$this->assertContains( $remove_term_id, $term_ids );
	}
}
