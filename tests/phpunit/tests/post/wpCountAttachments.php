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

		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => $mime_type,
			)
		);

		$expected  = wp_count_attachments( $mime_type );
		$cache_key = wp_cache_get_last_changed( 'posts' ) . ':attachments:image_jpeg';
		$actual    = wp_cache_get( $cache_key, 'persistent-counts' );

		$this->assertEquals( $expected, $actual );
	}
}
