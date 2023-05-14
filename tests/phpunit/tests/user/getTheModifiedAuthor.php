<?php

/**
 * @group author
 * @group user
 *
 * @covers ::get_the_modified_author
 */
class Tests_User_GetTheModifiedAuthor extends WP_UnitTestCase {
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

		add_post_meta( self::$post_id, '_edit_last', self::$author_id );
	}

	public function set_up() {
		parent::set_up();

		$GLOBALS['post'] = self::$post_id;
	}

	public function test_get_the_modified_author() {
		$author_name = get_the_modified_author();
		$user        = new WP_User( self::$author_id );

		$this->assertSame( $user->display_name, $author_name );
		$this->assertSame( 'Test Author', $author_name );
	}

	/**
	 * @ticket 58157
	 */
	public function test_get_the_modified_author_should_return_empty_string_if_user_id_does_not_exist() {
		update_post_meta( self::$post_id, '_edit_last', -1 );

		$this->assertSame( '', get_the_modified_author() );
	}
}
