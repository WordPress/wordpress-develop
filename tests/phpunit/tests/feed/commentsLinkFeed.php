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
	 * @ticket 66479
	 */
	public function test_filter_receives_comments_link_and_output_is_escaped() {
		$post_id         = self::factory()->post->create();
		$GLOBALS['post'] = get_post( $post_id );
		setup_postdata( $GLOBALS['post'] );

		$comments_link = get_comments_link( $post_id );
		$filtered      = null;

		add_filter(
			'comments_link_feed',
			static function ( $url ) use ( &$filtered ) {
				$filtered = $url;

				// Include a raw ampersand so missing esc_url() would fail the assertion below.
				return 'https://example.org/?a=1&b=2';
			}
		);

		ob_start();
		comments_link_feed();
		$output = ob_get_clean();

		$this->assertSame( $comments_link, $filtered );
		// Literal escaped form — do not derive this via esc_url().
		$this->assertSame( 'https://example.org/?a=1&#038;b=2', $output );
	}
}
