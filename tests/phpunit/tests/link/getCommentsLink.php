<?php

/**
 * Tests for the `get_comments_link()` and `comments_link()` functions.
 *
 * @group link
 * @group comment
 */
class Tests_Link_GetCommentsLink extends WP_UnitTestCase {

	/**
	 * Post ID without comments.
	 *
	 * @var int
	 */
	private static $post_without_comments;

	/**
	 * Post ID with an approved comment.
	 *
	 * @var int
	 */
	private static $post_with_comments;

	/**
	 * Post ID with only an unapproved comment.
	 *
	 * @var int
	 */
	private static $post_with_unapproved_comment;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_without_comments        = $factory->post->create( array( 'post_name' => 'no-comments' ) );
		self::$post_with_comments           = $factory->post->create( array( 'post_name' => 'with-comments' ) );
		self::$post_with_unapproved_comment = $factory->post->create( array( 'post_name' => 'unapproved-comment' ) );

		$factory->comment->create( array( 'comment_post_ID' => self::$post_with_comments ) );
		$factory->comment->create(
			array(
				'comment_post_ID'  => self::$post_with_unapproved_comment,
				'comment_approved' => '0',
			)
		);
	}

	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Tests the fragment for posts with and without approved comments.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_comments_link
	 *
	 * @dataProvider data_comment_fragments
	 *
	 * @param string $post     Fixture post key.
	 * @param string $expected Expected path relative to home_url().
	 */
	public function test_comment_fragments( $post, $expected ) {
		$this->assertSame( home_url( $expected ), get_comments_link( self::${$post} ) );
	}

	/**
	 * Data provider for test_comment_fragments().
	 *
	 * @return array[]
	 */
	public static function data_comment_fragments() {
		return array(
			'no comments'                => array( 'post_without_comments', '/no-comments/#respond' ),
			'approved comment'           => array( 'post_with_comments', '/with-comments/#comments' ),
			'only an unapproved comment' => array( 'post_with_unapproved_comment', '/unapproved-comment/#respond' ),
		);
	}

	/**
	 * Tests that get_comments_link() uses the post permalink with plain permalinks.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_comments_link
	 */
	public function test_plain_permalinks() {
		$this->set_permalink_structure( '' );

		$this->assertSame( home_url( '/?p=' . self::$post_with_comments . '#comments' ), get_comments_link( self::$post_with_comments ) );
	}

	/**
	 * Tests that get_comments_link() accepts a post object.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_comments_link
	 */
	public function test_accepts_post_object() {
		$this->assertSame( home_url( '/with-comments/#comments' ), get_comments_link( get_post( self::$post_with_comments ) ) );
	}

	/**
	 * Tests that get_comments_link() defaults to the global post, and that an explicit post takes precedence.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_comments_link
	 */
	public function test_uses_global_post_by_default() {
		$GLOBALS['post'] = get_post( self::$post_with_comments );

		$this->assertSame( home_url( '/with-comments/#comments' ), get_comments_link(), 'The global post should be used by default.' );
		$this->assertSame( home_url( '/no-comments/#respond' ), get_comments_link( self::$post_without_comments ), 'An explicit post should be used instead of the global post.' );
	}

	/**
	 * Tests the arguments passed to the 'get_comments_link' filter and that its return value is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_comments_link
	 */
	public function test_get_comments_link_filter() {
		$post = get_post( self::$post_with_comments );

		$filter = new MockAction();
		add_filter( 'get_comments_link', array( $filter, 'filter' ), 10, 2 );
		add_filter(
			'get_comments_link',
			static function ( $comments_link ) {
				return str_replace( '#comments', '#discussion', $comments_link );
			},
			20
		);

		$comments_link = get_comments_link( $post );

		$this->assertSame( array( home_url( '/with-comments/#comments' ), $post ), $filter->get_args()[0], 'The filter should receive the link and the post.' );
		$this->assertSame( home_url( '/with-comments/#discussion' ), $comments_link, 'The filtered link should be returned.' );
	}

	/**
	 * Tests that comments_link() displays the comments link of the global post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::comments_link
	 */
	public function test_comments_link_displays_link_for_global_post() {
		$GLOBALS['post'] = get_post( self::$post_with_comments );

		$this->expectOutputString( home_url( '/with-comments/#comments' ) );

		comments_link();
	}

	/**
	 * Tests that comments_link() escapes the URL.
	 *
	 * @ticket 65819
	 *
	 * @covers ::comments_link
	 */
	public function test_comments_link_escapes_url() {
		$GLOBALS['post'] = get_post( self::$post_with_comments );

		add_filter(
			'get_comments_link',
			static function () {
				return 'https://example.org/?a=1&b=2#comments';
			}
		);

		$this->expectOutputString( 'https://example.org/?a=1&#038;b=2#comments' );

		comments_link();
	}

	/**
	 * Tests that comments_link() triggers a deprecation notice for each deprecated argument.
	 *
	 * @ticket 65819
	 *
	 * @covers ::comments_link
	 *
	 * @dataProvider data_comments_link_deprecated_arguments
	 *
	 * @param string $deprecated   First deprecated argument.
	 * @param string $deprecated_2 Second deprecated argument.
	 */
	public function test_comments_link_deprecated_arguments( $deprecated, $deprecated_2 ) {
		$this->setExpectedDeprecated( 'comments_link' );

		$GLOBALS['post'] = get_post( self::$post_without_comments );

		$this->expectOutputString( home_url( '/no-comments/#respond' ) );

		comments_link( $deprecated, $deprecated_2 );
	}

	/**
	 * Data provider for test_comments_link_deprecated_arguments().
	 *
	 * @return array[]
	 */
	public static function data_comments_link_deprecated_arguments() {
		return array(
			'first argument'  => array( 'deprecated', '' ),
			'second argument' => array( '', 'deprecated' ),
		);
	}
}
