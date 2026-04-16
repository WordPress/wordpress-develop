<?php

/**
 * @group comment
 *
 * @covers ::wp_allow_comment
 */
class Tests_Comment_WpAllowComment extends WP_UnitTestCase {
	protected static $post_id;
	protected static $comment_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id    = $factory->post->create();
		self::$comment_id = $factory->comment->create(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_approved'     => '1',
				'comment_author'       => 'Bob',
				'comment_author_email' => 'bobthebuilder@example.com',
				'comment_author_url'   => 'http://example.com',
				'comment_content'      => 'Yes, we can!',
			)
		);

		update_option( 'comment_previously_approved', 0 );
	}

	public static function wpTeardownAfterClass() {
		wp_delete_post( self::$post_id, true );
		wp_delete_comment( self::$comment_id, true );

		update_option( 'comment_previously_approved', 1 );
	}

	public function test_allow_comment_if_comment_author_emails_differ() {
		$now          = time();
		$comment_data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Bob',
			'comment_author_email' => 'sideshowbob@example.com',
			'comment_author_url'   => 'http://example.com',
			'comment_content'      => 'Yes, we can!',
			'comment_author_IP'    => '192.168.0.1',
			'comment_parent'       => 0,
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $now ),
			'comment_agent'        => 'Bobbot/2.1',
			'comment_type'         => '',
		);

		$result = wp_allow_comment( $comment_data );

		$this->assertSame( 1, $result );
	}

	/**
	 * @ticket 40319
	 * @covers ::check_comment
	 */
	public function test_allow_comment_if_previously_approved_author_name_and_email_contain_apostrophe() {
		update_option( 'comment_previously_approved', 1 );
		add_filter( 'comment_flood_filter', '__return_false' );

		$now = time();

		// Insert an already-approved comment with apostrophe in name and email.
		$approved_comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_approved'     => '1',
				'comment_author'       => "O'Brien",
				'comment_author_email' => "o'brien@example.com",
				'comment_author_url'   => 'http://example.com',
				'comment_content'      => 'Test comment.',
				'comment_parent'       => 0,
				'comment_author_IP'    => '192.168.0.1',
				'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $now - 60 ),
				'comment_agent'        => 'TestAgent/1.0',
				'comment_type'         => '',
			)
		);

		$new_comment = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => wp_slash( "O'Brien" ),
			'comment_author_email' => wp_slash( "o'brien@example.com" ),
			'comment_author_url'   => 'http://example.com',
			'comment_content'      => 'A new comment.',
			'comment_parent'       => 0,
			'comment_author_IP'    => '192.168.0.1',
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $now ),
			'comment_agent'        => 'TestAgent/1.0',
			'comment_type'         => '',
		);

		$result = wp_allow_comment( $new_comment );

		wp_delete_comment( $approved_comment_id, true );
		update_option( 'comment_previously_approved', 0 );
		remove_filter( 'comment_flood_filter', '__return_false' );

		$this->assertSame( 1, $result, 'Comment from previously-approved author with apostrophe in name/email should be auto-approved.' );
	}

	public function test_die_as_duplicate_if_comment_author_name_and_emails_match() {
		$this->expectException( 'WPDieException' );

		$now          = time();
		$comment_data = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Bob',
			'comment_author_email' => 'bobthebuilder@example.com',
			'comment_author_url'   => 'http://example.com',
			'comment_content'      => 'Yes, we can!',
			'comment_author_IP'    => '192.168.0.1',
			'comment_parent'       => 0,
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $now ),
			'comment_agent'        => 'Bobbot/2.1',
			'comment_type'         => '',
		);

		$result = wp_allow_comment( $comment_data );
	}
}
