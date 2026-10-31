<?php

/**
 * Tests for the `get_author_feed_link()` function.
 *
 * @group link
 * @group feed
 * @group author
 *
 * @covers ::get_author_feed_link
 */
class Tests_Link_GetAuthorFeedLink extends WP_UnitTestCase {

	/**
	 * Author ID for tests.
	 *
	 * @var int
	 */
	private static $author_id;

	/**
	 * Set up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'user_login'    => 'johndoe',
				'user_nicename' => 'johndoe',
			)
		);
	}

	/**
	 * Tests plain permalinks for author feed link with default and specific feeds.
	 *
	 * @ticket 65819
	 */
	public function test_plain_permalinks() {
		$this->assertSame(
			home_url( '?feed=rss2&amp;author=' . self::$author_id ),
			get_author_feed_link( self::$author_id )
		);
		$this->assertSame(
			home_url( '?feed=atom&amp;author=' . self::$author_id ),
			get_author_feed_link( self::$author_id, 'atom' )
		);
	}

	/**
	 * Tests pretty permalinks with default and explicit feeds.
	 *
	 * @ticket 65819
	 */
	public function test_pretty_permalinks() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame(
			home_url( '/author/johndoe/feed/' ),
			get_author_feed_link( self::$author_id )
		);
		$this->assertSame(
			home_url( '/author/johndoe/feed/atom/' ),
			get_author_feed_link( self::$author_id, 'atom' )
		);
	}

	/**
	 * Tests that the default_feed filter changes the default feed type.
	 *
	 * @ticket 65819
	 */
	public function test_respects_default_feed_filter() {
		$this->set_permalink_structure( '/%postname%/' );

		add_filter(
			'default_feed',
			static function () {
				return 'atom';
			}
		);

		$this->assertSame(
			home_url( '/author/johndoe/feed/' ),
			get_author_feed_link( self::$author_id ),
			'An empty feed type should use the filtered default feed.'
		);
		$this->assertSame(
			home_url( '/author/johndoe/feed/' ),
			get_author_feed_link( self::$author_id, 'atom' ),
			'The default feed should not add a feed segment.'
		);
		$this->assertSame(
			home_url( '/author/johndoe/feed/rss2/' ),
			get_author_feed_link( self::$author_id, 'rss2' ),
			'A non-default feed should add a feed segment.'
		);
	}

	/**
	 * Tests the author_feed_link filter.
	 *
	 * @ticket 65819
	 */
	public function test_author_feed_link_filter() {
		$this->set_permalink_structure( '/%postname%/' );

		$filter = new MockAction();
		add_filter( 'author_feed_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'author_feed_link',
			static function ( $link, $feed ) {
				return $link . '?custom_feed=' . $feed;
			},
			20,
			2
		);

		$link = get_author_feed_link( self::$author_id, 'atom' );

		$this->assertSame(
			array( home_url( '/author/johndoe/feed/atom/' ), 'atom' ),
			$filter->get_args()[0],
			'The author_feed_link filter should receive the link and resolved feed.'
		);
		$this->assertSame(
			home_url( '/author/johndoe/feed/atom/?custom_feed=atom' ),
			$link,
			'The filtered link should be returned.'
		);
	}
}
