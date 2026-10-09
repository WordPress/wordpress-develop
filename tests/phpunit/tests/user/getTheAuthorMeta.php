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

	/**
	 * @ticket 58001
	 *
	 * @dataProvider data_get_the_author_meta_user_fields
	 *
	 * @param string $field User field.
	 */
	public function test_get_the_author_meta_user_field_does_not_load_user_meta( $field ) {
		$expected = get_userdata( self::$author_id )->$field;
		wp_cache_delete( self::$author_id, 'user_meta' );

		$this->assertSame( $expected, get_the_author_meta( $field, self::$author_id ), "The {$field} field should be returned." );
		$this->assertFalse( wp_cache_get( self::$author_id, 'user_meta' ), 'User meta should not be loaded.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public function data_get_the_author_meta_user_fields() {
		return array(
			'display_name'  => array( 'display_name' ),
			'user_nicename' => array( 'user_nicename' ),
			'user_email'    => array( 'user_email' ),
		);
	}

	/**
	 * @ticket 58001
	 */
	public function test_get_the_author_meta_roles_with_user_id() {
		$this->assertSame( array( 'author' ), get_the_author_meta( 'roles', self::$author_id ), 'The roles field should be loaded on demand.' );
	}
}
