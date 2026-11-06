<?php

/**
 * Tests for update_post_caches().
 *
 * @group post
 *
 * @covers ::update_post_caches
 */
class Tests_Post_UpdatePostCaches extends WP_UnitTestCase {

	/**
	 * IDs of the posts in the tests.
	 *
	 * @var int[]
	 */
	protected static $post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids = $factory->post->create_many( 2 );

		foreach ( self::$post_ids as $post_id ) {
			update_post_meta( $post_id, 'a_cache_test_field', 'a value' );
		}
	}

	/**
	 * Empties the caches that the function fills, and returns the posts as objects.
	 *
	 * @return WP_Post[] The posts, as they were before the caches were emptied.
	 */
	private function empty_the_caches() {
		$posts = array_map( 'get_post', self::$post_ids );

		foreach ( self::$post_ids as $post_id ) {
			wp_cache_delete( $post_id, 'posts' );
			wp_cache_delete( $post_id, 'post_meta' );
			wp_cache_delete( $post_id, 'category_relationships' );
		}

		return $posts;
	}

	/**
	 * Tests that nothing is done without posts.
	 *
	 * @ticket 66495
	 */
	public function test_should_do_nothing_without_posts() {
		$posts = array();

		$this->assertNull( update_post_caches( $posts ) );
	}

	/**
	 * Tests that the posts, their custom fields and their terms are cached by default.
	 *
	 * @ticket 66495
	 */
	public function test_should_cache_the_posts_their_custom_fields_and_their_terms() {
		$posts = $this->empty_the_caches();

		update_post_caches( $posts );

		foreach ( self::$post_ids as $post_id ) {
			$this->assertNotFalse( wp_cache_get( $post_id, 'posts' ), 'The post should be cached.' );
			$this->assertNotFalse( wp_cache_get( $post_id, 'post_meta' ), 'The custom fields should be cached.' );
			$this->assertNotFalse( wp_cache_get( $post_id, 'category_relationships' ), 'The categories should be cached.' );
		}
	}

	/**
	 * Tests that the cached custom fields have the values of the post.
	 *
	 * @ticket 66495
	 */
	public function test_should_cache_the_values_of_the_custom_fields() {
		$posts = $this->empty_the_caches();

		update_post_caches( $posts );

		$meta = wp_cache_get( self::$post_ids[0], 'post_meta' );

		$this->assertSame( array( 'a value' ), $meta['a_cache_test_field'] );
	}

	/**
	 * Tests that the terms are not cached when asked not to.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_cache_the_terms_when_asked_not_to() {
		$posts = $this->empty_the_caches();

		update_post_caches( $posts, 'post', false, true );

		foreach ( self::$post_ids as $post_id ) {
			$this->assertNotFalse( wp_cache_get( $post_id, 'post_meta' ) );
			$this->assertFalse( wp_cache_get( $post_id, 'category_relationships' ) );
		}
	}

	/**
	 * Tests that the custom fields are not cached when asked not to.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_cache_the_custom_fields_when_asked_not_to() {
		$posts = $this->empty_the_caches();

		update_post_caches( $posts, 'post', true, false );

		foreach ( self::$post_ids as $post_id ) {
			$this->assertNotFalse( wp_cache_get( $post_id, 'category_relationships' ) );
			$this->assertFalse( wp_cache_get( $post_id, 'post_meta' ) );
		}
	}

	/**
	 * Tests that the post is cached when neither the terms nor the custom fields are.
	 *
	 * @ticket 66495
	 */
	public function test_should_always_cache_the_posts() {
		$posts = $this->empty_the_caches();

		update_post_caches( $posts, 'post', false, false );

		foreach ( self::$post_ids as $post_id ) {
			$this->assertNotFalse( wp_cache_get( $post_id, 'posts' ) );
			$this->assertFalse( wp_cache_get( $post_id, 'post_meta' ) );
			$this->assertFalse( wp_cache_get( $post_id, 'category_relationships' ) );
		}
	}

	/**
	 * Registers a post type with a taxonomy of its own, and creates a post of that type with a term.
	 *
	 * @return int ID of the post.
	 */
	private function create_post_with_another_post_type_and_taxonomy() {
		register_post_type( 'foo-cache-type', array( 'public' => true ) );
		register_taxonomy( 'foo-cache-tax', 'foo-cache-type' );

		$post_id = self::factory()->post->create( array( 'post_type' => 'foo-cache-type' ) );
		$term    = self::factory()->term->create_and_get( array( 'taxonomy' => 'foo-cache-tax' ) );
		wp_set_object_terms( $post_id, array( $term->term_id ), 'foo-cache-tax' );

		wp_cache_delete( $post_id, 'posts' );
		wp_cache_delete( $post_id, 'foo-cache-tax_relationships' );
		wp_cache_delete( $post_id, 'category_relationships' );

		return $post_id;
	}

	public function tear_down() {
		unregister_taxonomy( 'foo-cache-tax' );
		unregister_post_type( 'foo-cache-type' );

		parent::tear_down();
	}

	/**
	 * Tests which terms are cached for each post type argument, for posts of two post types with different taxonomies.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_post_types
	 *
	 * @param string|string[]|false $post_type          Post type argument.
	 * @param bool                  $caches_categories  Whether the categories of the post are cached.
	 * @param bool                  $caches_other_terms Whether the terms of the other post type are cached.
	 */
	public function test_should_cache_the_terms_of_the_post_types_that_apply( $post_type, $caches_categories, $caches_other_terms ) {
		$other_post_id = $this->create_post_with_another_post_type_and_taxonomy();
		$posts         = $this->empty_the_caches();
		$posts[]       = get_post( $other_post_id );

		update_post_caches( $posts, $post_type );

		$categories = wp_cache_get( self::$post_ids[0], 'category_relationships' );
		$other      = wp_cache_get( $other_post_id, 'foo-cache-tax_relationships' );

		if ( $caches_categories ) {
			$this->assertNotFalse( $categories, 'The categories of the post should be cached.' );
		} else {
			$this->assertFalse( $categories, 'The categories of the post should not be cached.' );
		}

		if ( $caches_other_terms ) {
			$this->assertNotFalse( $other, 'The terms of the other post type should be cached.' );
			$this->assertCount( 1, $other );
		} else {
			$this->assertFalse( $other, 'The terms of the other post type should not be cached.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * The post types of the posts are `post` and `foo-cache-type`.
	 *
	 * @return array[]
	 */
	public static function data_post_types() {
		return array(
			'any'                 => array( 'any', true, true ),
			'empty'               => array( '', true, true ),
			'false'               => array( false, true, true ),
			'list of both'        => array( array( 'post', 'foo-cache-type' ), true, true ),
			'post only'           => array( 'post', true, false ),
			'other type only'     => array( 'foo-cache-type', false, true ),
			'list of other type'  => array( array( 'foo-cache-type' ), false, true ),
			'a type without them' => array( 'page', false, false ),
		);
	}
}
