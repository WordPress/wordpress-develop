<?php

/**
 * @group author
 * @group user
 *
 * @covers ::get_the_author_link
 */
class Tests_User_GetTheAuthorLink extends WP_UnitTestCase {
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
	 * @ticket 51859
	 *
	 * @covers ::get_the_author_link
	 */
	public function test_get_the_author_link() {
		$author_url          = get_the_author_meta( 'url' );
		$author_display_name = get_the_author();

		$link = get_the_author_link();

		$this->assertStringContainsString( $author_url, $link, 'The link does not contain the author URL' );
		$this->assertStringContainsString( $author_display_name, $link, 'The link does not contain the author display name' );
	}

	/**
	 * @ticket 51859
	 *
	 * @covers ::get_the_author_link
	 */
	public function test_filtered_get_the_author_link() {
		$filter = new MockAction();

		add_filter( 'the_author_link', array( &$filter, 'filter' ) );

		get_the_author_link();

		$this->assertSame( 1, $filter->get_call_count() );
		$this->assertSame( array( 'the_author_link' ), $filter->get_hook_names() );
	}
}
