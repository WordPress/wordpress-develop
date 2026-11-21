<?php
/**
 * Tests for get_post_mime_types().
 *
 * @group post
 * @group media
 *
 * @covers ::get_post_mime_types
 */
class Tests_Post_GetPostMimeTypes extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_customize_media_groups() {
		add_filter(
			'post_mime_types',
			static function ( $post_mime_types ) {
				$post_mime_types['image'][0] = 'Custom Images';
				return $post_mime_types;
			}
		);

		$types = get_post_mime_types();

		$this->assertSame( 'Custom Images', $types['image'][0] );
	}
}
