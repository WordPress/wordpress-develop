<?php

/**
 * Tests for delete_post_thumbnail().
 *
 * @group post
 *
 * @covers ::delete_post_thumbnail
 */
class Tests_Post_DeletePostThumbnail extends WP_UnitTestCase {

	/**
	 * Tests that the thumbnail is removed from a post.
	 *
	 * @ticket 66495
	 */
	public function test_should_remove_the_thumbnail_of_a_post() {
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		set_post_thumbnail( $post_id, $attachment_id );

		$this->assertSame( $attachment_id, get_post_thumbnail_id( $post_id ), 'The post should have a thumbnail.' );

		$this->assertTrue( delete_post_thumbnail( $post_id ) );
		$this->assertFalse( has_post_thumbnail( $post_id ) );
		$this->assertSame( array(), get_post_meta( $post_id, '_thumbnail_id' ) );
	}

	/**
	 * Tests that the attachment is not deleted with the thumbnail.
	 *
	 * @ticket 66495
	 */
	public function test_should_not_delete_the_attachment() {
		$post_id       = self::factory()->post->create();
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		set_post_thumbnail( $post_id, $attachment_id );

		delete_post_thumbnail( $post_id );

		$this->assertInstanceOf( WP_Post::class, get_post( $attachment_id ) );
	}

	/**
	 * Tests that false is returned for a post without a thumbnail.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_false_for_a_post_without_a_thumbnail() {
		$post_id = self::factory()->post->create();

		$this->assertFalse( delete_post_thumbnail( $post_id ) );
	}

	/**
	 * Tests that false is returned for a post that does not exist.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_false_for_a_post_that_does_not_exist() {
		$this->assertFalse( delete_post_thumbnail( 987654321 ) );
	}

	/**
	 * Tests that a post object can be passed.
	 *
	 * @ticket 66495
	 */
	public function test_should_accept_a_post_object() {
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, '_thumbnail_id', 123 );

		$this->assertTrue( delete_post_thumbnail( get_post( $post_id ) ) );
		$this->assertSame( array(), get_post_meta( $post_id, '_thumbnail_id' ) );
	}

	/**
	 * Tests that only the thumbnail is removed from the custom fields of the post.
	 *
	 * @ticket 66495
	 */
	public function test_should_keep_the_other_custom_fields() {
		$post_id = self::factory()->post->create();
		add_post_meta( $post_id, '_thumbnail_id', 123 );
		add_post_meta( $post_id, 'another_field', 'kept' );

		delete_post_thumbnail( $post_id );

		$this->assertSame( 'kept', get_post_meta( $post_id, 'another_field', true ) );
	}
}
