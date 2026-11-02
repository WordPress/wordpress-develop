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
	public function test_filter_receives_core_media_groups() {
		$filter = new MockAction();
		add_filter( 'post_mime_types', array( $filter, 'filter' ) );

		$types = get_post_mime_types();

		$this->assertArrayHasKey( 'image', $types );
		$this->assertArrayHasKey( 'audio', $types );
		$this->assertArrayHasKey( 'video', $types );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertArrayHasKey( 'image', $filter->get_args()[0][0] );
	}
}
