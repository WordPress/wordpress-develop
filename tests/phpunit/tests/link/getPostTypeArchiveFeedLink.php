<?php

/**
 * Tests for the `get_post_type_archive_feed_link()` function.
 *
 * @group link
 * @group feed
 *
 * @covers ::get_post_type_archive_feed_link
 */
class Tests_Link_GetPostTypeArchiveFeedLink extends WP_UnitTestCase {

	public function tear_down() {
		unregister_post_type( 'wptests_books' );

		parent::tear_down();
	}

	/**
	 * Registers a post type with an archive.
	 *
	 * @param array $args Optional. Arguments to merge into the defaults.
	 */
	private function register_books_post_type( $args = array() ) {
		register_post_type(
			'wptests_books',
			array_merge(
				array(
					'public'      => true,
					'has_archive' => true,
					'rewrite'     => array( 'slug' => 'books' ),
				),
				$args
			)
		);
	}

	/**
	 * Tests that false is returned for a post type that does not exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_unregistered_post_type() {
		$this->assertFalse( get_post_type_archive_feed_link( 'wptests_missing' ) );
	}

	/**
	 * Tests that false is returned for a post type without an archive.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_for_post_type_without_archive() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->register_books_post_type( array( 'has_archive' => false ) );

		$this->assertFalse( get_post_type_archive_feed_link( 'wptests_books' ) );
	}

	/**
	 * Tests the feed link with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_plain_permalinks
	 *
	 * @param string $feed     Feed type passed to the function.
	 * @param string $expected Expected link relative to home_url().
	 */
	public function test_plain_permalinks( $feed, $expected ) {
		$this->set_permalink_structure( '' );
		$this->register_books_post_type();

		$this->assertSame( home_url( $expected ), get_post_type_archive_feed_link( 'wptests_books', $feed ) );
	}

	/**
	 * Data provider for test_plain_permalinks().
	 *
	 * @return array[]
	 */
	public static function data_plain_permalinks() {
		return array(
			'default feed' => array( '', '?post_type=wptests_books&feed=rss2' ),
			'atom feed'    => array( 'atom', '?post_type=wptests_books&feed=atom' ),
		);
	}

	/**
	 * Tests the feed link with pretty permalinks.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_pretty_permalinks
	 *
	 * @param string $structure Permalink structure.
	 * @param array  $args      Post type arguments.
	 * @param string $feed      Feed type passed to the function.
	 * @param string $expected  Expected link relative to home_url().
	 */
	public function test_pretty_permalinks( $structure, $args, $feed, $expected ) {
		$this->set_permalink_structure( $structure );
		$this->register_books_post_type( $args );

		$this->assertSame( home_url( $expected ), get_post_type_archive_feed_link( 'wptests_books', $feed ) );
	}

	/**
	 * Data provider for test_pretty_permalinks().
	 *
	 * @return array[]
	 */
	public static function data_pretty_permalinks() {
		return array(
			'default feed'                       => array( '/%postname%/', array(), '', '/books/feed/' ),
			'default feed passed explicitly'     => array( '/%postname%/', array(), 'rss2', '/books/feed/' ),
			'atom feed'                          => array( '/%postname%/', array(), 'atom', '/books/feed/atom/' ),
			'structure without trailing slash'   => array( '/%postname%', array(), 'atom', '/books/feed/atom/' ),
			'custom archive slug'                => array( '/%postname%/', array( 'has_archive' => 'library' ), '', '/library/feed/' ),
			'archive with front'                 => array( '/blog/%postname%/', array(), '', '/blog/books/feed/' ),
			'archive without front'              => array(
				'/blog/%postname%/',
				array(
					'rewrite' => array(
						'slug'       => 'books',
						'with_front' => false,
					),
				),
				'',
				'/books/feed/',
			),
			'feeds disabled in rewrite'          => array(
				'/%postname%/',
				array(
					'rewrite' => array(
						'slug'  => 'books',
						'feeds' => false,
					),
				),
				'atom',
				'/books/?feed=atom',
			),
			'rewrite disabled for the post type' => array( '/%postname%/', array( 'rewrite' => false ), 'atom', '?post_type=wptests_books&feed=atom' ),
		);
	}

	/**
	 * Tests the feed link for the 'post' post type.
	 *
	 * The 'post' post type archive is the home URL and has no rewrite rules,
	 * so the feed is always added as a query argument.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_post_post_type
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_post_post_type( $structure ) {
		$this->set_permalink_structure( $structure );

		$this->assertSame( home_url() . '?feed=atom', get_post_type_archive_feed_link( 'post', 'atom' ) );
	}

	/**
	 * Data provider for test_post_post_type().
	 *
	 * @return array[]
	 */
	public static function data_post_post_type() {
		return array(
			'plain permalinks'  => array( '' ),
			'pretty permalinks' => array( '/%postname%/' ),
		);
	}

	/**
	 * Tests that the 'default_feed' filter sets the feed type and which feed omits the feed segment.
	 *
	 * @ticket 65819
	 */
	public function test_uses_default_feed_filter() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->register_books_post_type();

		add_filter(
			'default_feed',
			static function () {
				return 'atom';
			}
		);

		$this->assertSame( home_url( '/books/feed/' ), get_post_type_archive_feed_link( 'wptests_books' ), 'An empty feed type should use the filtered default feed.' );
		$this->assertSame( home_url( '/books/feed/' ), get_post_type_archive_feed_link( 'wptests_books', 'atom' ), 'The default feed should not add a feed segment.' );
		$this->assertSame( home_url( '/books/feed/rss2/' ), get_post_type_archive_feed_link( 'wptests_books', 'rss2' ), 'A non-default feed should add a feed segment.' );
	}

	/**
	 * Tests that the archive link from the 'post_type_archive_link' filter is used.
	 *
	 * @ticket 65819
	 */
	public function test_uses_filtered_archive_link() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->register_books_post_type();

		add_filter(
			'post_type_archive_link',
			static function () {
				return 'https://example.org/shelf';
			}
		);

		$this->assertSame( 'https://example.org/shelf/feed/atom/', get_post_type_archive_feed_link( 'wptests_books', 'atom' ) );
	}

	/**
	 * Tests the arguments passed to the 'post_type_archive_feed_link' filter and that its return value is used.
	 *
	 * @ticket 65819
	 */
	public function test_post_type_archive_feed_link_filter() {
		$this->set_permalink_structure( '/%postname%/' );
		$this->register_books_post_type();

		$filter = new MockAction();
		add_filter( 'post_type_archive_feed_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'post_type_archive_feed_link',
			static function ( $link, $feed ) {
				return $link . '?type=' . $feed;
			},
			20,
			2
		);

		$link = get_post_type_archive_feed_link( 'wptests_books' );

		$this->assertSame(
			array( home_url( '/books/feed/' ), 'rss2' ),
			$filter->get_args()[0],
			'The filter should receive the link and the resolved feed type.'
		);
		$this->assertSame( home_url( '/books/feed/?type=rss2' ), $link, 'The filtered link should be returned.' );
	}
}
