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

		$GLOBALS['post'] = get_post( self::$post_id );
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

	/**
	 * @ticket 64104
	 */
	public function test_get_the_modified_author_when_post_global_does_not_exist() {
		$GLOBALS['post'] = null;
		$this->assertNull( get_the_modified_author() );
	}

	/**
	 * @ticket 64104
	 */
	public function test_get_the_modified_author_when_invalid_post() {
		$this->assertNull( get_the_modified_author( -1 ) );
	}

	/**
	 * @ticket 64104
	 */
	public function test_get_the_modified_author_for_another_post() {
		$expected_display_name = 'Test Editor';

		$editor_id = self::factory()->user->create(
			array(
				'role'         => 'editor',
				'user_login'   => 'test_editor',
				'display_name' => $expected_display_name,
				'description'  => 'test_editor',
			)
		);

		$another_post_id = self::factory()->post->create();

		$this->assertNull( get_the_modified_author( $another_post_id ) );
		$this->assertNull( get_the_modified_author( get_post( $another_post_id ) ) );

		add_post_meta( $another_post_id, '_edit_last', $editor_id );
		$this->assertSame( $expected_display_name, get_the_modified_author( $another_post_id ) );
		$this->assertSame( $expected_display_name, get_the_modified_author( get_post( $another_post_id ) ) );
	}
}
