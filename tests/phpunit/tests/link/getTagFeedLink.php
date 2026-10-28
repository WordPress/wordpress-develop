<?php

/**
 * Tests for the `get_tag_feed_link()` function.
 *
 * @group link
 * @group feed
 *
 * @covers ::get_tag_feed_link
 * @covers ::get_term_feed_link
 */
class Tests_Link_GetTagFeedLink extends WP_UnitTestCase {

	/**
	 * Tag ID for tests.
	 *
	 * @var int
	 */
	private static $tag_id;

	/**
	 * Set up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$tag_id = $factory->tag->create(
			array(
				'name' => 'Testing',
				'slug' => 'testing',
			)
		);
	}

	/**
	 * Tests that false is returned for a non-existent tag ID.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_existent_tag() {
		$this->assertFalse( get_tag_feed_link( 999999 ) );
	}

	/**
	 * Tests plain permalinks for tag feed link with default and specific feeds.
	 *
	 * @ticket 65819
	 */
	public function test_plain_permalinks() {
		$this->assertSame(
			home_url( '?feed=rss2&amp;tag=testing' ),
			get_tag_feed_link( self::$tag_id )
		);
		$this->assertSame(
			home_url( '?feed=atom&amp;tag=testing' ),
			get_tag_feed_link( self::$tag_id, 'atom' )
		);
	}

	/**
	 * Tests pretty permalinks with default and explicit feeds.
	 *
	 * @ticket 65819
	 */
	public function test_pretty_permalinks() {
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();

		$this->assertSame(
			home_url( '/tag/testing/feed/' ),
			get_tag_feed_link( self::$tag_id )
		);
		$this->assertSame(
			home_url( '/tag/testing/feed/atom/' ),
			get_tag_feed_link( self::$tag_id, 'atom' )
		);
	}

	/**
	 * Tests that passing a WP_Term object works the same as an ID.
	 *
	 * @ticket 65819
	 */
	public function test_accepts_term_object() {
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();

		$tag = get_term( self::$tag_id, 'post_tag' );
		$this->assertSame(
			home_url( '/tag/testing/feed/' ),
			get_tag_feed_link( $tag )
		);
	}

	/**
	 * Tests that the default_feed filter changes the default feed type.
	 *
	 * @ticket 65819
	 */
	public function test_respects_default_feed_filter() {
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();

		add_filter(
			'default_feed',
			static function () {
				return 'atom';
			}
		);

		$this->assertSame(
			home_url( '/tag/testing/feed/' ),
			get_tag_feed_link( self::$tag_id ),
			'An empty feed type should use the filtered default feed.'
		);
		$this->assertSame(
			home_url( '/tag/testing/feed/' ),
			get_tag_feed_link( self::$tag_id, 'atom' ),
			'The default feed should not add a feed segment.'
		);
		$this->assertSame(
			home_url( '/tag/testing/feed/rss2/' ),
			get_tag_feed_link( self::$tag_id, 'rss2' ),
			'A non-default feed should add a feed segment.'
		);
	}

	/**
	 * Tests the tag_feed_link filter.
	 *
	 * @ticket 65819
	 */
	public function test_tag_feed_link_filter() {
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();

		$filter = new MockAction();
		add_filter( 'tag_feed_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'tag_feed_link',
			static function ( $link, $feed ) {
				return $link . '?custom_tag_feed=' . $feed;
			},
			20,
			2
		);

		$link = get_tag_feed_link( self::$tag_id, 'rss2' );

		$this->assertSame(
			array( home_url( '/tag/testing/feed/' ), 'rss2' ),
			$filter->get_args()[0],
			'The tag_feed_link filter should receive the link and resolved feed.'
		);
		$this->assertSame(
			home_url( '/tag/testing/feed/?custom_tag_feed=rss2' ),
			$link,
			'The filtered link should be returned.'
		);
	}
}
