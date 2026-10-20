<?php

/**
 * Tests for the `get_page_link()` and `_get_page_link()` functions.
 *
 * @group link
 */
class Tests_Link_GetPageLink extends WP_UnitTestCase {

	/**
	 * Published parent page ID.
	 *
	 * @var int
	 */
	private static $parent_page_id;

	/**
	 * Published child page ID.
	 *
	 * @var int
	 */
	private static $child_page_id;

	/**
	 * Draft page ID.
	 *
	 * @var int
	 */
	private static $draft_page_id;

	/**
	 * Private page ID.
	 *
	 * @var int
	 */
	private static $private_page_id;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private static $subscriber_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$parent_page_id = $factory->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'parent-page',
			)
		);

		self::$child_page_id = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'child-page',
				'post_parent' => self::$parent_page_id,
			)
		);

		self::$draft_page_id = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'draft-page',
				'post_status' => 'draft',
			)
		);

		self::$private_page_id = $factory->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'private-page',
				'post_status' => 'private',
			)
		);

		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Tests that get_page_link() returns a page_id query link with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 * @covers ::_get_page_link
	 */
	public function test_get_page_link_plain_permalinks() {
		$this->set_permalink_structure( '' );

		$this->assertSame( home_url( '?page_id=' . self::$parent_page_id ), get_page_link( self::$parent_page_id ) );
	}

	/**
	 * Tests that get_page_link() builds the page path with pretty permalinks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 * @covers ::_get_page_link
	 *
	 * @dataProvider data_get_page_link_pretty_permalinks
	 *
	 * @param string $structure Permalink structure.
	 * @param string $page      Fixture page key, 'parent' or 'child'.
	 * @param string $expected  Expected path relative to home_url().
	 */
	public function test_get_page_link_pretty_permalinks( $structure, $page, $expected ) {
		$this->set_permalink_structure( $structure );

		$page_id = 'child' === $page ? self::$child_page_id : self::$parent_page_id;

		$this->assertSame( home_url( $expected ), get_page_link( $page_id ) );
	}

	/**
	 * Data provider for test_get_page_link_pretty_permalinks().
	 *
	 * @return array[]
	 */
	public static function data_get_page_link_pretty_permalinks() {
		return array(
			'top-level page with trailing slash'    => array( '/%postname%/', 'parent', '/parent-page/' ),
			'child page includes the parent slug'   => array( '/%postname%/', 'child', '/parent-page/child-page/' ),
			'top-level page without trailing slash' => array( '/%postname%', 'parent', '/parent-page' ),
			'child page without trailing slash'     => array( '/%postname%', 'child', '/parent-page/child-page' ),
		);
	}

	/**
	 * Tests that get_page_link() accepts a post object.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 */
	public function test_get_page_link_accepts_post_object() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame( home_url( '/parent-page/child-page/' ), get_page_link( get_post( self::$child_page_id ) ) );
	}

	/**
	 * Tests that get_page_link() defaults to the global post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 */
	public function test_get_page_link_defaults_to_global_post() {
		$this->set_permalink_structure( '/%postname%/' );

		$GLOBALS['post'] = get_post( self::$child_page_id );

		$this->assertSame( home_url( '/parent-page/child-page/' ), get_page_link() );
	}

	/**
	 * Tests that get_page_link() keeps the %pagename% placeholder when $leavename is true.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 * @covers ::_get_page_link
	 */
	public function test_get_page_link_leavename_keeps_placeholder() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame( home_url( '/%pagename%/' ), get_page_link( self::$parent_page_id, true ) );
	}

	/**
	 * Tests that a draft page gets a plain link unless a sample permalink is requested.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 * @covers ::_get_page_link
	 */
	public function test_get_page_link_draft_page() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame( home_url( '?page_id=' . self::$draft_page_id ), get_page_link( self::$draft_page_id ), 'A draft page should use a plain link.' );
		$this->assertSame( home_url( '/draft-page/' ), get_page_link( self::$draft_page_id, false, true ), 'A sample permalink should use the page path.' );
	}

	/**
	 * Tests that a private page only gets a pretty link for users who can read it.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 * @covers ::_get_page_link
	 */
	public function test_get_page_link_private_page_depends_on_capability() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame( home_url( '?page_id=' . self::$private_page_id ), get_page_link( self::$private_page_id ), 'A logged-out user should get a plain link.' );

		wp_set_current_user( self::$subscriber_id );

		$this->assertSame( home_url( '?page_id=' . self::$private_page_id ), get_page_link( self::$private_page_id ), 'A subscriber should get a plain link.' );

		wp_set_current_user( self::$admin_id );

		$this->assertSame( home_url( '/private-page/' ), get_page_link( self::$private_page_id ), 'An administrator should get the page path.' );
	}

	/**
	 * Tests that get_page_link() returns the home URL for the static front page.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 */
	public function test_get_page_link_static_front_page_returns_home_url() {
		$this->set_permalink_structure( '/%postname%/' );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$parent_page_id );

		$this->assertSame( home_url( '/' ), get_page_link( self::$parent_page_id ), 'The front page should link to the home URL.' );
		$this->assertSame( home_url( '/parent-page/child-page/' ), get_page_link( self::$child_page_id ), 'Other pages should keep their path.' );
	}

	/**
	 * Tests that page_on_front is ignored when the front page displays the latest posts.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 */
	public function test_get_page_link_ignores_page_on_front_when_showing_posts() {
		$this->set_permalink_structure( '/%postname%/' );

		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', self::$parent_page_id );

		$this->assertSame( home_url( '/parent-page/' ), get_page_link( self::$parent_page_id ) );
	}

	/**
	 * Tests that _get_page_link() ignores the static front page setting.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_get_page_link
	 */
	public function test_private_get_page_link_ignores_static_front_page() {
		$this->set_permalink_structure( '/%postname%/' );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$parent_page_id );

		$this->assertSame( home_url( '/parent-page/' ), _get_page_link( self::$parent_page_id ) );
	}

	/**
	 * Tests the arguments passed to the 'page_link' filter and that its return value is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 */
	public function test_get_page_link_page_link_filter() {
		$this->set_permalink_structure( '/%postname%/' );

		$filter = new MockAction();
		add_filter( 'page_link', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'page_link',
			static function ( $link, $post_id ) {
				return $link . '?filtered=' . $post_id;
			},
			20,
			2
		);

		$link = get_page_link( self::$draft_page_id, false, true );

		$this->assertSame(
			array( home_url( '/draft-page/' ), self::$draft_page_id, true ),
			$filter->get_args()[0],
			'The filter should receive the link, the page ID and the sample flag.'
		);
		$this->assertSame( home_url( '/draft-page/?filtered=' . self::$draft_page_id ), $link, 'The filtered link should be returned.' );
	}

	/**
	 * Tests that the 'page_link' filter receives the home URL for the static front page.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_page_link
	 */
	public function test_get_page_link_page_link_filter_for_static_front_page() {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$parent_page_id );

		$page_link_filter     = new MockAction();
		$get_page_link_filter = new MockAction();
		add_filter( 'page_link', array( $page_link_filter, 'filter' ), 10, 3 );
		add_filter( '_get_page_link', array( $get_page_link_filter, 'filter' ), 10, 2 );

		get_page_link( self::$parent_page_id );

		$this->assertSame(
			array( home_url( '/' ), self::$parent_page_id, false ),
			$page_link_filter->get_args()[0],
			'The page_link filter should receive the home URL.'
		);
		$this->assertSame( 0, $get_page_link_filter->get_call_count(), 'The _get_page_link filter should not run for the front page.' );
	}

	/**
	 * Tests the arguments passed to the '_get_page_link' filter and that its return value is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::_get_page_link
	 * @covers ::get_page_link
	 */
	public function test_get_page_link_private_filter() {
		$this->set_permalink_structure( '/%postname%/' );

		$filter = new MockAction();
		add_filter( '_get_page_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'_get_page_link',
			static function ( $link ) {
				return $link . 'inner/';
			},
			20
		);

		$link = get_page_link( self::$child_page_id );

		$this->assertSame(
			array( home_url( '/parent-page/child-page/' ), self::$child_page_id ),
			$filter->get_args()[0],
			'The filter should receive the link and the page ID.'
		);
		$this->assertSame( home_url( '/parent-page/child-page/inner/' ), $link, 'The filtered link should be passed on by get_page_link().' );
	}
}
