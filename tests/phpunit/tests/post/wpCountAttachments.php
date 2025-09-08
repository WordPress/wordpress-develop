<?php

/**
 * @group post
 * @group media
 * @group upload
 *
 * @covers ::wp_count_attachments
 */
class Tests_Post_wpCountAttachments extends WP_UnitTestCase {

	/**
	 * Tests that the result is cached.
	 *
	 * @ticket 55227
	 */
	public function test_wp_count_attachments_should_cache_the_result() {
		$mime_type = 'image/jpeg';
		$cache_key = 'attachments:image_jpeg';

		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => $mime_type,
			)
		);
		$expected     = wp_count_attachments( $mime_type );
		$last_changed = wp_cache_get_last_changed( 'attachment_count' );

		$actual = wp_cache_get_salted( $cache_key, 'post-queries', $last_changed );

		$this->assertEquals( $expected, $actual );
	}
}
