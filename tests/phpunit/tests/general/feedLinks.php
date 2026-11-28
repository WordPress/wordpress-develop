<?php
/**
 * Tests for feed_links().
 *
 * @group general
 * @group template
 *
 * @covers ::feed_links
 */
class Tests_General_FeedLinks extends WP_UnitTestCase {

	public function tear_down() {
		remove_theme_support( 'automatic-feed-links' );
		parent::tear_down();
	}

	/**
	 * Tests that nothing is printed without automatic-feed-links support.
	 *
	 * @ticket 66821
	 */
	public function test_feed_links_does_nothing_without_theme_support() {
		remove_theme_support( 'automatic-feed-links' );

		$this->assertSame( '', get_echo( 'feed_links' ) );
	}

	/**
	 * Tests that posts and comments feed links are printed when supported.
	 *
	 * @ticket 66821
	 */
	public function test_feed_links_prints_posts_and_comments_feeds() {
		add_theme_support( 'automatic-feed-links' );

		$output = get_echo( 'feed_links' );

		$separator = _x( '&raquo;', 'feed link' );
		$name      = get_bloginfo( 'name' );

		$posts_link    = sprintf(
			'<link rel="alternate" type="%s" title="%s" href="%s" />',
			feed_content_type(),
			esc_attr( sprintf( '%1$s %2$s Feed', $name, $separator ) ),
			esc_url( get_feed_link() )
		);
		$comments_link = sprintf(
			'<link rel="alternate" type="%s" title="%s" href="%s" />',
			feed_content_type(),
			esc_attr( sprintf( '%1$s %2$s Comments Feed', $name, $separator ) ),
			esc_url( get_feed_link( 'comments_' . get_default_feed() ) )
		);

		$this->assertStringContainsString( $posts_link, $output );
		$this->assertStringContainsString( $comments_link, $output );
	}

	/**
	 * Tests that the posts feed link can be suppressed by filter.
	 *
	 * @ticket 66821
	 */
	public function test_feed_links_can_hide_posts_feed() {
		add_theme_support( 'automatic-feed-links' );
		add_filter( 'feed_links_show_posts_feed', '__return_false' );

		$output = get_echo( 'feed_links' );

		$this->assertStringNotContainsString( esc_url( get_feed_link() ), $output );
		$this->assertStringContainsString(
			esc_url( get_feed_link( 'comments_' . get_default_feed() ) ),
			$output
		);
	}

	/**
	 * Tests that the comments feed link can be suppressed by filter.
	 *
	 * @ticket 66821
	 */
	public function test_feed_links_can_hide_comments_feed() {
		add_theme_support( 'automatic-feed-links' );
		add_filter( 'feed_links_show_comments_feed', '__return_false' );

		$output = get_echo( 'feed_links' );

		$this->assertStringContainsString( esc_url( get_feed_link() ), $output );
		$this->assertStringNotContainsString(
			esc_url( get_feed_link( 'comments_' . get_default_feed() ) ),
			$output
		);
	}
}
