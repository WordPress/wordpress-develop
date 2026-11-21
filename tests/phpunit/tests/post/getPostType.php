<?php
/**
 * Tests for get_post_type().
 *
 * @group post
 *
 * @covers ::get_post_type
 */
class Tests_Post_GetPostType extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_uses_global_post_when_no_argument() {
		$GLOBALS['post'] = get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) );

		$this->assertSame( 'page', get_post_type() );
	}
}
