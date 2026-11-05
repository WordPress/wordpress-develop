<?php

/**
 * Tests for wp_check_comment_flood().
 *
 * @group comment
 *
 * @covers ::wp_check_comment_flood
 */
class Tests_Comment_WpCheckCommentFlood extends WP_UnitTestCase {

	/**
	 * IP address of the visitor in the tests.
	 *
	 * @var string
	 */
	const IP = '192.0.2.10';

	/**
	 * Email address of the visitor in the tests.
	 *
	 * @var string
	 */
	const EMAIL = 'visitor@example.org';

	/**
	 * ID of the post that gets the comments.
	 *
	 * @var int
	 */
	protected static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Creates a comment that was posted a number of seconds ago.
	 *
	 * @param int   $seconds_ago Seconds since the comment was posted.
	 * @param array $args        Optional. Other arguments of the comment.
	 * @return int ID of the comment.
	 */
	private function create_comment_posted_ago( $seconds_ago, $args = array() ) {
		$time = time() - $seconds_ago;

		return self::factory()->comment->create(
			array_merge(
				array(
					'comment_post_ID'      => self::$post_id,
					'comment_author_IP'    => '203.0.113.99',
					'comment_author_email' => 'someone-else@example.org',
					'comment_date'         => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $time ) ),
					'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $time ),
				),
				$args
			)
		);
	}

	/**
	 * Checks a new comment of the visitor, which is posted now.
	 *
	 * @param bool $is_flood  Result of the checks before this one.
	 * @param bool $avoid_die Whether to return instead of ending the request.
	 * @return bool Whether the comment is a flood.
	 */
	private function check_new_comment( $is_flood = false, $avoid_die = true ) {
		return wp_check_comment_flood( $is_flood, self::IP, self::EMAIL, gmdate( 'Y-m-d H:i:s' ), $avoid_die );
	}

	/**
	 * Tests that a flood that another callback already found is kept.
	 *
	 * @ticket 66494
	 */
	public function test_should_keep_a_flood_found_by_another_callback() {
		$this->assertTrue( $this->check_new_comment( true ) );
	}

	/**
	 * Tests that a comment is allowed when the visitor has not commented before.
	 *
	 * @ticket 66494
	 */
	public function test_should_allow_a_comment_without_previous_comments() {
		$this->assertFalse( $this->check_new_comment() );
	}

	/**
	 * Tests that a comment is a flood when the same IP address commented a few seconds ago.
	 *
	 * @ticket 66494
	 */
	public function test_should_find_a_flood_by_ip_address() {
		$this->create_comment_posted_ago( 3, array( 'comment_author_IP' => self::IP ) );

		$this->assertTrue( $this->check_new_comment() );
	}

	/**
	 * Tests that a comment is a flood when the same email address commented a few seconds ago.
	 *
	 * @ticket 66494
	 */
	public function test_should_find_a_flood_by_email_address() {
		$this->create_comment_posted_ago( 3, array( 'comment_author_email' => self::EMAIL ) );

		$this->assertTrue( $this->check_new_comment() );
	}

	/**
	 * Tests that a comment is allowed when only other visitors commented recently.
	 *
	 * @ticket 66494
	 */
	public function test_should_allow_a_comment_when_only_others_commented_recently() {
		$this->create_comment_posted_ago( 3 );

		$this->assertFalse( $this->check_new_comment() );
	}

	/**
	 * Tests that a comment is allowed when the last one was posted at least 15 seconds ago.
	 *
	 * @ticket 66494
	 */
	public function test_should_allow_a_comment_posted_more_than_15_seconds_after_the_last() {
		$this->create_comment_posted_ago( 30, array( 'comment_author_IP' => self::IP ) );

		$this->assertFalse( $this->check_new_comment() );
	}

	/**
	 * Tests that comments older than one hour are not considered.
	 *
	 * @ticket 66494
	 */
	public function test_should_ignore_comments_older_than_one_hour() {
		$this->create_comment_posted_ago( HOUR_IN_SECONDS + 60, array( 'comment_author_IP' => self::IP ) );

		// A callback of the filter that blocks every comment shows that the database query found nothing.
		add_filter( 'comment_flood_filter', '__return_true' );

		$this->assertFalse( $this->check_new_comment() );
	}

	/**
	 * Tests that a comment less than one hour old is considered.
	 *
	 * @ticket 66494
	 */
	public function test_should_consider_comments_less_than_one_hour_old() {
		$this->create_comment_posted_ago( HOUR_IN_SECONDS - 60, array( 'comment_author_IP' => self::IP ) );

		add_filter( 'comment_flood_filter', '__return_true' );

		$this->assertTrue( $this->check_new_comment() );
	}

	/**
	 * Tests that a logged-in user is matched by user ID, and not by IP address.
	 *
	 * @ticket 66494
	 */
	public function test_should_match_logged_in_users_by_user_id() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->create_comment_posted_ago( 3, array( 'user_id' => $user_id ) );

		$this->assertTrue( $this->check_new_comment() );
	}

	/**
	 * Tests that the IP address of a logged-in user is not used to find a flood.
	 *
	 * @ticket 66494
	 */
	public function test_should_not_match_logged_in_users_by_ip_address() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->create_comment_posted_ago( 3, array( 'comment_author_IP' => self::IP ) );

		$this->assertFalse( $this->check_new_comment() );
	}

	/**
	 * Tests that users who can manage options, or who can moderate comments, are not throttled.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_capabilities_that_are_not_throttled
	 *
	 * @param string $capability Capability that the user has. The user has no other relevant capability.
	 */
	public function test_should_not_throttle_users_who_can_manage_options_or_moderate_comments( $capability ) {
		$user = wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$user->add_cap( $capability );

		$other_capability = 'manage_options' === $capability ? 'moderate_comments' : 'manage_options';
		$this->assertFalse( current_user_can( $other_capability ), 'The user should have only one of the two capabilities.' );

		$this->create_comment_posted_ago(
			3,
			array(
				'user_id'              => $user->ID,
				'comment_author_IP'    => self::IP,
				'comment_author_email' => self::EMAIL,
			)
		);

		$this->assertFalse( $this->check_new_comment() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_capabilities_that_are_not_throttled() {
		return array(
			'manage_options'    => array( 'manage_options' ),
			'moderate_comments' => array( 'moderate_comments' ),
		);
	}

	/**
	 * Tests that a user who can neither manage options nor moderate comments is throttled.
	 *
	 * @ticket 66494
	 */
	public function test_should_throttle_users_without_those_capabilities() {
		$user = wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse( current_user_can( 'manage_options' ) );
		$this->assertFalse( current_user_can( 'moderate_comments' ) );

		$this->create_comment_posted_ago( 3, array( 'user_id' => $user->ID ) );

		$this->assertTrue( $this->check_new_comment() );
	}

	/**
	 * Tests that the `comment_flood_filter` filter decides whether the comment is a flood.
	 *
	 * @ticket 66494
	 */
	public function test_should_use_the_comment_flood_filter() {
		$last = $this->create_comment_posted_ago( 3, array( 'comment_author_IP' => self::IP ) );

		$args = array();
		add_filter(
			'comment_flood_filter',
			static function ( $is_flood, $time_lastcomment, $time_newcomment ) use ( &$args ) {
				$args = array( $is_flood, $time_lastcomment, $time_newcomment );

				return false;
			},
			100,
			3
		);

		$this->assertFalse( $this->check_new_comment() );
		$this->assertTrue( $args[0], 'The filter should receive the decision of the default callback, which blocks a comment 3 seconds after the last one.' );
		$this->assertSame( (int) mysql2date( 'U', get_comment( $last )->comment_date_gmt, false ), $args[1] );
		$this->assertEqualsWithDelta( time(), $args[2], 2 );
	}

	/**
	 * Tests that the `comment_flood_trigger` action fires when a comment is a flood.
	 *
	 * @ticket 66494
	 */
	public function test_should_fire_comment_flood_trigger_action_for_a_flood() {
		$this->create_comment_posted_ago( 3, array( 'comment_author_IP' => self::IP ) );

		$action = new MockAction();
		add_action( 'comment_flood_trigger', array( $action, 'action' ), 10, 2 );

		$this->assertTrue( $this->check_new_comment() );
		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * Tests that the `comment_flood_trigger` action does not fire when a comment is allowed.
	 *
	 * @ticket 66494
	 */
	public function test_should_not_fire_comment_flood_trigger_action_without_a_flood() {
		$this->create_comment_posted_ago( 30, array( 'comment_author_IP' => self::IP ) );

		$action = new MockAction();
		add_action( 'comment_flood_trigger', array( $action, 'action' ), 10, 2 );

		$this->assertFalse( $this->check_new_comment() );
		$this->assertSame( 0, $action->get_call_count() );
	}

	/**
	 * Tests that the request ends with a 429 response when a flood is found and dying is not avoided.
	 *
	 * @ticket 66494
	 */
	public function test_should_die_with_a_429_response_for_a_flood() {
		$this->create_comment_posted_ago( 3, array( 'comment_author_IP' => self::IP ) );

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'You are posting comments too quickly. Slow down.' );
		$this->expectExceptionCode( 429 );

		$this->check_new_comment( false, false );
	}

	/**
	 * Tests that the message of the flood can be changed with the `comment_flood_message` filter.
	 *
	 * @ticket 66494
	 */
	public function test_should_use_the_comment_flood_message_filter() {
		$this->create_comment_posted_ago( 3, array( 'comment_author_IP' => self::IP ) );

		add_filter(
			'comment_flood_message',
			static function () {
				return 'Please wait a moment.';
			}
		);

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'Please wait a moment.' );

		$this->check_new_comment( false, false );
	}

	/**
	 * Tests that the function is the callback of the `wp_is_comment_flood` filter that wp_allow_comment() uses.
	 *
	 * @ticket 66494
	 */
	public function test_should_be_used_by_wp_allow_comment() {
		$this->create_comment_posted_ago( 3, array( 'comment_author_IP' => self::IP ) );

		$result = wp_allow_comment(
			array(
				'comment_post_ID'      => self::$post_id,
				'comment_author'       => 'Visitor',
				'comment_author_email' => self::EMAIL,
				'comment_author_url'   => '',
				'comment_content'      => 'A comment posted too quickly.',
				'comment_author_IP'    => self::IP,
				'comment_parent'       => 0,
				'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s' ),
				'comment_agent'        => 'Tests/1.0',
				'comment_type'         => 'comment',
			),
			true
		);

		$this->assertWPError( $result );
		$this->assertSame( 'comment_flood', $result->get_error_code() );
	}
}
