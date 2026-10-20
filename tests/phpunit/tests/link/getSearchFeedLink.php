<?php

/**
 * Tests for the `get_search_feed_link()` and `get_search_comments_feed_link()` functions.
 *
 * @group link
 * @group feed
 */
class Tests_Link_GetSearchFeedLink extends WP_UnitTestCase {

	/**
	 * Tests that get_search_feed_link() returns the expected URL.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_feed_link
	 *
	 * @dataProvider data_get_search_feed_link
	 *
	 * @param string $structure Permalink structure.
	 * @param string $query     Search query.
	 * @param string $feed      Feed type passed to the function.
	 * @param string $expected  Expected URL relative to home_url().
	 */
	public function test_get_search_feed_link( $structure, $query, $feed, $expected ) {
		$this->set_permalink_structure( $structure );

		$this->assertSame( home_url( $expected ), get_search_feed_link( $query, $feed ) );
	}

	/**
	 * Data provider for test_get_search_feed_link().
	 *
	 * @return array[]
	 */
	public static function data_get_search_feed_link() {
		return array(
			'plain permalinks, default feed'  => array( '', 'pizza', '', '?s=pizza&feed=rss2' ),
			'plain permalinks, atom feed'     => array( '', 'pizza', 'atom', '?s=pizza&feed=atom' ),
			'plain permalinks, spaces'        => array( '', 'hello world', '', '?s=hello+world&feed=rss2' ),
			'pretty permalinks, default feed' => array( '/%postname%/', 'pizza', '', '/search/pizza/feed/rss2/' ),
			'pretty permalinks, atom feed'    => array( '/%postname%/', 'pizza', 'atom', '/search/pizza/feed/atom/' ),
			'pretty permalinks, spaces'       => array( '/%postname%/', 'hello world', '', '/search/hello+world/feed/rss2/' ),
		);
	}

	/**
	 * Tests that get_search_comments_feed_link() returns the expected URL.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_comments_feed_link
	 *
	 * @dataProvider data_get_search_comments_feed_link
	 *
	 * @param string $structure Permalink structure.
	 * @param string $feed      Feed type passed to the function.
	 * @param string $expected  Expected URL relative to home_url().
	 */
	public function test_get_search_comments_feed_link( $structure, $feed, $expected ) {
		$this->set_permalink_structure( $structure );

		$this->assertSame( home_url( $expected ), get_search_comments_feed_link( 'pizza', $feed ) );
	}

	/**
	 * Data provider for test_get_search_comments_feed_link().
	 *
	 * @return array[]
	 */
	public static function data_get_search_comments_feed_link() {
		return array(
			'plain permalinks, default feed'  => array( '', '', '?s=pizza&feed=comments-rss2' ),
			'plain permalinks, atom feed'     => array( '', 'atom', '?s=pizza&feed=comments-atom' ),
			'pretty permalinks, default feed' => array( '/%postname%/', '', '/search/pizza/feed/rss2/?withcomments=1' ),
			'pretty permalinks, atom feed'    => array( '/%postname%/', 'atom', '/search/pizza/feed/atom/?withcomments=1' ),
		);
	}

	/**
	 * Tests that both functions use the feed type from the default_feed filter when none is passed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_feed_link
	 * @covers ::get_search_comments_feed_link
	 */
	public function test_search_feed_links_use_default_feed_filter() {
		$this->set_permalink_structure( '/%postname%/' );

		add_filter(
			'default_feed',
			static function () {
				return 'atom';
			}
		);

		$this->assertSame( home_url( '/search/pizza/feed/atom/' ), get_search_feed_link( 'pizza' ), 'The posts feed should use the filtered default feed.' );
		$this->assertSame( home_url( '/search/pizza/feed/atom/?withcomments=1' ), get_search_comments_feed_link( 'pizza' ), 'The comments feed should use the filtered default feed.' );
	}

	/**
	 * Tests that get_search_feed_link() passes the expected arguments to the search_feed_link filter
	 * and returns the filtered value.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_feed_link
	 */
	public function test_get_search_feed_link_applies_search_feed_link_filter() {
		$this->set_permalink_structure( '' );

		$filter = new MockAction();
		add_filter( 'search_feed_link', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'search_feed_link',
			static function () {
				return 'https://example.org/filtered-search-feed/';
			},
			20
		);

		$this->assertSame( 'https://example.org/filtered-search-feed/', get_search_feed_link( 'pizza', 'atom' ), 'The filtered link should be returned.' );
		$this->assertSame(
			array( array( home_url( '?s=pizza&feed=atom' ), 'atom', 'posts' ) ),
			$filter->get_args(),
			'The filter should receive the link, the feed type and the posts search type.'
		);
	}

	/**
	 * Tests that get_search_comments_feed_link() runs the search_feed_link filter for the posts feed
	 * it builds on, then for the comments feed, and returns the filtered value.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_search_comments_feed_link
	 */
	public function test_get_search_comments_feed_link_applies_search_feed_link_filter() {
		$this->set_permalink_structure( '/%postname%/' );

		$filter = new MockAction();
		add_filter( 'search_feed_link', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'search_feed_link',
			static function ( $link, $feed, $type ) {
				return 'comments' === $type ? 'https://example.org/filtered-comments-feed/' : $link;
			},
			20,
			3
		);

		$this->assertSame( 'https://example.org/filtered-comments-feed/', get_search_comments_feed_link( 'pizza' ), 'The filtered comments feed link should be returned.' );
		$this->assertSame(
			array(
				array( home_url( '/search/pizza/feed/rss2/' ), 'rss2', 'posts' ),
				array( home_url( '/search/pizza/feed/rss2/?withcomments=1' ), 'rss2', 'comments' ),
			),
			$filter->get_args(),
			'The filter should run for the posts feed first, then for the comments feed.'
		);
	}
}
