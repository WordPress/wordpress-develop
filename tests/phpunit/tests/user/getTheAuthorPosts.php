<?php

/**
 * @group author
 * @group user
 *
 * @covers ::get_the_author_posts
 */
class Tests_User_GetTheAuthorPosts extends WP_UnitTestCase {
	protected static $author_id = 0;
	protected static $post_id   = 0;

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
				'post_author'  => self::$author_id,
				'post_status'  => 'publish',
				'post_content' => 'content',
				'post_title'   => 'title',
				'post_type'    => 'post',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		setup_postdata( get_post( self::$post_id ) );
	}

	public function test_get_the_author_posts() {
		// Test with no global post, result should be 0 because no author is found.
		$this->assertSame( 0, get_the_author_posts() );
		$GLOBALS['post'] = self::$post_id;
		$this->assertEquals( 1, get_the_author_posts() );
	}

	/**
	 * @ticket 30904
	 */
	public function test_get_the_author_posts_with_custom_post_type() {
		register_post_type( 'wptests_pt' );

		$cpt_ids         = self::factory()->post->create_many(
			2,
			array(
				'post_author' => self::$author_id,
				'post_type'   => 'wptests_pt',
			)
		);
		$GLOBALS['post'] = $cpt_ids[0];

		$this->assertEquals( 2, get_the_author_posts() );

		_unregister_post_type( 'wptests_pt' );
	}
}
