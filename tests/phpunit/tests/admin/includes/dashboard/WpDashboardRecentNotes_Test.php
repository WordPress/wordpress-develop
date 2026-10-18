<?php

/**
 * @group admin
 * @group dashboard
 * @group notes
 *
 * @covers ::wp_dashboard_recent_notes
 */
class Admin_Includes_Dashboard_WpDashboardRecentNotes_Test extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	public static int $admin_id;

	/**
	 * @var int
	 */
	public static int $author_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Creates a draft post.
	 *
	 * @param string   $title  The post title.
	 * @param int|null $author Optional. The post author. Default is the administrator.
	 * @return int The post ID.
	 */
	private function create_post( string $title, ?int $author = null ): int {
		return self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'draft',
				'post_author' => $author ?? self::$admin_id,
			)
		);
	}

	/**
	 * Creates a note.
	 *
	 * @param int    $post_id   The post to add the note to.
	 * @param string $approved  Optional. '0' for an open note, '1' for a resolved one. Default '0'.
	 * @param int    $parent_id Optional. The note this one replies to. Default 0.
	 * @param string $date      Optional. Local date, in MySQL format. Default is the current local time.
	 * @return int The note ID.
	 */
	private function create_note( int $post_id, string $approved = '0', int $parent_id = 0, string $date = '' ): int {
		if ( '' === $date ) {
			$date = current_time( 'mysql' );
		}

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => $approved,
				'comment_parent'   => $parent_id,
				'comment_date'     => $date,
				'comment_date_gmt' => get_gmt_from_date( $date ),
			)
		);
	}

	/**
	 * Renders the section.
	 *
	 * @param positive-int $total_items Optional. Number of posts to display. Default 5.
	 * @return array{0: bool, 1: string} The return value of the function, and the rendered markup.
	 */
	private function render( int $total_items = 5 ): array {
		ob_start();
		$returned = wp_dashboard_recent_notes( $total_items );
		$output   = (string) ob_get_clean();

		return array( $returned, $output );
	}

	/**
	 * Returns the linked post titles in the rendered section, in order.
	 *
	 * @param string $output The rendered section.
	 * @return string[] The linked post titles.
	 */
	private function get_linked_titles( string $output ): array {
		preg_match_all( '#<a [^>]*>([^<]*)</a>#', $output, $matches );

		return $matches[1];
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_return_false_when_there_are_no_open_notes(): void {
		$post_id = $this->create_post( 'A post with a resolved note' );
		$this->create_note( $post_id, '1' );

		list( $returned, $output ) = $this->render();

		$this->assertFalse( $returned );
		$this->assertSame( '', $output );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_render_a_row_for_the_post(): void {
		$post_id = $this->create_post( 'A post with notes' );
		$this->create_note( $post_id, '0', 0, current_datetime()->modify( '-3 hours' )->format( 'Y-m-d H:i:s' ) );

		list( , $output ) = $this->render();

		$edit_post_link = get_edit_post_link( $post_id );
		$this->assertIsString( $edit_post_link, 'The post should have an edit link.' );

		$this->assertMatchesRegularExpression(
			sprintf(
				'#<li>\s*<span>3 hours ago</span>\s*<a href="%s" aria-label="Edit &\#8220;A post with notes&\#8221;">A post with notes</a>\s*<span class="open-notes-count">1 open note</span>\s*</li>#',
				preg_quote( esc_url( $edit_post_link ), '#' )
			),
			$output
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_render_one_row_per_post_with_its_open_note_count(): void {
		$older_id = $this->create_post( 'An older post' );
		$this->create_note( $older_id, '0', 0, '2026-01-01 10:00:00' );
		$this->create_note( $older_id, '0', 0, '2026-01-01 11:00:00' );

		$newer_id = $this->create_post( 'A newer post' );
		$note_id  = $this->create_note( $newer_id, '0', 0, '2026-01-02 10:00:00' );

		// None of these are counted as open notes.
		$this->create_note( $newer_id, '1' );
		$this->create_note( $newer_id, '0', $note_id );
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $newer_id,
				'comment_approved' => '0',
			)
		);

		list( $returned, $output ) = $this->render();

		$this->assertTrue( $returned );
		$this->assertSame( array( 'A newer post', 'An older post' ), $this->get_linked_titles( $output ) );
		$this->assertMatchesRegularExpression( '#A newer post</a>\s*<span class="open-notes-count">1 open note</span>#', $output );
		$this->assertMatchesRegularExpression( '#An older post</a>\s*<span class="open-notes-count">2 open notes</span>#', $output );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_limit_the_number_of_posts(): void {
		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_note( $this->create_post( 'Post ' . $i ) );
		}

		list( , $output ) = $this->render( 2 );

		$this->assertSame( 2, substr_count( $output, '<li>' ) );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_exclude_posts_the_user_cannot_edit(): void {
		$this->create_note( $this->create_post( 'A post of their own', self::$author_id ) );
		$this->create_note( $this->create_post( 'A post of somebody else' ) );

		wp_set_current_user( self::$author_id );

		list( , $output ) = $this->render();

		$this->assertSame( array( 'A post of their own' ), $this->get_linked_titles( $output ) );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_skip_posts_without_an_edit_link(): void {
		$this->create_note( $this->create_post( 'A post with an edit link' ), '0', 0, '2026-01-01 10:00:00' );

		$unlinked_id = $this->create_post( 'A post without an edit link' );
		$this->create_note( $unlinked_id, '0', 0, '2026-01-02 10:00:00' );

		add_filter(
			'get_edit_post_link',
			static function ( string $link, int $post_id ) use ( $unlinked_id ) {
				return $unlinked_id === $post_id ? '' : $link;
			},
			10,
			2
		);

		list( , $output ) = $this->render( 1 );

		$this->assertSame( array( 'A post with an edit link' ), $this->get_linked_titles( $output ) );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_exclude_open_replies_to_a_resolved_thread(): void {
		$post_id = $this->create_post( 'A post with a resolved thread' );
		$note_id = $this->create_note( $post_id, '1' );
		$this->create_note( $post_id, '0', $note_id );

		list( $returned ) = $this->render();

		$this->assertFalse( $returned );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_order_posts_by_their_most_recent_reply(): void {
		$replied_to_id = $this->create_post( 'A post replied to recently' );
		$note_id       = $this->create_note( $replied_to_id, '0', 0, '2026-01-01 10:00:00' );
		$this->create_note( $replied_to_id, '0', $note_id, '2026-01-03 10:00:00' );

		$quiet_id = $this->create_post( 'A post left alone since' );
		$this->create_note( $quiet_id, '0', 0, '2026-01-02 10:00:00' );

		list( , $output ) = $this->render();

		$this->assertSame(
			array( 'A post replied to recently', 'A post left alone since' ),
			$this->get_linked_titles( $output )
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_page_past_notes_the_user_cannot_see(): void {
		$other_id = $this->create_post( 'A post of somebody else' );

		// More notes than the first page of the query holds.
		for ( $i = 0; $i < 6; $i++ ) {
			$this->create_note( $other_id, '0', 0, '2026-01-02 10:00:00' );
		}

		$this->create_note( $this->create_post( 'A post of their own', self::$author_id ), '0', 0, '2026-01-01 10:00:00' );

		wp_set_current_user( self::$author_id );

		list( , $output ) = $this->render( 1 );

		$this->assertSame( array( 'A post of their own' ), $this->get_linked_titles( $output ) );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_stop_paging_after_a_hundred_notes_per_row(): void {
		$other_id = $this->create_post( 'A post of somebody else' );

		for ( $i = 0; $i < 100; $i++ ) {
			$this->create_note( $other_id, '0', 0, '2026-01-02 10:00:00' );
		}

		$this->create_note( $this->create_post( 'A post of their own', self::$author_id ), '0', 0, '2026-01-01 10:00:00' );

		wp_set_current_user( self::$author_id );

		list( $returned ) = $this->render( 1 );

		$this->assertFalse( $returned );
	}
}
