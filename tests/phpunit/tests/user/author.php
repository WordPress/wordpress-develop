<?php

/**
 * Test functions in wp-includes/author-template.php
 *
 * @group author
 * @group user
 */
class Tests_User_Author_Template extends WP_UnitTestCase {
	protected static $author_id = 0;
	protected static $post_id   = 0;

	private $permalink_structure;

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

	public function test_get_the_author() {
		$author_name = get_the_author();
		$user        = new WP_User( self::$author_id );

		$this->assertSame( $user->display_name, $author_name );
		$this->assertSame( 'Test Author', $author_name );
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

	public function test_get_the_author_meta_no_authordata() {
		unset( $GLOBALS['authordata'] );
		$this->assertSame( '', get_the_author_meta( 'id' ) );
		$this->assertSame( '', get_the_author_meta( 'user_login' ) );
		$this->assertSame( '', get_the_author_meta( 'does_not_exist' ) );
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
		$this->assertSame( array( 'the_author_link' ), $filter->get_tags() );
	}
}
