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
	 * Tests that the terms are cached for the post types of the posts when the post type is `any`, an empty value, or a list.
	 *
	 * @ticket 66495
	 *
	 * @dataProvider data_post_types
	 *
	 * @param string|string[] $post_type Post type argument.
	 */
	public function test_should_cache_the_terms_for_any_post_type_argument( $post_type ) {
		$posts = $this->empty_the_caches();

		update_post_caches( $posts, $post_type );

		$this->assertNotFalse( wp_cache_get( self::$post_ids[0], 'category_relationships' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_post_types() {
		return array(
			'post'  => array( 'post' ),
			'any'   => array( 'any' ),
			'empty' => array( '' ),
			'false' => array( false ),
			'list'  => array( array( 'post', 'page' ) ),
		);
	}

	/**
	 * Tests that the terms of the post type that is given are the ones that are cached.
	 *
	 * @ticket 66495
	 */
	public function test_should_cache_the_terms_of_the_post_type_that_is_given() {
		$posts = $this->empty_the_caches();

		// The categories are not taxonomies of pages, so they are not cached for this post type.
		update_post_caches( $posts, 'page' );

		$this->assertFalse( wp_cache_get( self::$post_ids[0], 'category_relationships' ) );
	}
}
