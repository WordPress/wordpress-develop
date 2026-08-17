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
	 * An administrator, who can edit every post.
	 *
	 * @var int
	 */
	public static $admin_id;

	/**
	 * An author, who can only edit their own posts.
	 *
	 * @var int
	 */
	public static $author_id;

	/**
	 * Creates the users shared by the tests.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id = $factory->user->create( array( 'role' => 'author' ) );
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Creates a post.
	 *
	 * @param string $title  The post title.
	 * @param int    $author Optional. The post author. Default is the administrator.
	 * @return int The post ID.
	 */
	private function create_post( $title, $author = 0 ) {
		return self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'draft',
				'post_author' => $author ? $author : self::$admin_id,
			)
		);
	}

	/**
	 * Creates a note.
	 *
	 * @param int    $post_id  The post to add the note to.
	 * @param string $approved Optional. '0' for an open note, '1' for a resolved
	 *                         one. Default '0'.
	 * @param int    $parent_id Optional. The note this one replies to. Default 0.
	 * @param string $date     Optional. Local date, in MySQL format. Default is
	 *                         the current local time.
	 * @return int The note ID.
	 */
	private function create_note( $post_id, $approved = '0', $parent_id = 0, $date = '' ) {
		if ( '' === $date ) {
			$date = current_time( 'mysql' );
		}

		return self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_content'  => 'A note.',
				'comment_approved' => $approved,
				'comment_parent'   => $parent_id,
				'comment_date'     => $date,
				'comment_date_gmt' => get_gmt_from_date( $date ),
			)
		);
	}

	/**
	 * Renders the section and returns its markup.
	 *
	 * @param int $total_items Optional. Number of posts to display. Default 5.
	 * @return array The return value of the function, and the rendered markup.
	 */
	private function render( $total_items = 5 ) {
		ob_start();
		$returned = wp_dashboard_recent_notes( $total_items );
		$output   = ob_get_clean();

		return array( $returned, $output );
	}

	/**
	 * Returns the post titles the rendered section links to, in order.
	 *
	 * @param string $output The rendered section.
	 * @return string[] The linked post titles.
	 */
	private function get_linked_titles( $output ) {
		preg_match_all( '#<a [^>]*>([^<]*)</a>#', $output, $matches );

		return $matches[1];
	}

	/**
	 * Returns the dates the rendered section starts each row with, in order and
	 * without the trailing time.
	 *
	 * @param string $output The rendered section.
	 * @return string[] The rendered dates.
	 */
	private function get_rendered_days( $output ) {
		preg_match_all( '#<li><span>([^<]*)</span>#', $output, $matches );

		return array_map(
			static function ( $date ) {
				$parts = explode( ', ', $date, 2 );

				return $parts[0];
			},
			$matches[1]
		);
	}

	/**
	 * Returns a date that is in the current year whichever day the tests run on,
	 * so that it is rendered without a year.
	 *
	 * @param string $modifier A relative date modifier, as accepted by strtotime().
	 * @return DateTimeImmutable The date.
	 */
	private function date_in_the_current_year( $modifier ) {
		$now  = current_datetime();
		$date = $now->modify( $modifier );

		if ( $date->format( 'Y' ) !== $now->format( 'Y' ) ) {
			// Near the turn of the year, count in the other direction instead.
			$date = $now->modify( str_replace( '-', '+', $modifier ) );
		}

		return $date;
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_return_false_when_there_are_no_notes() {
		$this->create_post( 'A post without notes' );

		list( $returned, $output ) = $this->render();

		$this->assertFalse( $returned, 'The function did not return false.' );
		$this->assertSame( '', $output, 'The function rendered a section anyway.' );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_return_false_when_every_note_is_resolved() {
		$post_id = $this->create_post( 'A post with resolved notes' );
		$this->create_note( $post_id, '1' );

		list( $returned, $output ) = $this->render();

		$this->assertFalse( $returned, 'The function did not return false.' );
		$this->assertSame( '', $output, 'The function rendered a section anyway.' );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_render_one_row_per_post() {
		$post_id = $this->create_post( 'A post with several notes' );
		$this->create_note( $post_id );
		$this->create_note( $post_id );
		$this->create_note( $post_id );

		list( $returned, $output ) = $this->render();

		$this->assertTrue( $returned, 'The function did not return true.' );
		$this->assertSame(
			1,
			substr_count( $output, '<li>' ),
			'A post with several notes was not rendered as a single row.'
		);
	}

	/**
	 * The count is of the open notes of the post, not of the notes that were
	 * queried to build the section.
	 *
	 * @ticket 65890
	 */
	public function test_should_count_the_open_notes_of_the_post() {
		$post_id = $this->create_post( 'A post with several notes' );
		$this->create_note( $post_id );
		$this->create_note( $post_id );
		$this->create_note( $post_id );

		list( , $output ) = $this->render();

		$this->assertStringContainsString(
			'<span class="open-notes-count">3 open notes</span>',
			$output,
			'The open notes of the post were not counted.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_not_count_resolved_notes() {
		$post_id = $this->create_post( 'A post with a resolved note' );
		$this->create_note( $post_id );
		$this->create_note( $post_id, '1' );

		list( , $output ) = $this->render();

		$this->assertStringContainsString(
			'<span class="open-notes-count">1 open note</span>',
			$output,
			'A resolved note was counted as open.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_not_count_replies() {
		$post_id = $this->create_post( 'A post with a reply' );
		$note_id = $this->create_note( $post_id );
		$this->create_note( $post_id, '0', $note_id );

		list( , $output ) = $this->render();

		$this->assertStringContainsString(
			'<span class="open-notes-count">1 open note</span>',
			$output,
			'A reply was counted as an open note.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_not_count_regular_comments() {
		$post_id = $this->create_post( 'A post with a comment' );
		$this->create_note( $post_id );

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'A regular comment.',
				'comment_approved' => '0',
			)
		);

		list( , $output ) = $this->render();

		$this->assertStringContainsString(
			'<span class="open-notes-count">1 open note</span>',
			$output,
			'A regular comment was counted as an open note.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_count_the_notes_of_each_post_separately() {
		$first_id = $this->create_post( 'A post with one note' );
		$this->create_note( $first_id, '0', 0, '2026-01-02 10:00:00' );

		$second_id = $this->create_post( 'A post with two notes' );
		$this->create_note( $second_id, '0', 0, '2026-01-01 10:00:00' );
		$this->create_note( $second_id, '0', 0, '2026-01-01 11:00:00' );

		list( , $output ) = $this->render();

		$this->assertSame(
			1,
			substr_count( $output, '<span class="open-notes-count">1 open note</span>' ),
			'The post with one note was not counted on its own.'
		);
		$this->assertSame(
			1,
			substr_count( $output, '<span class="open-notes-count">2 open notes</span>' ),
			'The post with two notes was not counted on its own.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_order_posts_by_their_most_recent_open_note() {
		$older_id = $this->create_post( 'An older post' );
		$this->create_note( $older_id, '0', 0, '2026-01-01 10:00:00' );

		$newer_id = $this->create_post( 'A newer post' );
		$this->create_note( $newer_id, '0', 0, '2026-01-03 10:00:00' );

		list( , $output ) = $this->render();

		$this->assertSame(
			array( 'A newer post', 'An older post' ),
			$this->get_linked_titles( $output ),
			'The posts were not ordered by their most recent open note.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_limit_the_number_of_posts() {
		for ( $i = 0; $i < 4; $i++ ) {
			$post_id = $this->create_post( 'Post ' . $i );
			$this->create_note( $post_id, '0', 0, '2026-01-0' . ( $i + 1 ) . ' 10:00:00' );
		}

		list( , $output ) = $this->render( 2 );

		$this->assertSame(
			2,
			substr_count( $output, '<li>' ),
			'The section was not limited to the requested number of posts.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_exclude_posts_the_user_cannot_edit() {
		$own_id = $this->create_post( 'A post of their own', self::$author_id );
		$this->create_note( $own_id );

		$other_id = $this->create_post( 'A post of somebody else' );
		$this->create_note( $other_id );

		wp_set_current_user( self::$author_id );

		list( , $output ) = $this->render();

		$this->assertSame(
			array( 'A post of their own' ),
			$this->get_linked_titles( $output ),
			'A post the user cannot edit was listed.'
		);
	}

	/**
	 * Resolving a note approves the thread it starts, so a thread whose replies
	 * are still on hold is resolved too.
	 *
	 * @ticket 65890
	 */
	public function test_should_exclude_a_resolved_thread_with_open_replies() {
		$post_id = $this->create_post( 'A post with a resolved thread' );
		$note_id = $this->create_note( $post_id, '1' );
		$this->create_note( $post_id, '0', $note_id );

		list( $returned, $output ) = $this->render();

		$this->assertFalse( $returned, 'The function did not return false.' );
		$this->assertSame( '', $output, 'A resolved thread was listed.' );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_list_a_post_whose_only_open_thread_has_replies_once() {
		$post_id  = $this->create_post( 'A post with an open thread' );
		$resolved = $this->create_note( $post_id, '1' );
		$this->create_note( $post_id, '0', $resolved );

		$open = $this->create_note( $post_id );
		$this->create_note( $post_id, '0', $open );

		list( $returned, $output ) = $this->render();

		$this->assertTrue( $returned, 'The function did not return true.' );
		$this->assertSame(
			1,
			substr_count( $output, '<li>' ),
			'The post was not listed exactly once.'
		);
		$this->assertStringContainsString(
			'<span class="open-notes-count">1 open note</span>',
			$output,
			'Only the open thread should have been counted.'
		);
	}

	/**
	 * The date is the last time the thread was added to, which is the date of
	 * its most recent reply when it has one.
	 *
	 * @ticket 65890
	 */
	public function test_should_show_the_date_of_the_most_recent_reply() {
		$opened  = $this->date_in_the_current_year( '-13 days' );
		$replied = $opened->modify( '+10 days' );

		$post_id = $this->create_post( 'A post with a reply' );
		$note_id = $this->create_note( $post_id, '0', 0, $opened->format( 'Y-m-d H:i:s' ) );
		$this->create_note( $post_id, '0', $note_id, $replied->format( 'Y-m-d H:i:s' ) );

		list( , $output ) = $this->render();

		$this->assertSame(
			array( date_i18n( 'M jS', $replied->getTimestamp() + $replied->getOffset() ) ),
			$this->get_rendered_days( $output ),
			'The date was not the date of the most recent reply.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_order_posts_by_their_most_recent_reply() {
		$opened  = $this->date_in_the_current_year( '-13 days' );
		$replied = $opened->modify( '+10 days' );

		$replied_to_id = $this->create_post( 'A post replied to recently' );
		$note_id       = $this->create_note( $replied_to_id, '0', 0, $opened->format( 'Y-m-d H:i:s' ) );
		$this->create_note( $replied_to_id, '0', $note_id, $replied->format( 'Y-m-d H:i:s' ) );

		// Opened after the other thread started, but not added to since.
		$quiet_id = $this->create_post( 'A post left alone since' );
		$this->create_note( $quiet_id, '0', 0, $opened->modify( '+1 day' )->format( 'Y-m-d H:i:s' ) );

		list( , $output ) = $this->render();

		$this->assertSame(
			array( 'A post replied to recently', 'A post left alone since' ),
			$this->get_linked_titles( $output ),
			'The posts were not ordered by their most recent reply.'
		);
	}

	/**
	 * Notes the current user cannot see are paged past, but only up to a point,
	 * so that a site with a large number of them does not have every dashboard
	 * load walk the whole comments table.
	 *
	 * @ticket 65890
	 */
	public function test_should_stop_paging_past_notes_the_user_cannot_see() {
		$other_id = $this->create_post( 'A post of somebody else' );

		// One more note than a single row is allowed to look at.
		for ( $i = 0; $i <= 100; $i++ ) {
			$this->create_note( $other_id, '0', 0, '2026-01-02 10:00:00' );
		}

		// Older than all of them, so it is only reached by paging past them.
		$own_id = $this->create_post( 'A post of their own', self::$author_id );
		$this->create_note( $own_id, '0', 0, '2026-01-01 10:00:00' );

		wp_set_current_user( self::$author_id );

		list( $returned, $output ) = $this->render( 1 );

		$this->assertFalse( $returned, 'The function did not return false.' );
		$this->assertSame( '', $output, 'The function kept paging past the limit.' );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_page_past_notes_the_user_cannot_see() {
		$other_id = $this->create_post( 'A post of somebody else' );

		// More notes than the first page of the query holds.
		for ( $i = 0; $i < 20; $i++ ) {
			$this->create_note( $other_id, '0', 0, '2026-01-02 10:00:00' );
		}

		$own_id = $this->create_post( 'A post of their own', self::$author_id );
		$this->create_note( $own_id, '0', 0, '2026-01-01 10:00:00' );

		wp_set_current_user( self::$author_id );

		list( , $output ) = $this->render( 1 );

		$this->assertSame(
			array( 'A post of their own' ),
			$this->get_linked_titles( $output ),
			'The post was not found past the notes the user cannot see.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_return_false_when_the_user_can_edit_none_of_the_posts() {
		$post_id = $this->create_post( 'A post of somebody else' );
		$this->create_note( $post_id );

		wp_set_current_user( self::$author_id );

		list( $returned, $output ) = $this->render();

		$this->assertFalse( $returned, 'The function did not return false.' );
		$this->assertSame( '', $output, 'The function rendered a section anyway.' );
	}
}
