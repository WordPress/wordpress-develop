<?php

/**
 * @group admin
 * @group dashboard
 * @group notes
 *
 * @covers ::_wp_dashboard_recent_notes_row
 */
class Admin_Includes_Dashboard_WpDashboardRecentNotesRow_Test extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	public static $admin_id;

	/**
	 * @var int
	 */
	public static $post_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		self::$post_id = $factory->post->create(
			array(
				'post_title'  => 'A post with notes',
				'post_status' => 'draft',
				'post_author' => self::$admin_id,
			)
		);
	}

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Creates an open note on the shared post.
	 *
	 * @param string $date Optional. Local date, in MySQL format. Default is the current local time.
	 * @return WP_Comment The note.
	 */
	private function create_note( $date = '' ) {
		if ( '' === $date ) {
			$date = current_time( 'mysql' );
		}

		$note_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_type'     => 'note',
				'comment_approved' => '0',
				'comment_date'     => $date,
				'comment_date_gmt' => get_gmt_from_date( $date ),
			)
		);

		return get_comment( $note_id );
	}

	/**
	 * Renders a row and returns its markup.
	 *
	 * @param mixed ...$args Arguments passed to _wp_dashboard_recent_notes_row().
	 * @return string The rendered row.
	 */
	private function render( ...$args ) {
		ob_start();
		_wp_dashboard_recent_notes_row( ...$args );
		return ob_get_clean();
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_render_a_row_for_the_post() {
		$date   = current_datetime()->modify( '-3 hours' )->format( 'Y-m-d H:i:s' );
		$output = $this->render( $this->create_note( $date ) );

		$this->assertSame(
			sprintf(
				'<li><span>3 hours ago</span> <a href="%s" aria-label="Edit &#8220;A post with notes&#8221;">A post with notes</a> <span class="open-notes-count">1 open note</span></li>',
				esc_url( get_edit_post_link( self::$post_id ) )
			),
			$output
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_render_the_open_note_count() {
		$output = $this->render( $this->create_note(), 2 );

		$this->assertStringContainsString( '<span class="open-notes-count">2 open notes</span>', $output );
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_render_nothing_for_a_post_without_an_edit_link() {
		$note = $this->create_note();

		wp_delete_post( self::$post_id, true );

		$this->assertSame( '', $this->render( $note ) );
	}
}
