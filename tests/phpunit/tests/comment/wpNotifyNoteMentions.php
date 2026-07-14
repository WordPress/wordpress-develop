<?php

/**
 * Tests for note mention and follower notifications.
 *
 * @group comment
 * @group notes
 */
class Tests_Comment_WpNotifyNoteMentions extends WP_UnitTestCase {

	/**
	 * Post the notes are attached to.
	 *
	 * @var WP_Post
	 */
	private static $post;

	/**
	 * Author of the post (notified by the post-author path, not the mention path).
	 *
	 * @var WP_User
	 */
	private static $post_author;

	/**
	 * A user who writes notes.
	 *
	 * @var WP_User
	 */
	private static $commenter;

	/**
	 * A user who gets mentioned.
	 *
	 * @var WP_User
	 */
	private static $mentioned;

	/**
	 * Captured wp_mail() recipients for the current test.
	 *
	 * @var string[]
	 */
	private $sent_to = array();

	/**
	 * Sets up shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_author = $factory->user->create_and_get( array( 'role' => 'editor' ) );
		self::$commenter   = $factory->user->create_and_get( array( 'role' => 'editor' ) );
		self::$mentioned   = $factory->user->create_and_get( array( 'role' => 'editor' ) );

		self::$post = $factory->post->create_and_get( array( 'post_author' => self::$post_author->ID ) );
	}

	public function set_up() {
		parent::set_up();
		$this->sent_to = array();
		// Short-circuit wp_mail() and record who would have been emailed.
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	/**
	 * Records wp_mail() recipients and short-circuits delivery.
	 *
	 * @param null  $short_circuit Short-circuit value.
	 * @param array $atts          wp_mail() arguments.
	 * @return bool Always true to indicate a "sent" message.
	 */
	public function capture_mail( $short_circuit, $atts ) {
		foreach ( (array) $atts['to'] as $to ) {
			$this->sent_to[] = $to;
		}
		return true;
	}

	/**
	 * Builds a note comment for the shared post.
	 *
	 * @param string $content   Note content.
	 * @param int    $user_id   Author user ID.
	 * @param int    $parent_id Parent note ID (0 for a top-level note).
	 * @return WP_Comment The inserted note.
	 */
	private function insert_note( $content, $user_id, $parent_id = 0 ) {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => self::$post->ID,
				'comment_type'    => 'note',
				'comment_content' => $content,
				'comment_parent'  => $parent_id,
				'user_id'         => $user_id,
			)
		);

		return get_comment( $comment_id );
	}

	/**
	 * Builds the stored markup for a mention of the given user.
	 *
	 * @param int    $user_id User ID to mention.
	 * @param string $label   Optional. The mention's visible text.
	 * @return string The mention anchor markup.
	 */
	private function get_mention_markup( $user_id, $label = '@Mentioned' ) {
		return sprintf( '<a class="wp-note-mention user-%d" href="#">%s</a>', $user_id, $label );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_get_note_mentioned_user_ids
	 */
	public function test_parses_mentioned_user_ids(): void {
		$content = '<p>Hi <a class="wp-note-mention user-5" href="#">@Jane</a> and '
			. '<a class="wp-note-mention user-9" href="#">@Bob</a>.</p>';

		$this->assertSame( array( 5, 9 ), wp_get_note_mentioned_user_ids( $content ) );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_get_note_mentioned_user_ids
	 */
	public function test_ignores_plain_links_and_deduplicates(): void {
		$content = '<p><a class="user-7" href="https://example.com">not a mention</a> '
			. '<a class="wp-note-mention user-5" href="#">@Jane</a> '
			. '<a class="wp-note-mention user-5" href="#">@Jane again</a> '
			. '<a class="wp-note-mention" href="#">no user class</a></p>';

		$this->assertSame( array( 5 ), wp_get_note_mentioned_user_ids( $content ) );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_get_note_thread_root_id
	 */
	public function test_thread_root_is_parent_for_replies(): void {
		$root  = $this->insert_note( 'Top level', self::$commenter->ID );
		$reply = $this->insert_note( 'A reply', self::$commenter->ID, $root->comment_ID );

		$this->assertSame( (int) $root->comment_ID, wp_get_note_thread_root_id( $reply ) );
		$this->assertSame( (int) $root->comment_ID, wp_get_note_thread_root_id( $root ) );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_notify_note_mentions
	 * @covers ::wp_send_note_notification
	 * @covers ::wp_add_note_followers
	 * @covers ::wp_get_note_followers
	 */
	public function test_mentioned_user_is_emailed_and_subscribed(): void {
		$mention = $this->get_mention_markup( self::$mentioned->ID );
		$note    = $this->insert_note( "Ping $mention", self::$commenter->ID );

		wp_notify_note_mentions( $note );

		$this->assertContains( self::$mentioned->user_email, $this->sent_to );

		// The mentioned user and the note author both follow the thread now.
		$followers = wp_get_note_followers( $note->comment_ID );
		$this->assertContains( self::$mentioned->ID, $followers );
		$this->assertContains( self::$commenter->ID, $followers );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_notify_note_mentions
	 */
	public function test_author_is_not_notified_about_their_own_note(): void {
		$self_mention = $this->get_mention_markup( self::$commenter->ID, '@Me' );
		$note         = $this->insert_note( "Note to $self_mention", self::$commenter->ID );

		wp_notify_note_mentions( $note );

		$this->assertNotContains( self::$commenter->user_email, $this->sent_to );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_notify_note_mentions
	 */
	public function test_post_author_is_left_to_the_postauthor_notification(): void {
		$mention = $this->get_mention_markup( self::$post_author->ID, '@Author' );
		$note    = $this->insert_note( "Hey $mention", self::$commenter->ID );

		wp_notify_note_mentions( $note );

		// wp_new_comment_via_rest_notify_postauthor() notifies the post author
		// of every note; the mention path must not also email them or they
		// would receive a duplicate.
		$this->assertNotContains( self::$post_author->user_email, $this->sent_to );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_notify_note_mentions
	 * @covers ::wp_get_note_followers
	 */
	public function test_mentioned_user_without_note_access_is_not_emailed(): void {
		$subscriber_user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		$mention = $this->get_mention_markup( $subscriber_user->ID, '@Subscriber' );
		$note    = $this->insert_note( "Ping $mention", self::$commenter->ID );

		wp_notify_note_mentions( $note );

		// Notes are only readable by users who can edit them; a subscriber
		// cannot, so emailing them would leak content they cannot see.
		$this->assertNotContains( $subscriber_user->user_email, $this->sent_to );

		// They are still recorded as a follower in case their role changes.
		$this->assertContains( $subscriber_user->ID, wp_get_note_followers( $note->comment_ID ) );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_notify_note_mentions
	 * @covers ::wp_get_note_thread_root_id
	 */
	public function test_followers_are_notified_of_replies(): void {
		$mention = $this->get_mention_markup( self::$mentioned->ID );
		$root    = $this->insert_note( "Start $mention", self::$commenter->ID );
		wp_notify_note_mentions( $root );

		// A different user replies; the mentioned user follows and should be
		// notified even though they are not mentioned in the reply itself.
		$this->sent_to = array();
		$replier       = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$reply         = $this->insert_note( 'Following up', $replier->ID, $root->comment_ID );
		wp_notify_note_mentions( $reply );

		$this->assertContains( self::$mentioned->user_email, $this->sent_to );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_notify_note_mentions
	 */
	public function test_no_notifications_when_disabled(): void {
		update_option( 'wp_notes_notify', 0 );

		$mention = $this->get_mention_markup( self::$mentioned->ID );
		$note    = $this->insert_note( "Ping $mention", self::$commenter->ID );
		wp_notify_note_mentions( $note );

		$this->assertEmpty( $this->sent_to );

		update_option( 'wp_notes_notify', 1 );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_notify_note_mentions
	 */
	public function test_editing_a_note_does_not_renotify(): void {
		$mention = $this->get_mention_markup( self::$mentioned->ID );
		$note    = $this->insert_note( "Ping $mention", self::$commenter->ID );

		// Simulate the update path of rest_insert_comment ($creating false).
		wp_notify_note_mentions( $note, null, false );

		$this->assertEmpty( $this->sent_to );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_notify_note_mentions
	 */
	public function test_recipients_filter_can_add_and_remove(): void {
		$extra_user = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );

		$filter = function ( $ids ) use ( $extra_user ) {
			$ids[] = $extra_user->ID;
			return array_values( array_diff( $ids, array( self::$mentioned->ID ) ) );
		};
		add_filter( 'wp_note_notification_recipients', $filter );

		$mention = $this->get_mention_markup( self::$mentioned->ID );
		$note    = $this->insert_note( "Ping $mention", self::$commenter->ID );
		wp_notify_note_mentions( $note );

		$this->assertContains( $extra_user->user_email, $this->sent_to );
		$this->assertNotContains( self::$mentioned->user_email, $this->sent_to );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_add_note_followers
	 * @covers ::wp_remove_note_followers
	 * @covers ::wp_get_note_followers
	 */
	public function test_followers_can_be_removed(): void {
		$note = $this->insert_note( 'Top level', self::$commenter->ID );
		wp_add_note_followers( $note->comment_ID, array( self::$commenter->ID, self::$mentioned->ID ) );

		$remaining = wp_remove_note_followers( $note->comment_ID, array( self::$mentioned->ID ) );

		$this->assertSame( array( self::$commenter->ID ), $remaining );
		$this->assertSame( array( self::$commenter->ID ), wp_get_note_followers( $note->comment_ID ) );

		// Removing the last follower clears the meta entirely.
		wp_remove_note_followers( $note->comment_ID, array( self::$commenter->ID ) );
		$this->assertSame( '', get_comment_meta( $note->comment_ID, '_wp_note_followers', true ) );
	}

	/**
	 * @ticket 65622
	 *
	 * @covers ::wp_create_initial_comment_meta
	 */
	public function test_followers_meta_is_registered_for_rest(): void {
		wp_create_initial_comment_meta();

		$registered = get_registered_meta_keys( 'comment' );

		$this->assertArrayHasKey( '_wp_note_followers', $registered );
		$this->assertNotFalse( $registered['_wp_note_followers']['show_in_rest'] );
	}
}
