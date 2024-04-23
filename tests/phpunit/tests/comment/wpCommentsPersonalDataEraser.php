<?php

/**
 * @group comment
 * @group privacy
 *
 * @covers ::wp_comments_personal_data_eraser
 */
class Tests_Comment_wpCommentsPersonalDataEraser extends WP_UnitTestCase {

	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * The `wp_comments_personal_data_eraser()` function should erase user's comments.
	 *
	 * @ticket 43442
	 */
	public function test_wp_comments_personal_data_eraser() {

		$user_id = self::factory()->user->create();

		$args       = array(
			'user_id'              => $user_id,
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-04-14 17:20:00',
			'comment_agent'        => 'COMMENT_AGENT',
			'comment_content'      => 'Comment Content',
		);
		$comment_id = self::factory()->comment->create( $args );

		wp_comments_personal_data_eraser( $args['comment_author_email'] );

		$comment = get_comment( $comment_id );

		$actual = array(
			'comment_ID'           => $comment->comment_ID,
			'user_id'              => $comment->user_id,
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_author_url'   => $comment->comment_author_url,
			'comment_author_IP'    => $comment->comment_author_IP,
			'comment_date'         => $comment->comment_date,
			'comment_date_gmt'     => $comment->comment_date_gmt,
			'comment_agent'        => $comment->comment_agent,
			'comment_content'      => $comment->comment_content,
		);

		$expected = array(
			'comment_ID'           => (string) $comment_id,
			'user_id'              => '0', // Anonymized.
			'comment_author'       => 'Anonymous', // Anonymized.
			'comment_author_email' => '', // Anonymized.
			'comment_author_url'   => '', // Anonymized.
			'comment_author_IP'    => '192.168.0.0', // Anonymized.
			'comment_date'         => '2018-04-14 17:20:00',
			'comment_date_gmt'     => '2018-04-14 17:20:00',
			'comment_agent'        => '', // Anonymized.
			'comment_content'      => 'Comment Content',
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing the `wp_comments_personal_data_eraser()` function's output on an empty first page.
	 *
	 * @ticket 43442
	 */
	public function test_wp_comments_personal_data_eraser_empty_first_page_output() {

		$actual   = wp_comments_personal_data_eraser( 'nocommentsfound@local.host' );
		$expected = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing the `wp_comments_personal_data_eraser()` function's output, for the non-empty first page.
	 *
	 * @ticket 43442
	 */
	public function test_wp_comments_personal_data_eraser_non_empty_first_page_output() {

		$args = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-04-14 17:20:00',
			'comment_agent'        => 'COMMENT_AGENT',
			'comment_content'      => 'Comment Content',
		);
		self::factory()->comment->create( $args );

		$actual   = wp_comments_personal_data_eraser( $args['comment_author_email'] );
		$expected = array(
			'items_removed'  => true,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing the `wp_comments_personal_data_eraser()` function's output, for an empty second page.
	 *
	 * @ticket 43442
	 */
	public function test_wp_comments_personal_data_eraser_empty_second_page_output() {

		$args = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-04-14 17:20:00',
			'comment_agent'        => 'COMMENT_AGENT',
			'comment_content'      => 'Comment Content',
		);
		self::factory()->comment->create( $args );

		$actual   = wp_comments_personal_data_eraser( $args['comment_author_email'], 2 );
		$expected = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing the `wp_anonymize_comment` filter, to prevent comment anonymization.
	 *
	 * @ticket 43442
	 */
	public function test_wp_anonymize_comment_filter_to_prevent_comment_anonymization() {

		$args       = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-04-14 17:20:00',
			'comment_agent'        => 'COMMENT_AGENT',
			'comment_content'      => 'Comment Content',
		);
		$comment_id = self::factory()->comment->create( $args );

		add_filter( 'wp_anonymize_comment', '__return_false' );
		$actual = wp_comments_personal_data_eraser( $args['comment_author_email'] );
		remove_filter( 'wp_anonymize_comment', '__return_false' );

		$message = sprintf( 'Comment %d contains personal data but could not be anonymized.', $comment_id );

		$expected = array(
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => array( $message ),
			'done'           => true,
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing the `wp_anonymize_comment` filter, to prevent comment anonymization, with a custom message.
	 *
	 * @ticket 43442
	 */
	public function test_wp_anonymize_comment_filter_to_prevent_comment_anonymization_with_custom_message() {

		$args       = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-04-14 17:20:00',
			'comment_agent'        => 'COMMENT_AGENT',
			'comment_content'      => 'Comment Content',
		);
		$comment_id = self::factory()->comment->create( $args );

		add_filter( 'wp_anonymize_comment', array( $this, 'wp_anonymize_comment_custom_message' ), 10, 3 );
		$actual = wp_comments_personal_data_eraser( $args['comment_author_email'] );
		remove_filter( 'wp_anonymize_comment', array( $this, 'wp_anonymize_comment_custom_message' ) );

		$message = sprintf( 'Some custom message for comment %d.', $comment_id );

		$expected = array(
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => array( $message ),
			'done'           => true,
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Callback for the `wp_anonymize_comment` filter.
	 *
	 * @param  bool|string $anonymize          Whether to apply the comment anonymization (bool).
	 *                                         Custom prevention message (string). Default true.
	 * @param  WP_Comment  $comment            WP_Comment object.
	 * @param  array       $anonymized_comment Anonymized comment data.
	 * @return string
	 */
	public function wp_anonymize_comment_custom_message( $anonymize, $comment, $anonymized_comment ) {
		return sprintf( 'Some custom message for comment %d.', $comment->comment_ID );
	}

	/**
	 * Testing that `wp_comments_personal_data_eraser()` orders comments by ID.
	 *
	 * @ticket 57700
	 */
	public function test_wp_comments_personal_data_eraser_orders_comments_by_id() {

		$args = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Comment Author',
			'comment_author_email' => 'personal@local.host',
			'comment_author_url'   => 'https://local.host/',
			'comment_author_IP'    => '192.168.0.1',
			'comment_date'         => '2018-04-14 17:20:00',
			'comment_agent'        => 'COMMENT_AGENT',
			'comment_content'      => 'Comment Content',
		);
		self::factory()->comment->create( $args );

		$filter = new MockAction();
		add_filter( 'comments_clauses', array( &$filter, 'filter' ) );

		wp_comments_personal_data_eraser( $args['comment_author_email'] );

		$clauses = $filter->get_args()[0][0];

		$this->assertStringContainsString( 'comment_ID', $clauses['orderby'] );
	}
}
