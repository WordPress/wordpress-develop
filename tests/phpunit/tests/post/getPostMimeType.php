<?php
/**
 * Tests for get_post_mime_type().
 *
 * @group post
 *
 * @covers ::get_post_mime_type
 */
class Tests_Post_GetPostMimeType extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_false_for_nonexistent_post() {
		$this->assertFalse( get_post_mime_type( PHP_INT_MAX ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_stored_mime_type_for_attachment() {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		$this->assertSame( 'image/jpeg', get_post_mime_type( $attachment_id ) );
	}
}
