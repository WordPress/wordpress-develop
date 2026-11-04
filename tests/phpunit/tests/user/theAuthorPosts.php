<?php

/**
 * Tests for the_author_posts().
 *
 * @group author
 * @group user
 *
 * @covers ::the_author_posts
 */
class Tests_User_TheAuthorPosts extends WP_UnitTestCase {

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
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$author_id,
				'post_status' => 'publish',
				'post_title'  => 'First Author Post',
			)
		);

		$factory->post->create(
			array(
				'post_author' => self::$author_id,
				'post_status' => 'publish',
				'post_title'  => 'Second Author Post',
			)
		);
	}

	/**
	 * Sets up the global post data before each test.
	 */
	public function set_up() {
		parent::set_up();

		$GLOBALS['post'] = get_post( self::$post_id );
		setup_postdata( $GLOBALS['post'] );
	}

	/**
	 * Cleans up global state after each test.
	 */
	public function tear_down() {
		unregister_post_type( 'wptests_cpt' );
		unset( $GLOBALS['post'], $GLOBALS['authordata'] );

		parent::tear_down();
	}

	/**
	 * Tests that the_author_posts() echoes the current post author's post count.
	 *
	 * @ticket 65819
	 */
	public function test_echoes_author_post_count() {
		$output = get_echo( 'the_author_posts' );

		$this->assertSame( '2', $output );
	}

	/**
	 * Tests that the_author_posts() echoes 0 when no global post is available.
	 *
	 * @ticket 65819
	 */
	public function test_echoes_zero_when_no_global_post_available() {
		unset( $GLOBALS['post'] );

		$this->assertSame( '0', get_echo( 'the_author_posts' ) );
	}
}
