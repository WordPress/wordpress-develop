<?php

/**
 * @group author
 * @group user
 *
 * @covers ::get_the_author_meta
 */
class Tests_User_GetTheAuthorMeta extends WP_UnitTestCase {
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

	public function test_get_the_author_meta() {
		$this->assertSame( 'test_author', get_the_author_meta( 'login' ) );
		$this->assertSame( 'test_author', get_the_author_meta( 'user_login' ) );
		$this->assertSame( 'Test Author', get_the_author_meta( 'display_name' ) );

		$this->assertSame( 'test_author', trim( get_the_author_meta( 'description' ) ) );
		$this->assertSame( 'test_author', get_the_author_meta( 'user_description' ) );

		add_user_meta( self::$author_id, 'user_description', 'user description' );
		$this->assertSame( 'user description', get_user_meta( self::$author_id, 'user_description', true ) );
		// user_description in meta is ignored. The content of description is returned instead.
		// See #20285.
		$this->assertSame( 'test_author', get_the_author_meta( 'user_description' ) );
		$this->assertSame( 'test_author', trim( get_the_author_meta( 'description' ) ) );

		update_user_meta( self::$author_id, 'user_description', '' );
		$this->assertSame( '', get_user_meta( self::$author_id, 'user_description', true ) );
		$this->assertSame( 'test_author', get_the_author_meta( 'user_description' ) );
		$this->assertSame( 'test_author', trim( get_the_author_meta( 'description' ) ) );

		$this->assertSame( '', get_the_author_meta( 'does_not_exist' ) );
	}

	/**
	 * @ticket 20529
	 * @ticket 58157
	 */
	public function test_get_the_author_meta_should_return_empty_string_if_authordata_is_not_set() {
		unset( $GLOBALS['authordata'] );

		$this->assertSame( '', get_the_author_meta( 'id' ) );
		$this->assertSame( '', get_the_author_meta( 'user_login' ) );
		$this->assertSame( '', get_the_author_meta( 'does_not_exist' ) );
	}
}
