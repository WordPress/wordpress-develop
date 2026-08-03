<?php

/**
 * Tests for note mention notifications.
 *
 * @group comment
 * @group notes
 *
 * @covers ::wp_notify_note_mentions
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
	 * Captured wp_mail() calls for the current test.
	 *
	 * @var array[]
	 */
	private $sent = array();

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
		$this->sent    = array();
		$this->sent_to = array();
		// Short-circuit wp_mail() and record what would have been sent.
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	/**
	 * Records wp_mail() calls and short-circuits delivery.
	 *
	 * @param null  $short_circuit Short-circuit value.
	 * @param array $atts          wp_mail() arguments.
	 * @return bool Always true to indicate a "sent" message.
	 */
	public function capture_mail( $short_circuit, $atts ) {
		$to = (array) $atts['to'];

		$this->sent[] = array(
			'to'      => $to,
			'subject' => (string) $atts['subject'],
			'message' => (string) $atts['message'],
		);

		foreach ( $to as $recipient ) {
			$this->sent_to[] = $recipient;
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
	 * @return string The mention chip markup.
	 */
	private function get_mention_markup( $user_id, $label = '@Mentioned' ) {
		return sprintf( '<span class="wp-note-mention user-%d">%s</span>', $user_id, $label );
	}

	/**
	 * @ticket 65639
	 *
	 * @covers ::wp_get_note_mentioned_user_ids
	 */
	public function test_parses_mentioned_user_ids() {
		$content = '<p>Hi <span class="wp-note-mention user-5">@Jane</span> and '
			. '<span class="wp-note-mention user-9">@Bob</span>.</p>';

		$this->assertSame( array( 5, 9 ), wp_get_note_mentioned_user_ids( $content ) );
	}

	/**
	 * @ticket 65639
	 *
	 * @covers ::wp_get_note_mentioned_user_ids
	 */
	public function test_ignores_non_mentions_and_deduplicates() {
		$content = '<p><span class="user-7">not a mention</span> '
			. '<a class="wp-note-mention user-7" href="#">an anchor, not a chip</a> '
			. '<span class="wp-note-mention user-5">@Jane</span> '
			. '<span class="wp-note-mention user-5">@Jane again</span> '
			. '<span class="wp-note-mention">no user class</span></p>';

		$this->assertSame( array( 5 ), wp_get_note_mentioned_user_ids( $content ) );
	}

	/**
	 * @ticket 65639
	 *
	 * @covers ::wp_send_note_notification
	 */
	public function test_mentioned_user_is_emailed() {
		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertContains( self::$mentioned->user_email, $this->sent_to );
	}

	/**
	 * @ticket 65639
	 *
	 * @covers ::wp_send_note_notification
	 */
	public function test_email_contains_context_and_editor_link() {
		/*
		 * The editor link comes from get_edit_post_link(), which is scoped to
		 * the current user; in the REST flow that is the note's author.
		 */
		wp_set_current_user( self::$commenter->ID );

		$note = $this->insert_note(
			'<p>Please review ' . $this->get_mention_markup( self::$mentioned->ID, '@Reviewer' ) . '</p>',
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertCount( 1, $this->sent );
		$email = $this->sent[0];

		$this->assertStringContainsString( 'You were mentioned in a note', $email['subject'] );
		// The note text is included, stripped of markup.
		$this->assertStringContainsString( 'Please review @Reviewer', $email['message'] );
		$this->assertStringNotContainsString( '<span', $email['message'] );
		// The email links to the post editor, as the post author's note email does.
		$this->assertStringContainsString(
			get_edit_post_link( self::$post->ID, 'url' ),
			$email['message']
		);
	}

	/**
	 * @ticket 65639
	 */
	public function test_author_is_not_notified_about_their_own_note() {
		$note = $this->insert_note(
			'Note to ' . $this->get_mention_markup( self::$commenter->ID, '@Me' ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertNotContains( self::$commenter->user_email, $this->sent_to );
	}

	/**
	 * @ticket 65639
	 */
	public function test_post_author_is_left_to_the_postauthor_notification() {
		$note = $this->insert_note(
			'Hey ' . $this->get_mention_markup( self::$post_author->ID, '@Author' ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		/*
		 * wp_new_comment_via_rest_notify_postauthor() notifies the post author
		 * of every note; the mention path must not also email them or they
		 * would receive a duplicate.
		 */
		$this->assertNotContains( self::$post_author->user_email, $this->sent_to );
	}

	/**
	 * @ticket 65639
	 */
	public function test_mentioned_user_without_note_access_is_not_emailed() {
		$subscriber = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( $subscriber->ID, '@Subscriber' ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		/*
		 * Notes are only readable by users who can edit them; a subscriber
		 * cannot, so emailing them would leak content they cannot see.
		 */
		$this->assertNotContains( $subscriber->user_email, $this->sent_to );
	}

	/**
	 * @ticket 65639
	 */
	public function test_mentioning_a_nonexistent_user_sends_nothing() {
		$note = $this->insert_note(
			'Ghost ' . $this->get_mention_markup( 999999, '@Ghost' ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertEmpty( $this->sent_to );
	}

	/**
	 * @ticket 65639
	 */
	public function test_no_notifications_when_disabled() {
		update_option( 'wp_notes_notify', 0 );

		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertEmpty( $this->sent_to );
	}

	/**
	 * @ticket 65639
	 */
	public function test_editing_a_note_does_not_renotify() {
		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		// Simulate the update path of rest_insert_comment ( $creating false ).
		wp_notify_note_mentions( $note, null, false );

		$this->assertEmpty( $this->sent_to );
	}

	/**
	 * Creating a note through the REST endpoint must trigger the mention email.
	 *
	 * This exercises the `rest_insert_comment` wiring (hook name, priority and
	 * argument count), which the direct calls above bypass.
	 *
	 * @ticket 65639
	 */
	public function test_rest_note_creation_triggers_mention_email() {
		wp_set_current_user( self::$commenter->ID );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'post', self::$post->ID );
		$request->set_param( 'type', 'note' );
		$request->set_param( 'content', 'Ping ' . $this->get_mention_markup( self::$mentioned->ID ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$this->assertContains( self::$mentioned->user_email, $this->sent_to );
	}

	/**
	 * Updating a note through the REST endpoint must not re-notify.
	 *
	 * @ticket 65639
	 */
	public function test_rest_note_update_does_not_renotify() {
		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		wp_set_current_user( self::$commenter->ID );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/comments/' . $note->comment_ID );
		$request->set_param( 'content', 'Edited ping ' . $this->get_mention_markup( self::$mentioned->ID ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotContains( self::$mentioned->user_email, $this->sent_to );
	}
}
