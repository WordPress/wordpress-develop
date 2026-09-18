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
	 */
	private static WP_Post $post;

	/**
	 * Author of the post (notified by the post-author path, not the mention path).
	 */
	private static WP_User $post_author;

	/**
	 * A user who writes notes.
	 */
	private static WP_User $commenter;

	/**
	 * A user who gets mentioned.
	 */
	private static WP_User $mentioned;

	/**
	 * Captured wp_mail() calls for the current test.
	 *
	 * @var list<array{
	 *     to: list<non-falsy-string>,
	 *     subject: string,
	 *     message: string,
	 *     headers: list<string>,
	 * }>
	 */
	private array $sent = array();

	/**
	 * Captured wp_mail() recipients for the current test.
	 *
	 * @var list<non-falsy-string>
	 */
	private array $sent_to = array();

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
	 *
	 * @phpstan-param array{
	 *     to: non-falsy-string|list<non-falsy-string>,
	 *     subject: string,
	 *     message: string,
	 *     headers: string|list<string>,
	 *     ...
	 * } $atts
	 * @phpstan-return true
	 */
	public function capture_mail( $short_circuit, array $atts ): bool {
		$to = (array) $atts['to'];

		$this->sent[] = array(
			'to'      => $to,
			'subject' => $atts['subject'],
			'message' => $atts['message'],
			'headers' => (array) $atts['headers'],
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
	private function insert_note( string $content, int $user_id, int $parent_id = 0 ): WP_Comment {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID' => self::$post->ID,
				'comment_type'    => 'note',
				'comment_content' => $content,
				'comment_parent'  => $parent_id,
				'user_id'         => $user_id,
			)
		);
		assert( $comment instanceof WP_Comment );
		return $comment;
	}

	/**
	 * Builds the stored markup for a mention of the given user.
	 *
	 * @param int    $user_id User ID to mention.
	 * @param string $label   Optional. The mention's visible text.
	 * @return string The mention chip markup.
	 */
	private function get_mention_markup( int $user_id, string $label = '@Mentioned' ): string {
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
		$edit_link = get_edit_post_link( self::$post->ID, 'url' );
		$this->assertIsString( $edit_link );
		$this->assertStringContainsString(
			$edit_link,
			$email['message']
		);
	}

	/**
	 * The editor link is composed for the recipient, not for whoever happens to
	 * be current, so it survives contexts with no logged-in user such as WP-Cron.
	 *
	 * @ticket 65639
	 *
	 * @covers ::wp_send_note_notification
	 */
	public function test_editor_link_is_built_for_the_recipient() {
		wp_set_current_user( 0 );

		$note = $this->insert_note(
			$this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertCount( 1, $this->sent );

		// The switch is temporary; the caller's context is left as it was found.
		$this->assertSame( 0, get_current_user_id() );

		wp_set_current_user( self::$mentioned->ID );
		$edit_link = get_edit_post_link( self::$post->ID, 'url' );
		$this->assertIsString( $edit_link );
		$this->assertStringContainsString( $edit_link, $this->sent[0]['message'] );
	}

	/**
	 * @ticket 65639
	 *
	 * @covers ::wp_send_note_notification
	 */
	public function test_email_is_sent_as_plain_text() {
		$note = $this->insert_note(
			$this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertCount( 1, $this->sent );
		$this->assertStringContainsString( 'Content-Type: text/plain', implode( "\n", $this->sent[0]['headers'] ) );
	}

	/**
	 * The post title is escaped on the way into the database, so it is decoded
	 * exactly once for the plain text email. Decoding twice resolves entities
	 * the author meant to be read literally.
	 *
	 * @ticket 65639
	 *
	 * @covers ::wp_send_note_notification
	 */
	public function test_email_subject_decodes_the_post_title_once() {
		// Stored form of the literal title "Tom &amp; Jerry".
		add_filter(
			'the_title',
			static function () {
				return 'Tom &amp;amp; Jerry';
			}
		);

		$note = $this->insert_note(
			$this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertCount( 1, $this->sent );
		$this->assertStringContainsString( 'Tom &amp; Jerry', $this->sent[0]['subject'] );
		$this->assertStringNotContainsString( 'Tom & Jerry', $this->sent[0]['subject'] );
	}

	/**
	 * Note content is stored as HTML, so markup is stripped before entities are
	 * decoded. Decoding first would turn escaped text into tags and strip it.
	 *
	 * @ticket 65639
	 *
	 * @covers ::wp_send_note_notification
	 */
	public function test_email_keeps_escaped_markup_in_the_note_text() {
		$note = $this->insert_note(
			'<p>Use &lt;code&gt; tags ' . $this->get_mention_markup( self::$mentioned->ID ) . '</p>',
			self::$commenter->ID
		);

		wp_notify_note_mentions( $note );

		$this->assertCount( 1, $this->sent );
		$this->assertStringContainsString( 'Use <code> tags', $this->sent[0]['message'] );
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
		$this->assertInstanceOf( WP_User::class, $subscriber );

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

		/*
		 * Run the create path the fixture skipped, so the mentioned user is
		 * subscribed to the thread as a real create would leave them. An edit
		 * only notifies users the thread has never told about it, which is how
		 * wp_notify_new_mentions_on_note_update() tells a mention added by an
		 * edit from one that was already delivered.
		 */
		do_action( 'rest_insert_comment', $note, null, true );
		$this->sent    = array();
		$this->sent_to = array();

		wp_set_current_user( self::$commenter->ID );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/comments/' . $note->comment_ID );
		$request->set_param( 'content', 'Edited ping ' . $this->get_mention_markup( self::$mentioned->ID ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotContains( self::$mentioned->user_email, $this->sent_to );
	}
}
