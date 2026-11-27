<?php

/**
 * Tests for note thread follower subscriptions and notifications.
 *
 * @group comment
 * @group notes
 *
 * @covers ::wp_notify_note_followers
 */
class Tests_Comment_WpNotifyNoteFollowers extends WP_UnitTestCase {

	/**
	 * Post the notes are attached to.
	 */
	private static WP_Post $post;

	/**
	 * Author of the post.
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
		);

		foreach ( $to as $recipient ) {
			$this->sent_to[] = $recipient;
		}

		return true;
	}

	/**
	 * Returns the captured emails sent to the given address.
	 *
	 * @param string $email Recipient address.
	 * @return array The captured emails.
	 */
	private function emails_to( string $email ): array {
		return array_values(
			array_filter(
				$this->sent,
				static function ( $mail ) use ( $email ) {
					return in_array( $email, $mail['to'], true );
				}
			)
		);
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
	 * Updates a note's content as its author, preserving mention markup.
	 *
	 * @param WP_Comment $note    The note to update.
	 * @param string     $content New note content.
	 * @return WP_Comment The updated note.
	 */
	private function update_note_content( WP_Comment $note, string $content ): WP_Comment {
		$editor_id = (int) $note->user_id;
		if ( is_multisite() ) {
			grant_super_admin( $editor_id );
		}
		$previous_user_id = get_current_user_id();
		wp_set_current_user( $editor_id );

		wp_update_comment(
			array(
				'comment_ID'      => $note->comment_ID,
				'comment_content' => $content,
			)
		);

		wp_set_current_user( $previous_user_id );
		if ( is_multisite() ) {
			revoke_super_admin( $editor_id );
		}

		$updated = get_comment( $note->comment_ID );
		assert( $updated instanceof WP_Comment );
		return $updated;
	}

	/**
	 * Fires the real `rest_insert_comment` action for a note, exercising every
	 * registered handler in its true priority order.
	 *
	 * @param WP_Comment $comment  The note.
	 * @param bool       $creating Whether to simulate a create or an update.
	 */
	private function fire_rest_insert( WP_Comment $comment, bool $creating = true ): void {
		do_action( 'rest_insert_comment', $comment, null, $creating );
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
	 * @covers ::wp_add_note_followers
	 * @covers ::wp_get_note_followers
	 * @covers ::wp_remove_note_followers
	 */
	public function test_followers_can_be_added_and_removed() {
		$note = $this->insert_note( 'Top level', self::$commenter->ID );

		wp_add_note_followers(
			(int) $note->comment_ID,
			array( self::$commenter->ID, self::$mentioned->ID )
		);

		$remaining = wp_remove_note_followers(
			(int) $note->comment_ID,
			array( self::$mentioned->ID )
		);

		$this->assertSame( array( self::$commenter->ID ), $remaining );
		$this->assertSame(
			array( self::$commenter->ID ),
			wp_get_note_followers( (int) $note->comment_ID )
		);

		// Removing the last follower clears the meta entirely.
		wp_remove_note_followers( (int) $note->comment_ID, array( self::$commenter->ID ) );
		$this->assertSame( '', get_comment_meta( $note->comment_ID, '_wp_note_followers', true ) );
	}

	/**
	 * @covers ::wp_create_initial_comment_meta
	 */
	public function test_followers_meta_is_registered_for_rest() {
		// The test case unregisters every meta key, so register them again here.
		wp_create_initial_comment_meta();

		$registered = get_registered_meta_keys( 'comment' );

		$this->assertArrayHasKey( '_wp_note_followers', $registered );
		$this->assertNotFalse( $registered['_wp_note_followers']['show_in_rest'] );
	}

	/**
	 * @covers ::wp_maintain_note_followers
	 */
	public function test_author_and_mentioned_users_follow_a_new_thread() {
		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		$this->fire_rest_insert( $note );

		$followers = wp_get_note_followers( (int) $note->comment_ID );
		$this->assertContains( self::$commenter->ID, $followers );
		$this->assertContains( self::$mentioned->ID, $followers );
	}

	/**
	 * @covers ::wp_maintain_note_followers
	 */
	public function test_replying_subscribes_the_replier_to_the_thread_root() {
		$root = $this->insert_note( 'Top level', self::$commenter->ID );
		$this->fire_rest_insert( $root );

		$replier = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$reply   = $this->insert_note( 'A reply', $replier->ID, (int) $root->comment_ID );
		$this->fire_rest_insert( $reply );

		$this->assertContains(
			$replier->ID,
			wp_get_note_followers( (int) $root->comment_ID )
		);
		// The reply itself carries no follower list; the root anchors it.
		$this->assertSame( array(), wp_get_note_followers( (int) $reply->comment_ID ) );
	}

	/**
	 * Subscription bookkeeping must run even while notifications are off, so
	 * enabling notifications later works for existing threads.
	 *
	 * @covers ::wp_maintain_note_followers
	 */
	public function test_subscriptions_are_maintained_while_notifications_are_disabled() {
		update_option( 'wp_notes_notify', 0 );

		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);
		$this->fire_rest_insert( $note );

		update_option( 'wp_notes_notify', 1 );

		$this->assertEmpty( $this->sent_to );
		$followers = wp_get_note_followers( (int) $note->comment_ID );
		$this->assertContains( self::$commenter->ID, $followers );
		$this->assertContains( self::$mentioned->ID, $followers );
	}

	/**
	 * @covers ::wp_notify_note_followers
	 */
	public function test_followers_are_notified_of_replies() {
		$root = $this->insert_note(
			'Start ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);
		$this->fire_rest_insert( $root );

		/*
		 * A different user replies; the mentioned user follows the thread and
		 * is notified even though the reply does not mention them.
		 */
		$this->sent    = array();
		$this->sent_to = array();
		$replier       = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$reply         = $this->insert_note( 'Following up', $replier->ID, (int) $root->comment_ID );
		$this->fire_rest_insert( $reply );

		$emails = $this->emails_to( self::$mentioned->user_email );
		$this->assertCount( 1, $emails );
		$this->assertStringContainsString( 'a note you follow', $emails[0]['subject'] );
		// The follower email carries the unfollow link for the thread.
		$this->assertStringContainsString(
			wp_get_note_unfollow_url( (int) $root->comment_ID, self::$mentioned->ID ),
			$emails[0]['message']
		);
	}

	/**
	 * A follower who is also mentioned in the reply gets the mention email
	 * only, never two emails about the same note.
	 *
	 * @covers ::wp_notify_note_followers
	 */
	public function test_mentioned_followers_are_not_double_notified() {
		$root = $this->insert_note(
			'Start ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);
		$this->fire_rest_insert( $root );

		$this->sent    = array();
		$this->sent_to = array();
		$replier       = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$reply         = $this->insert_note(
			'Again ' . $this->get_mention_markup( self::$mentioned->ID ),
			$replier->ID,
			(int) $root->comment_ID
		);
		$this->fire_rest_insert( $reply );

		$emails = $this->emails_to( self::$mentioned->user_email );
		$this->assertCount( 1, $emails );
		$this->assertStringContainsString( 'You were mentioned', $emails[0]['subject'] );
	}

	/**
	 * @covers ::wp_notify_new_mentions_on_note_update
	 * @covers ::wp_maintain_note_followers
	 */
	public function test_edit_that_adds_a_mention_notifies_and_subscribes_the_new_user() {
		$note = $this->insert_note( 'No mentions yet', self::$commenter->ID );
		$this->fire_rest_insert( $note );
		$this->assertEmpty( $this->emails_to( self::$mentioned->user_email ) );

		$updated = $this->update_note_content(
			$note,
			'Now ping ' . $this->get_mention_markup( self::$mentioned->ID )
		);
		$this->fire_rest_insert( $updated, false );

		$emails = $this->emails_to( self::$mentioned->user_email );
		$this->assertCount( 1, $emails );
		$this->assertStringContainsString( 'You were mentioned', $emails[0]['subject'] );
		$this->assertContains(
			self::$mentioned->ID,
			wp_get_note_followers( (int) $note->comment_ID )
		);
	}

	/**
	 * Users already following the thread are not re-notified when an edit
	 * repeats their mention.
	 *
	 * @covers ::wp_notify_new_mentions_on_note_update
	 */
	public function test_edit_does_not_renotify_existing_followers() {
		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);
		$this->fire_rest_insert( $note );

		$this->sent    = array();
		$this->sent_to = array();
		$updated       = $this->update_note_content(
			$note,
			'Edited, still ' . $this->get_mention_markup( self::$mentioned->ID )
		);
		$this->fire_rest_insert( $updated, false );

		$this->assertEmpty( $this->emails_to( self::$mentioned->user_email ) );
		$this->assertEmpty( $this->emails_to( self::$commenter->user_email ) );
	}

	/**
	 * A mentioned post author receives the mention email, and the generic
	 * post-author notification is suppressed for that note.
	 *
	 * @covers ::wp_route_post_author_mention_notification
	 */
	public function test_mentioned_post_author_gets_the_mention_email_not_the_generic_one() {
		$note = $this->insert_note(
			'Hey ' . $this->get_mention_markup( self::$post_author->ID, '@Author' ),
			self::$commenter->ID
		);

		$this->fire_rest_insert( $note );

		$emails = $this->emails_to( self::$post_author->user_email );
		$this->assertCount( 1, $emails );
		$this->assertStringContainsString( 'You were mentioned', $emails[0]['subject'] );
	}

	/**
	 * Without a mention, the generic post-author email is untouched.
	 *
	 * @covers ::wp_route_post_author_mention_notification
	 */
	public function test_unmentioned_post_author_still_gets_the_generic_email() {
		$note = $this->insert_note( 'Just a note', self::$commenter->ID );

		$this->fire_rest_insert( $note );

		$emails = $this->emails_to( self::$post_author->user_email );
		$this->assertCount( 1, $emails );
		$this->assertStringNotContainsString( 'You were mentioned', $emails[0]['subject'] );
	}

	/**
	 * @covers ::wp_add_note_unfollow_link_to_email
	 */
	public function test_mention_email_carries_the_unfollow_link() {
		$note = $this->insert_note(
			'Ping ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);

		$this->fire_rest_insert( $note );

		$emails = $this->emails_to( self::$mentioned->user_email );
		$this->assertCount( 1, $emails );
		$this->assertStringContainsString(
			wp_get_note_unfollow_url( (int) $note->comment_ID, self::$mentioned->ID ),
			$emails[0]['message']
		);
	}

	/**
	 * @covers ::wp_handle_note_unfollow
	 */
	public function test_unfollow_link_removes_the_follower() {
		$note = $this->insert_note( 'Top level', self::$commenter->ID );
		wp_add_note_followers( (int) $note->comment_ID, array( self::$mentioned->ID ) );

		$_GET['comment'] = (string) $note->comment_ID;
		$_GET['uid']     = (string) self::$mentioned->ID;
		$_GET['token']   = wp_get_note_unfollow_token( (int) $note->comment_ID, self::$mentioned->ID );

		try {
			wp_handle_note_unfollow();
			$this->fail( 'Expected wp_die() confirmation.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'no longer be notified', $e->getMessage() );
		} finally {
			unset( $_GET['comment'], $_GET['uid'], $_GET['token'] );
		}

		$this->assertNotContains(
			self::$mentioned->ID,
			wp_get_note_followers( (int) $note->comment_ID )
		);
	}

	/**
	 * @covers ::wp_handle_note_unfollow
	 */
	public function test_unfollow_link_rejects_a_bad_token() {
		$note = $this->insert_note( 'Top level', self::$commenter->ID );
		wp_add_note_followers( (int) $note->comment_ID, array( self::$mentioned->ID ) );

		$_GET['comment'] = (string) $note->comment_ID;
		$_GET['uid']     = (string) self::$mentioned->ID;
		$_GET['token']   = 'forged-token';

		try {
			wp_handle_note_unfollow();
			$this->fail( 'Expected wp_die() rejection.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'not valid', $e->getMessage() );
		} finally {
			unset( $_GET['comment'], $_GET['uid'], $_GET['token'] );
		}

		// The follower list is untouched.
		$this->assertContains(
			self::$mentioned->ID,
			wp_get_note_followers( (int) $note->comment_ID )
		);
	}

	/**
	 * A follower who cannot read the note is never emailed its content.
	 *
	 * @covers ::wp_notify_note_followers
	 */
	public function test_followers_without_note_access_are_not_emailed() {
		$subscriber = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );

		$root = $this->insert_note( 'Top level', self::$commenter->ID );
		$this->fire_rest_insert( $root );
		wp_add_note_followers( (int) $root->comment_ID, array( $subscriber->ID ) );

		$this->sent    = array();
		$this->sent_to = array();
		$replier       = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$reply         = $this->insert_note( 'Following up', $replier->ID, (int) $root->comment_ID );
		$this->fire_rest_insert( $reply );

		$this->assertNotContains( $subscriber->user_email, $this->sent_to );
	}

	/**
	 * Resolving or reopening a thread posts a system note. Announcing it as a
	 * reply would mail followers a body with nothing in it, since resolve
	 * notes carry no content.
	 *
	 * @covers ::wp_notify_note_followers
	 * @covers ::wp_get_note_status_event
	 */
	public function test_system_notes_do_not_send_the_follower_email() {
		$root = $this->insert_note(
			'Start ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);
		$this->fire_rest_insert( $root );

		$this->sent    = array();
		$this->sent_to = array();

		$resolver = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$system   = $this->insert_note( '', $resolver->ID, (int) $root->comment_ID );
		update_comment_meta( $system->comment_ID, '_wp_note_status', 'resolved' );
		$this->fire_rest_insert( $system );

		$this->assertEmpty( $this->emails_to( self::$mentioned->user_email ) );
	}

	/**
	 * The controller saves comment meta after `rest_insert_comment` fires, so
	 * a system note has to be recognized from the request that created it.
	 *
	 * @covers ::wp_get_note_status_event
	 */
	public function test_system_notes_are_recognized_before_their_meta_is_saved() {
		$root = $this->insert_note(
			'Start ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);
		$this->fire_rest_insert( $root );

		$this->sent    = array();
		$this->sent_to = array();

		$resolver = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$system   = $this->insert_note( 'Reopening this', $resolver->ID, (int) $root->comment_ID );

		$request = new WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'meta', array( '_wp_note_status' => 'reopen' ) );

		// No meta row exists yet, exactly as during a real create.
		$this->assertSame( '', get_comment_meta( $system->comment_ID, '_wp_note_status', true ) );
		$this->assertSame( 'reopen', wp_get_note_status_event( $system, $request ) );

		do_action( 'rest_insert_comment', $system, $request, true );

		$this->assertEmpty( $this->emails_to( self::$mentioned->user_email ) );
	}

	/**
	 * A regular reply is not mistaken for a thread event.
	 *
	 * @covers ::wp_get_note_status_event
	 */
	public function test_regular_notes_record_no_thread_event() {
		$note = $this->insert_note( 'Just a note', self::$commenter->ID );

		$this->assertNull( wp_get_note_status_event( $note ) );
		$this->assertNull( wp_get_note_status_event( $note, new WP_REST_Request( 'POST', '/wp/v2/comments' ) ) );
	}

	/**
	 * The sent action is what channels other than email hook, so it has to
	 * report every recipient and say why they were notified.
	 *
	 * @covers ::wp_send_note_notification
	 * @covers ::wp_send_note_follower_notification
	 */
	public function test_notification_sent_action_reports_every_recipient() {
		$fired = array();
		add_action(
			'wp_note_notification_sent',
			static function ( $user_id, $comment, $context, $sent ) use ( &$fired ) {
				$fired[] = array(
					'user_id' => $user_id,
					'context' => $context,
					'sent'    => $sent,
				);
			},
			10,
			4
		);

		$root = $this->insert_note(
			'Start ' . $this->get_mention_markup( self::$mentioned->ID ),
			self::$commenter->ID
		);
		$this->fire_rest_insert( $root );

		$this->assertSame(
			array(
				array(
					'user_id' => self::$mentioned->ID,
					'context' => 'mention',
					'sent'    => true,
				),
			),
			$fired
		);

		// The same user, now reached as a follower rather than a mention.
		$fired   = array();
		$replier = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		$reply   = $this->insert_note( 'Following up', $replier->ID, (int) $root->comment_ID );
		$this->fire_rest_insert( $reply );

		$this->assertContains(
			array(
				'user_id' => self::$mentioned->ID,
				'context' => 'follower',
				'sent'    => true,
			),
			$fired
		);
	}

	/**
	 * A mentioned post author is reported under their own context, so an
	 * integration can tell the routed mention from a plain one.
	 *
	 * @covers ::wp_route_post_author_mention_notification
	 */
	public function test_notification_sent_action_reports_the_routed_post_author_mention() {
		$contexts = array();
		add_action(
			'wp_note_notification_sent',
			static function ( $user_id, $comment, $context ) use ( &$contexts ) {
				$contexts[ $user_id ] = $context;
			},
			10,
			3
		);

		$note = $this->insert_note(
			'Hey ' . $this->get_mention_markup( self::$post_author->ID, '@Author' ),
			self::$commenter->ID
		);
		$this->fire_rest_insert( $note );

		$this->assertArrayHasKey( self::$post_author->ID, $contexts );
		$this->assertSame( 'post_author_mention', $contexts[ self::$post_author->ID ] );
	}
}
