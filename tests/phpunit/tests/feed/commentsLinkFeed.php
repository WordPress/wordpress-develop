<?php
/**
 * Tests for comments_link_feed().
 *
 * @group feed
 *
 * @covers ::comments_link_feed
 */
class Tests_Feed_CommentsLinkFeed extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_comments_link_and_output_is_escaped() {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$filter = new MockAction();
		add_filter( 'comments_link_feed', array( $filter, 'filter_append' ) );

		ob_start();
		comments_link_feed();
		$output = ob_get_clean();

		$comments_link = get_comments_link( $post_id );

		$this->assertSame( esc_url( $comments_link . '_append' ), $output );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( $comments_link ), $filter->get_args()[0] );
	}
}
