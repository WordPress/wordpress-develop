<?php

/**
 * @group link
 *
 * @covers ::the_feed_link
 */
class Tests_Link_TheFeedLink extends WP_UnitTestCase {

	/**
	 * Tests the_feed_link() with pretty permalink structure and default feed.
	 *
	 * @ticket 65819
	 */
	public function test_the_feed_link_pretty_permastruct_default_feed() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$expected_url = home_url( '/feed/' );
		$expected     = '<a href="' . esc_url( $expected_url ) . '">Entries Feed</a>';

		ob_start();
		the_feed_link( 'Entries Feed' );
		$output = ob_get_clean();

		$this->assertSame( $expected, $output );
	}

	/**
	 * Tests the_feed_link() with custom feed type.
	 *
	 * @ticket 65819
	 */
	public function test_the_feed_link_custom_feed_type() {
		$this->set_permalink_structure( '' );

		$expected_url = home_url( '?feed=atom' );
		$expected     = '<a href="' . esc_url( $expected_url ) . '">Atom Feed</a>';

		ob_start();
		the_feed_link( 'Atom Feed', 'atom' );
		$output = ob_get_clean();

		$this->assertSame( $expected, $output );
	}

	/**
	 * Tests the 'the_feed_link' filter hook.
	 *
	 * @ticket 65819
	 */
	public function test_the_feed_link_filter() {
		$this->set_permalink_structure( '' );

		add_filter(
			'the_feed_link',
			static function ( $link, $feed ) {
				return '<div class="feed-wrapper" data-feed="' . esc_attr( $feed ) . '">' . $link . '</div>';
			},
			10,
			2
		);

		ob_start();
		the_feed_link( 'Custom Filtered Feed', 'atom' );
		$output = ob_get_clean();

		$this->assertStringStartsWith( '<div class="feed-wrapper" data-feed="atom"><a', $output );
		$this->assertStringEndsWith( 'Custom Filtered Feed</a></div>', $output );
	}
}
