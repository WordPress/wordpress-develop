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

		$permalink = get_permalink( $post_id );
		$filtered  = null;

		add_filter(
			'the_permalink_rss',
			static function ( $url ) use ( &$filtered ) {
				$filtered = $url;

				// Include a raw ampersand so missing esc_url() would fail the assertion below.
				return 'https://example.org/?a=1&b=2';
			}
		);

		ob_start();
		the_permalink_rss();
		$output = ob_get_clean();

		$this->assertSame( $permalink, $filtered );
		// Literal escaped form — do not derive this via esc_url().
		$this->assertSame( 'https://example.org/?a=1&#038;b=2', $output );
	}
}
