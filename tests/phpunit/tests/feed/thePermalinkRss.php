<?php
/**
 * Tests for the_permalink_rss().
 *
 * @group feed
 *
 * @covers ::the_permalink_rss
 */
class Tests_Feed_ThePermalinkRss extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_permalink_and_output_is_escaped() {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$filter = new MockAction();
		add_filter( 'the_permalink_rss', array( $filter, 'filter_append' ) );

		ob_start();
		the_permalink_rss();
		$output = ob_get_clean();

		$permalink = get_permalink( $post_id );

		$this->assertSame( esc_url( $permalink . '_append' ), $output );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( $permalink ), $filter->get_args()[0] );
	}
}
