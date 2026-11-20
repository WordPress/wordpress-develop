<?php

/**
 * Tests for is_local_attachment().
 *
 * @group post
 *
 * @covers ::is_local_attachment
 */
class Tests_Post_IsLocalAttachment extends WP_UnitTestCase {

	/**
	 * Tests that a URL of another site is not a local attachment, even when it names the ID of an attachment of this site.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_false_for_a_url_of_another_site() {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		$this->assertFalse( is_local_attachment( 'https://another-site.example/?attachment_id=' . $attachment_id ) );
		$this->assertTrue( is_local_attachment( home_url( '/?attachment_id=' . $attachment_id ) ), 'The same ID on this site should be an attachment.' );
	}

	/**
	 * Tests that a URL of the form `?attachment_id=` of this site is an attachment, without looking for the attachment.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_true_for_a_url_with_an_attachment_id() {
		$this->assertTrue( is_local_attachment( home_url( '/?attachment_id=987654' ) ) );
	}

	/**
	 * Tests that the URL of a post of this site is not an attachment.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_false_for_the_url_of_a_post() {
		$this->set_permalink_structure( '/%postname%/' );

		$post_id = self::factory()->post->create( array( 'post_name' => 'a-local-post' ) );

		$this->assertFalse( is_local_attachment( get_permalink( $post_id ) ) );
	}

	/**
	 * Tests that a URL of this site that does not match anything is not an attachment.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_false_for_a_url_that_matches_nothing() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertFalse( is_local_attachment( home_url( '/nothing-is-here/' ) ) );
		$this->assertFalse( is_local_attachment( home_url( '/' ) ) );
	}

	/**
	 * Tests that the permalink of an attachment of this site is an attachment.
	 *
	 * @ticket 66495
	 */
	public function test_should_return_true_for_the_permalink_of_an_attachment() {
		$this->set_permalink_structure( '/%postname%/' );

		$post_id       = self::factory()->post->create( array( 'post_name' => 'a-post-with-an-attachment' ) );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $post_id,
				'post_name'      => 'an-attachment',
			)
		);

		$this->assertTrue( is_local_attachment( get_attachment_link( $attachment_id ) ) );
	}
}
