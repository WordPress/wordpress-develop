<?php

/**
 * Tests for the get_comments_pagenum_link() function.
 *
 * @group link
 * @group comment
 * @covers ::get_comments_pagenum_link
 */
class Tests_Link_GetCommentsPagenumLink extends WP_UnitTestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up test fixtures before class.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->go_to( get_permalink( self::$post_id ) );
	}

	/**
	 * Tests get_comments_pagenum_link() with plain permalinks and oldest default comments page.
	 *
	 * @ticket 65819
	 */
	public function test_get_comments_pagenum_link_plain_permalinks_oldest_comments() {
		update_option( 'default_comments_page', 'oldest' );

		// Page 1 should not have cpage query arg.
		$link_page_one     = get_comments_pagenum_link( 1 );
		$expected_page_one = get_permalink( self::$post_id ) . '#comments';
		$this->assertSame( $expected_page_one, $link_page_one );

		// Page 2 should have cpage=2.
		$link_page_two     = get_comments_pagenum_link( 2 );
		$expected_page_two = add_query_arg( 'cpage', 2, get_permalink( self::$post_id ) ) . '#comments';
		$this->assertSame( $expected_page_two, $link_page_two );
	}

	/**
	 * Tests get_comments_pagenum_link() with pretty permalinks and oldest default comments page.
	 *
	 * @ticket 65819
	 */
	public function test_get_comments_pagenum_link_pretty_permalinks_oldest_comments() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$this->go_to( get_permalink( self::$post_id ) );
		update_option( 'default_comments_page', 'oldest' );

		// Page 1 should not have comment-page slug.
		$link_page_one     = get_comments_pagenum_link( 1 );
		$expected_page_one = get_permalink( self::$post_id ) . '#comments';
		$this->assertSame( $expected_page_one, $link_page_one );

		// Page 2 should have comment-page-2.
		$link_page_two     = get_comments_pagenum_link( 2 );
		$expected_page_two = get_permalink( self::$post_id ) . 'comment-page-2/#comments';
		$this->assertSame( $expected_page_two, $link_page_two );
	}

	/**
	 * Tests get_comments_pagenum_link() with plain permalinks and newest default comments page.
	 *
	 * @ticket 65819
	 */
	public function test_get_comments_pagenum_link_plain_permalinks_newest_comments() {
		update_option( 'default_comments_page', 'newest' );

		// When requesting the newest page (pagenum == max_page), no page parameter should be added.
		$link_newest_page     = get_comments_pagenum_link( 3, 3 );
		$expected_newest_page = get_permalink( self::$post_id ) . '#comments';
		$this->assertSame( $expected_newest_page, $link_newest_page );

		// When requesting an older page (pagenum != max_page), page parameter should be added.
		$link_older_page     = get_comments_pagenum_link( 1, 3 );
		$expected_older_page = add_query_arg( 'cpage', 1, get_permalink( self::$post_id ) ) . '#comments';
		$this->assertSame( $expected_older_page, $link_older_page );
	}

	/**
	 * Tests get_comments_pagenum_link() with pretty permalinks and newest default comments page.
	 *
	 * @ticket 65819
	 */
	public function test_get_comments_pagenum_link_pretty_permalinks_newest_comments() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$this->go_to( get_permalink( self::$post_id ) );
		update_option( 'default_comments_page', 'newest' );

		// When requesting the newest page (pagenum == max_page), no page parameter should be added.
		$link_newest_page     = get_comments_pagenum_link( 3, 3 );
		$expected_newest_page = get_permalink( self::$post_id ) . '#comments';
		$this->assertSame( $expected_newest_page, $link_newest_page );

		// When requesting an older page (pagenum != max_page), comment-page slug should be added.
		$link_older_page     = get_comments_pagenum_link( 1, 3 );
		$expected_older_page = get_permalink( self::$post_id ) . 'comment-page-1/#comments';
		$this->assertSame( $expected_older_page, $link_older_page );
	}

	/**
	 * Tests the 'get_comments_pagenum_link' filter.
	 *
	 * @ticket 65819
	 */
	public function test_get_comments_pagenum_link_filter() {
		add_filter(
			'get_comments_pagenum_link',
			static function ( $result ) {
				return $result . '&filtered=true';
			}
		);

		$link = get_comments_pagenum_link( 2 );

		$this->assertStringEndsWith( '#comments&filtered=true', $link );
	}
}
