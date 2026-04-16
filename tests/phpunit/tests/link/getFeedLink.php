<?php

/**
 * @group link
 * @covers ::get_feed_link
 */
class Tests_Link_GetFeedLink extends WP_UnitTestCase {

	/**
	 * @ticket 51839
	 * @dataProvider data_plain_permastruct
	 *
	 * @param string $expected Expected suffix to home_url().
	 * @param string $type     Feed type to request.
	 */
	public function tests_plain_permastruct( $expected, $type ) {
		$this->set_permalink_structure( '' );

		$this->assertSame( home_url( $expected ), get_feed_link( $type ) );
	}

	public function data_plain_permastruct() {
		return array(
			array( '?feed=rss2', '' ),
			array( '?feed=atom', 'atom' ),
			array( '?feed=get-feed-link', 'get-feed-link' ),
			array( '?feed=comments-rss2', 'comments_rss2' ),
			array( '?feed=comments-atom', 'comments_atom' ),
		);
	}

	/**
	 * @ticket 51839
	 * @dataProvider data_pretty_permastruct
	 *
	 * @param string $expected Expected suffix to home_url().
	 * @param string $type     Feed type to request.
	 */
	public function tests_pretty_permastruct( $expected, $type ) {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$this->assertSame( home_url( $expected ), get_feed_link( $type ) );
	}

	/**
	 * @ticket 51839
	 * @dataProvider data_pretty_permastruct
	 *
	 * @param string $expected Expected suffix to home_url().
	 * @param string $type     Feed type to request.
	 */
	public function tests_pretty_permastruct_with_prefix( $expected, $type ) {
		$this->set_permalink_structure( '/archives/%post_id%/%postname%/' );

		$this->assertSame( home_url( $expected ), get_feed_link( $type ) );
	}

	public function data_pretty_permastruct() {
		return array(
			array( '/feed/', '' ),
			array( '/feed/atom/', 'atom' ),
			array( '/feed/get-feed-link/', 'get-feed-link' ),
			array( '/comments/feed/', 'comments_rss2' ),
			array( '/comments/feed/atom/', 'comments_atom' ),
		);
	}
}
