<?php

/**
 * @group author
 * @group user
 *
 * @covers ::get_the_author_posts_link
 */
class Tests_User_GetTheAuthorPostsLink extends WP_UnitTestCase {
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

	/**
	 * @ticket 30355
	 */
	public function test_get_the_author_posts_link_no_permalinks() {
		$author = get_userdata( self::$author_id );

		$GLOBALS['authordata'] = $author->data;

		$link = get_the_author_posts_link();

		$url = sprintf( 'http://%1$s/?author=%2$s', WP_TESTS_DOMAIN, $author->ID );

		$this->assertStringContainsString( $url, $link );
		$this->assertStringContainsString( 'Posts by Test Author', $link );
		$this->assertStringContainsString( '>Test Author</a>', $link );

		unset( $GLOBALS['authordata'] );
	}

	/**
	 * @ticket 30355
	 */
	public function test_get_the_author_posts_link_with_permalinks() {
		$this->set_permalink_structure( '/%postname%/' );

		$author = get_userdata( self::$author_id );

		$GLOBALS['authordata'] = $author;

		$link = get_the_author_posts_link();

		$url = sprintf( 'http://%1$s/author/%2$s/', WP_TESTS_DOMAIN, $author->user_nicename );

		$this->set_permalink_structure( '' );

		$this->assertStringContainsString( $url, $link );
		$this->assertStringContainsString( 'Posts by Test Author', $link );
		$this->assertStringContainsString( '>Test Author</a>', $link );

		unset( $GLOBALS['authordata'] );
	}

	/**
	 * @ticket 58157
	 */
	public function test_get_the_author_posts_link_should_return_empty_string_if_authordata_is_not_set() {
		unset( $GLOBALS['authordata'] );

		$this->assertSame( '', get_the_author_posts_link() );
	}
}
