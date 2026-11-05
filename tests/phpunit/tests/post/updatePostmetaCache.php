<?php

/**
 * Tests for update_postmeta_cache().
 *
 * @group post
 *
 * @covers ::update_postmeta_cache
 */
class Tests_Post_UpdatePostmetaCache extends WP_UnitTestCase {

	/**
	 * Tests that the custom fields of the posts are cached.
	 *
	 * @ticket 66495
	 */
	public function test_should_cache_the_custom_fields_of_the_posts() {
		$post_ids = self::factory()->post->create_many( 2 );
		update_post_meta( $post_ids[0], 'a_cache_test_field', 'first' );
		update_post_meta( $post_ids[1], 'a_cache_test_field', 'second' );

		foreach ( $post_ids as $post_id ) {
			wp_cache_delete( $post_id, 'post_meta' );
		}

		update_postmeta_cache( $post_ids );

		$this->assertSame( array( 'first' ), wp_cache_get( $post_ids[0], 'post_meta' )['a_cache_test_field'] );
		$this->assertSame( array( 'second' ), wp_cache_get( $post_ids[1], 'post_meta' )['a_cache_test_field'] );
	}

	/**
	 * Tests that the cache of each post is returned, by the ID of the post.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_the_cache_of_each_post() {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'a_cache_test_field', 'a value' );
		wp_cache_delete( $post_id, 'post_meta' );

		$result = update_postmeta_cache( array( $post_id ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( $post_id, $result );
		$this->assertSame( array( 'a value' ), $result[ $post_id ]['a_cache_test_field'] );
	}

	/**
	 * Tests that an empty cache is stored for a post without custom fields.
	 *
	 * @ticket 66495
	 */
	public function test_should_cache_an_empty_list_for_a_post_without_custom_fields() {
		$post_id = self::factory()->post->create();
		wp_cache_delete( $post_id, 'post_meta' );

		// The post has no custom fields of its own that start with an underscore, so remove all of them.
		foreach ( array_keys( get_post_meta( $post_id ) ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
		wp_cache_delete( $post_id, 'post_meta' );

		update_postmeta_cache( array( $post_id ) );

		$this->assertSame( array(), wp_cache_get( $post_id, 'post_meta' ) );
	}

	/**
	 * Tests that false is returned without post IDs.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_false_without_post_ids() {
		$this->assertFalse( update_postmeta_cache( array() ) );
	}
}
