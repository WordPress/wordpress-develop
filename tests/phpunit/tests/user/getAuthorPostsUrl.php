<?php

/**
 * Tests for get_author_posts_url().
 *
 * @group author
 * @group user
 *
 * @covers ::get_author_posts_url
 */
class Tests_User_GetAuthorPostsUrl extends WP_UnitTestCase {

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	private static $author_id = 0;

	/**
	 * Author nicename.
	 *
	 * @var string
	 */
	private static $author_nicename = '';

	/**
	 * Creates a shared author fixture.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'          => 'author',
				'user_login'    => 'test_author',
				'user_nicename' => 'test-author',
				'display_name'  => 'Test Author',
			)
		);

		$user                  = get_userdata( self::$author_id );
		self::$author_nicename = $user->user_nicename;
	}

	/**
	 * Tests that get_author_posts_url() returns a query-string URL when pretty permalinks are not used.
	 *
	 * @ticket 65819
	 */
	public function test_returns_query_string_url_without_permalinks() {
		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/?author=' . self::$author_id,
			get_author_posts_url( self::$author_id )
		);
	}

	/**
	 * Tests that get_author_posts_url() returns a pretty permalink when an author permastruct is available,
	 * and looks up the author nicename when it is omitted.
	 *
	 * @ticket 65819
	 */
	public function test_returns_pretty_permalink_and_looks_up_nicename() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/author/' . self::$author_nicename . '/',
			get_author_posts_url( self::$author_id )
		);

		$this->assertSame(
			'http://' . WP_TESTS_DOMAIN . '/author/custom-nicename/',
			get_author_posts_url( self::$author_id, 'custom-nicename' )
		);

		$this->set_permalink_structure( '' );
	}

	/**
	 * Tests that get_author_posts_url() applies the 'author_link' filter.
	 *
	 * @ticket 65819
	 */
	public function test_applies_author_link_filter() {
		$filter = new MockAction();
		add_filter( 'author_link', array( $filter, 'filter' ), 10, 3 );
		add_filter(
			'author_link',
			static function () {
				return 'https://example.org/filtered-author/';
			},
			20
		);

		$result = get_author_posts_url( self::$author_id, self::$author_nicename );

		$this->assertSame( 'https://example.org/filtered-author/', $result );
		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame(
			array(
				'http://' . WP_TESTS_DOMAIN . '/?author=' . self::$author_id,
				self::$author_id,
				self::$author_nicename,
			),
			$filter->get_args()[0]
		);
	}
}
