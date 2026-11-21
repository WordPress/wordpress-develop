<?php

/**
 * Tests for wp_get_unapproved_comment_author_email().
 *
 * @group comment
 *
 * @covers ::wp_get_unapproved_comment_author_email
 */
class Tests_Comment_WpGetUnapprovedCommentAuthorEmail extends WP_UnitTestCase {

	/**
	 * Post the comments belong to.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Original $_GET values.
	 *
	 * @var array
	 */
	private $orig_get;

	/**
	 * Original $_COOKIE values.
	 *
	 * @var array
	 */
	private $orig_cookie;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	public function set_up() {
		parent::set_up();

		$this->orig_get    = $_GET;
		$this->orig_cookie = $_COOKIE;

		unset( $_GET['unapproved'], $_GET['moderation-hash'], $_COOKIE[ 'comment_author_email_' . COOKIEHASH ] );
	}

	public function tear_down() {
		$_GET    = $this->orig_get;
		$_COOKIE = $this->orig_cookie;

		parent::tear_down();
	}

	/**
	 * Creates an unapproved comment posted the given number of minutes ago.
	 *
	 * @param int $minutes_ago How long ago the comment was posted.
	 * @return WP_Comment The created comment.
	 */
	private function create_unapproved_comment( $minutes_ago ) {
		return self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_approved'     => '0',
				'comment_author_email' => 'author@example.com',
				'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', time() - $minutes_ago * MINUTE_IN_SECONDS ),
			)
		);
	}

	/**
	 * Sets the query arguments added to the redirect after a comment is held for moderation.
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $hash       Moderation hash.
	 */
	private function set_moderation_query_args( $comment_id, $hash ) {
		$_GET['unapproved']      = (string) $comment_id;
		$_GET['moderation-hash'] = $hash;
	}

	/**
	 * Tests that an empty string is returned without query arguments or a commenter cookie.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_string_without_query_args_or_cookie() {
		$this->assertSame( '', wp_get_unapproved_comment_author_email() );
	}

	/**
	 * Tests that the commenter cookie is used without query arguments.
	 *
	 * @ticket 65819
	 */
	public function test_returns_commenter_cookie_email_without_query_args() {
		$_COOKIE[ 'comment_author_email_' . COOKIEHASH ] = 'cookie@example.com';

		$this->assertSame( 'cookie@example.com', wp_get_unapproved_comment_author_email() );
	}

	/**
	 * Tests that a valid hash for a recent comment returns the comment author's email.
	 *
	 * @ticket 65819
	 */
	public function test_returns_comment_author_email_for_valid_hash() {
		$_COOKIE[ 'comment_author_email_' . COOKIEHASH ] = 'cookie@example.com';

		$comment = $this->create_unapproved_comment( 1 );
		$this->set_moderation_query_args( $comment->comment_ID, wp_hash( $comment->comment_date_gmt ) );

		$this->assertSame( 'author@example.com', wp_get_unapproved_comment_author_email(), 'A valid hash should take precedence over the cookie.' );
	}

	/**
	 * Tests that the commenter cookie is used when the moderation query arguments do not identify a recent comment.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_moderation_query_args
	 *
	 * @param string $scenario Which part of the query arguments is invalid.
	 */
	public function test_falls_back_to_commenter_cookie( $scenario ) {
		$_COOKIE[ 'comment_author_email_' . COOKIEHASH ] = 'cookie@example.com';

		$comment = $this->create_unapproved_comment( 'expired' === $scenario ? 11 : 1 );
		$hash    = wp_hash( $comment->comment_date_gmt );

		switch ( $scenario ) {
			case 'wrong hash':
				$this->set_moderation_query_args( $comment->comment_ID, wp_hash( 'something else' ) );
				break;
			case 'missing hash':
				$_GET['unapproved'] = (string) $comment->comment_ID;
				break;
			case 'missing comment ID':
				$_GET['moderation-hash'] = $hash;
				break;
			case 'unknown comment':
				$this->set_moderation_query_args( PHP_INT_MAX, $hash );
				break;
			case 'expired':
				$this->set_moderation_query_args( $comment->comment_ID, $hash );
				break;
		}

		$this->assertSame( 'cookie@example.com', wp_get_unapproved_comment_author_email() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, string[]>
	 */
	public static function data_invalid_moderation_query_args() {
		return array(
			'wrong hash'                     => array( 'wrong hash' ),
			'missing hash'                   => array( 'missing hash' ),
			'missing comment ID'             => array( 'missing comment ID' ),
			'unknown comment'                => array( 'unknown comment' ),
			'comment older than ten minutes' => array( 'expired' ),
		);
	}
}
