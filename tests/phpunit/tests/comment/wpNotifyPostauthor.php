<?php

/**
 * Tests for the post author notification email.
 *
 * @group comment
 * @group notes
 *
 * @covers ::wp_notify_postauthor
 */
class Tests_Comment_WpNotifyPostauthor extends WP_UnitTestCase {

	/**
	 * Post the comments and notes are attached to.
	 */
	private static WP_Post $post;

	/**
	 * Author of the post, who receives the notification.
	 */
	private static WP_User $post_author;

	/**
	 * A user who writes comments and notes on the post.
	 */
	private static WP_User $commenter;

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
	 * Sets up shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_author = $factory->user->create_and_get( array( 'role' => 'editor' ) );
		self::$commenter   = $factory->user->create_and_get( array( 'role' => 'editor' ) );

		self::$post = $factory->post->create_and_get( array( 'post_author' => self::$post_author->ID ) );
	}

	public function set_up() {
		parent::set_up();
		$this->sent = array();
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
		$this->sent[] = array(
			'to'      => (array) $atts['to'],
			'subject' => $atts['subject'],
			'message' => $atts['message'],
		);

		return true;
	}

	/**
	 * Inserts a comment on the shared post, written by the commenter.
	 *
	 * @param string $content Comment content, as stored.
	 * @param string $type    Optional. Comment type. Default 'comment'.
	 * @return int The comment ID.
	 */
	private function insert_comment( string $content, string $type = 'comment' ): int {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => self::$post->ID,
				'comment_type'         => $type,
				'comment_content'      => $content,
				'user_id'              => self::$commenter->ID,
				'comment_author'       => self::$commenter->display_name,
				'comment_author_email' => self::$commenter->user_email,
			)
		);
		assert( is_int( $comment_id ) );
		return $comment_id;
	}

	/**
	 * Note content is stored as HTML, and an @mention is a span around the name. The
	 * email is plain text, so the post author should read the name, not the markup.
	 */
	public function test_note_email_drops_the_markup_around_a_mention() {
		$note_id = $this->insert_comment(
			'Hi <span class="wp-note-mention user-7">@Reviewer</span>, please check the intro.',
			'note'
		);

		$this->assertTrue( wp_notify_postauthor( $note_id ) );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( array( self::$post_author->user_email ), $this->sent[0]['to'] );
		$this->assertStringContainsString( "Note: \r\nHi @Reviewer, please check the intro.", $this->sent[0]['message'] );
		$this->assertStringNotContainsString( '<span', $this->sent[0]['message'] );
	}

	/**
	 * Text the author typed as an escaped tag is text, and is not read as a tag and dropped.
	 */
	public function test_note_email_keeps_escaped_text() {
		$note_id = $this->insert_comment( 'Rename &lt;code&gt; to &lt;kbd&gt; here.', 'note' );

		wp_notify_postauthor( $note_id );

		$this->assertCount( 1, $this->sent );
		$this->assertStringContainsString( 'Rename <code> to <kbd> here.', $this->sent[0]['message'] );
	}

	/**
	 * The content of a regular comment is placed in the email as it always was.
	 */
	public function test_comment_email_leaves_the_content_as_is() {
		$comment_id = $this->insert_comment( 'A <strong>bold</strong> <a href="https://example.com/">claim</a>.' );

		wp_notify_postauthor( $comment_id );

		$this->assertCount( 1, $this->sent );
		$this->assertStringContainsString( 'A <strong>bold</strong> <a href="https://example.com/">claim</a>.', $this->sent[0]['message'] );
	}
}
