<?php

/**
 * Tests for the `get_category_feed_link()` function.
 *
 * @group link
 * @group feed
 *
 * @covers ::get_category_feed_link
 * @covers ::get_term_feed_link
 */
class Tests_Link_GetCategoryFeedLink extends WP_UnitTestCase {

	/**
	 * Category ID for tests.
	 *
	 * @var int
	 */
	private static $cat_id;

	/**
	 * Set up test fixtures before any tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$cat_id = $factory->category->create(
			array(
				'name' => 'News',
				'slug' => 'news',
			)
		);
	}

	/**
	 * Tests that false is returned for a non-existent category ID.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_non_existent_category() {
		$this->assertFalse( get_category_feed_link( 999999 ) );
	}

	/**
	 * Tests plain permalinks for category feed link with default and specific feeds.
	 *
	 * @ticket 65819
	 */
	public function test_plain_permalinks() {
		$this->assertSame(
			home_url( '?feed=rss2&amp;cat=' . self::$cat_id ),
			get_category_feed_link( self::$cat_id )
		);
		$this->assertSame(
			home_url( '?feed=atom&amp;cat=' . self::$cat_id ),
			get_category_feed_link( self::$cat_id, 'atom' )
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
			home_url( '/category/news/feed/' ),
			get_category_feed_link( self::$cat_id )
		);
		$this->assertSame(
			home_url( '/category/news/feed/atom/' ),
			get_category_feed_link( self::$cat_id, 'atom' )
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

		$cat = get_term( self::$cat_id, 'category' );
		$this->assertSame(
			home_url( '/category/news/feed/' ),
			get_category_feed_link( $cat )
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
			home_url( '/category/news/feed/' ),
			get_category_feed_link( self::$cat_id ),
			'An empty feed type should use the filtered default feed.'
		);
		$this->assertSame(
			home_url( '/category/news/feed/' ),
			get_category_feed_link( self::$cat_id, 'atom' ),
			'The default feed should not add a feed segment.'
		);
		$this->assertSame(
			home_url( '/category/news/feed/rss2/' ),
			get_category_feed_link( self::$cat_id, 'rss2' ),
			'A non-default feed should add a feed segment.'
		);
	}

	/**
	 * Tests the category_feed_link filter.
	 *
	 * @ticket 65819
	 */
	public function test_category_feed_link_filter() {
		$this->set_permalink_structure( '/%postname%/' );
		create_initial_taxonomies();

		$filter = new MockAction();
		add_filter( 'category_feed_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'category_feed_link',
			static function ( $link, $feed ) {
				return $link . '?custom_feed=' . $feed;
			},
			20,
			2
		);

		$link = get_category_feed_link( self::$cat_id, 'rss2' );

		$this->assertSame(
			array( home_url( '/category/news/feed/' ), 'rss2' ),
			$filter->get_args()[0],
			'The category_feed_link filter should receive the link and resolved feed.'
		);
		$this->assertSame(
			home_url( '/category/news/feed/?custom_feed=rss2' ),
			$link,
			'The filtered link should be returned.'
		);
	}
}
