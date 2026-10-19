<?php

/**
 * Tests for the_author_posts_link().
 *
 * @group author
 * @group user
 *
 * @covers ::the_author_posts_link
 */
class Tests_User_TheAuthorPostsLink extends WP_UnitTestCase {

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	private static $author_id = 0;

	/**
	 * Post ID authored by self::$author_id.
	 *
	 * @var int
	 */
	private static $post_id = 0;

	/**
	 * Creates shared author and post fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'test_author',
				'display_name' => 'Test Author',
				'description'  => 'test_author',
				'user_url'     => 'http://example.com',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$author_id,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Sets up the global post data before each test.
	 */
	public function set_up() {
		parent::set_up();

		setup_postdata( get_post( self::$post_id ) );
	}

	/**
	 * Cleans up global state after each test.
	 */
	public function tear_down() {
		unset( $GLOBALS['authordata'] );

		parent::tear_down();
	}

	/**
	 * Tests that the_author_posts_link() outputs the author posts link.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_author_posts_link() {
		$output = get_echo( 'the_author_posts_link' );

		$this->assertSame( get_the_author_posts_link(), $output );
		$this->assertStringContainsString( 'rel="author"', $output );
		$this->assertStringContainsString( '>Test Author</a>', $output );
		$this->assertStringContainsString( 'author=' . self::$author_id, $output );
	}

	/**
	 * Tests that the_author_posts_link() outputs pretty permalinks when enabled.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_author_posts_link_with_pretty_permalinks() {
		$this->set_permalink_structure( '/%postname%/' );

		$user     = get_userdata( self::$author_id );
		$output   = get_echo( 'the_author_posts_link' );
		$expected = get_the_author_posts_link();

		$this->set_permalink_structure( '' );

		$this->assertSame( $expected, $output );
		$this->assertStringContainsString( '/author/' . $user->user_nicename . '/', $output );
	}

	/**
	 * Tests that the_author_posts_link() outputs nothing when authordata is not set.
	 *
	 * @ticket 65819
	 */
	public function test_outputs_empty_string_when_authordata_is_not_set() {
		unset( $GLOBALS['authordata'] );

		$this->assertSame( '', get_echo( 'the_author_posts_link' ) );
	}

	/**
	 * Tests that passing a deprecated argument triggers a deprecation notice.
	 *
	 * @ticket 65819
	 * @expectedDeprecated the_author_posts_link
	 */
	public function test_deprecated_argument() {
		get_echo( 'the_author_posts_link', array( 'deprecated' ) );
	}

	/**
	 * Tests that the_author_posts_link() reflects the 'the_author_posts_link' filter.
	 *
	 * @ticket 65819
	 */
	public function test_applies_the_author_posts_link_filter() {
		$filter = static function () {
			return '<a href="https://example.org/custom-author">Custom Author Link</a>';
		};

		add_filter( 'the_author_posts_link', $filter );

		$output = get_echo( 'the_author_posts_link' );

		remove_filter( 'the_author_posts_link', $filter );

		$this->assertSame( '<a href="https://example.org/custom-author">Custom Author Link</a>', $output );
	}
}
