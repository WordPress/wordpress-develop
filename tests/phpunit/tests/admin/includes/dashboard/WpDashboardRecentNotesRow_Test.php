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
	 * An administrator, who can edit the post the notes belong to.
	 *
	 * @var int
	 */
	public static $admin_id;

	/**
	 * The post the notes belong to.
	 *
	 * @var int
	 */
	public static $post_id;

	/**
	 * Creates the user and post shared by the tests.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
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

		// Notes are only shown to users who can edit the post they belong to.
		wp_set_current_user( self::$admin_id );
	}

	/**
	 * Creates an open note on the shared post.
	 *
	 * @param string $date Optional. Local date for the note, in MySQL format.
	 *                     Default is the current local time.
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
				'comment_content'  => 'A note.',
				// A note is created on hold, and is approved once it is resolved.
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
	 * @param WP_Comment $note       The note to render.
	 * @param int|null   $open_notes Optional. Open note count. Default null, to
	 *                               call the function without the argument.
	 * @return string The rendered row.
	 */
	private function render( $note, $open_notes = null ) {
		ob_start();

		if ( null === $open_notes ) {
			_wp_dashboard_recent_notes_row( $note );
		} else {
			_wp_dashboard_recent_notes_row( $note, $open_notes );
		}

		return ob_get_clean();
	}

	/**
	 * Returns the relative time a rendered row starts with.
	 *
	 * @param string $output The rendered row.
	 * @return string The contents of the date element.
	 */
	private function get_rendered_date( $output ) {
		preg_match( '#<li><span>([^<]*)</span>#', $output, $matches );

		$this->assertNotEmpty( $matches, 'The row did not render a date.' );

		return $matches[1];
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_link_to_the_post_edit_screen() {
		$output = $this->render( $this->create_note() );

		$this->assertStringContainsString(
			'href="' . esc_url( get_edit_post_link( self::$post_id ) ) . '"',
			$output,
			'The row did not link to the edit screen of the post.'
		);
	}

	/**
	 * A post can be deleted between the moment its notes are queried and the
	 * moment the row is rendered.
	 *
	 * @ticket 65890
	 */
	public function test_should_render_nothing_for_a_post_without_an_edit_link() {
		$note = $this->create_note();

		wp_delete_post( self::$post_id, true );

		$this->assertSame(
			'',
			$this->render( $note ),
			'A note whose post has no edit link rendered a row.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_use_the_post_title_as_the_link_text() {
		$output = $this->render( $this->create_note() );

		$this->assertStringContainsString(
			'>A post with notes</a>',
			$output,
			'The link text was not the post title.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_label_the_link_with_the_post_title() {
		$output = $this->render( $this->create_note() );

		$this->assertStringContainsString(
			'aria-label="Edit &#8220;A post with notes&#8221;"',
			$output,
			'The link was not labelled with the post title.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_use_the_placeholder_title_for_an_untitled_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '',
				'post_status' => 'draft',
				'post_author' => self::$admin_id,
			)
		);

		$note_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => '0',
			)
		);

		$output = $this->render( get_comment( $note_id ) );

		$this->assertStringContainsString(
			'>(no title)</a>',
			$output,
			'An untitled post did not fall back to the placeholder title.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_escape_the_post_title() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => '<script>alert(1)</script>',
				'post_status' => 'draft',
				'post_author' => self::$admin_id,
			)
		);

		$note_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => 'note',
				'comment_approved' => '0',
			)
		);

		$output = $this->render( get_comment( $note_id ) );

		$this->assertStringNotContainsString(
			'<script>',
			$output,
			'The post title was not escaped.'
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_default_to_a_single_open_note() {
		$output = $this->render( $this->create_note() );

		$this->assertStringContainsString(
			'<span class="open-notes-count">1 open note</span>',
			$output,
			'The row did not default to a single open note.'
		);
	}

	/**
	 * @ticket 65890
	 *
	 * @dataProvider data_open_note_counts
	 *
	 * @param int    $open_notes The number of open notes.
	 * @param string $expected   The expected count text.
	 */
	public function test_should_render_the_open_note_count( $open_notes, $expected ) {
		$output = $this->render( $this->create_note(), $open_notes );

		$this->assertStringContainsString(
			'<span class="open-notes-count">' . $expected . '</span>',
			$output,
			'The open note count was not rendered as expected.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_open_note_counts() {
		return array(
			'a single note'    => array( 1, '1 open note' ),
			'two notes'        => array( 2, '2 open notes' ),
			'no notes'         => array( 0, '0 open notes' ),
			'a thousand notes' => array( 1000, '1,000 open notes' ),
		);
	}

	/**
	 * @ticket 65890
	 *
	 * @dataProvider data_note_ages
	 *
	 * @param string $modifier A relative date modifier, as accepted by strtotime().
	 * @param string $expected The expected relative time.
	 */
	public function test_should_render_the_age_of_the_note( $modifier, $expected ) {
		$date = current_datetime()->modify( $modifier )->format( 'Y-m-d H:i:s' );

		$output = $this->render( $this->create_note( $date ) );

		$this->assertSame(
			$expected,
			$this->get_rendered_date( $output ),
			'The age of the note was not rendered as expected.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_note_ages() {
		return array(
			'minutes ago' => array( '-5 minutes', '5 minutes ago' ),
			'hours ago'   => array( '-3 hours', '3 hours ago' ),
			'days ago'    => array( '-2 days', '2 days ago' ),
			'weeks ago'   => array( '-3 weeks', '3 weeks ago' ),
			'a year ago'  => array( '-1 year', '1 year ago' ),
		);
	}

	/**
	 * @ticket 65890
	 */
	public function test_should_render_the_age_of_a_new_note_in_seconds() {
		$output = $this->render( $this->create_note() );

		$this->assertMatchesRegularExpression(
			'/^\d+ seconds? ago$/',
			$this->get_rendered_date( $output ),
			'A note added just now was not rendered as seconds ago.'
		);
	}

	/**
	 * The relative time is computed from the GMT date of the note, so it does
	 * not change with the timezone of the site.
	 *
	 * @ticket 65890
	 *
	 * @dataProvider data_timezones_away_from_utc
	 *
	 * @param string $timezone The timezone of the site.
	 */
	public function test_should_not_change_with_the_timezone_of_the_site( $timezone ) {
		update_option( 'timezone_string', $timezone );

		$date = current_datetime()->modify( '-3 hours' )->format( 'Y-m-d H:i:s' );

		$note = $this->create_note( $date );

		$this->assertSame(
			'3 hours ago',
			$this->get_rendered_date( $this->render( $note ) ),
			'The age of the note changed with the timezone of the site.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_timezones_away_from_utc() {
		return array(
			// 14 hours ahead of UTC.
			'ahead of UTC' => array( 'Pacific/Kiritimati' ),
			// 11 hours behind UTC.
			'behind UTC'   => array( 'Pacific/Niue' ),
		);
	}
}
