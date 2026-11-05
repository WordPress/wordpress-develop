<?php

/**
 * Tests for wp_transition_comment_status().
 *
 * @group comment
 *
 * @covers ::wp_transition_comment_status
 */
class Tests_Comment_WpTransitionCommentStatus extends WP_UnitTestCase {

	/**
	 * Comment that is used in the transitions.
	 *
	 * @var WP_Comment
	 */
	protected static $comment;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$comment = get_comment(
			$factory->comment->create(
				array(
					'comment_post_ID'  => $factory->post->create(),
					'comment_approved' => '1',
				)
			)
		);
	}

	/**
	 * Records the calls of the actions of a transition.
	 *
	 * @param array $calls Reference to the list of the calls.
	 */
	private function record_calls( array &$calls ) {
		$comment = self::$comment;

		add_action(
			'transition_comment_status',
			static function ( $new_status, $old_status, $passed_comment ) use ( &$calls, $comment ) {
				$calls[] = array( 'transition_comment_status', $new_status, $old_status, $passed_comment === $comment );
			},
			10,
			3
		);

		foreach ( array( 'unapproved', 'approved', 'spam', 'trash' ) as $old_status ) {
			foreach ( array( 'unapproved', 'approved', 'spam', 'trash' ) as $new_status ) {
				add_action(
					"comment_{$old_status}_to_{$new_status}",
					static function ( $passed_comment ) use ( &$calls, $comment, $old_status, $new_status ) {
						$calls[] = array( "comment_{$old_status}_to_{$new_status}", $passed_comment === $comment );
					}
				);
			}

			add_action(
				"comment_{$old_status}_comment",
				static function ( $comment_id, $passed_comment ) use ( &$calls, $comment, $old_status ) {
					$calls[] = array( "comment_{$old_status}_comment", $comment_id, $passed_comment === $comment );
				},
				10,
				2
			);
		}
	}

	/**
	 * Tests that the actions fire, in order, when the status changes.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_status_changes
	 *
	 * @param string|int $new_status      New status.
	 * @param string|int $old_status      Old status.
	 * @param string     $expected_new    Status that the actions use for the new status.
	 * @param string     $expected_old    Status that the actions use for the old status.
	 */
	public function test_should_fire_the_actions_of_a_status_change( $new_status, $old_status, $expected_new, $expected_old ) {
		$calls = array();
		$this->record_calls( $calls );

		wp_transition_comment_status( $new_status, $old_status, self::$comment );

		$this->assertSame(
			array(
				array( 'transition_comment_status', $expected_new, $expected_old, true ),
				array( "comment_{$expected_old}_to_{$expected_new}", true ),
				array( "comment_{$expected_new}_comment", self::$comment->comment_ID, true ),
			),
			$calls
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_status_changes() {
		return array(
			'approved from unapproved'       => array( 'approved', 'unapproved', 'approved', 'unapproved' ),
			'unapproved from approved'       => array( 'unapproved', 'approved', 'unapproved', 'approved' ),
			'spam from approved'             => array( 'spam', 'approved', 'spam', 'approved' ),
			'trash from spam'                => array( 'trash', 'spam', 'trash', 'spam' ),
			'hold is unapproved'             => array( 'hold', 'approved', 'unapproved', 'approved' ),
			'approve is approved'            => array( 'approve', 'unapproved', 'approved', 'unapproved' ),
			'0 is unapproved'                => array( 0, 'approved', 'unapproved', 'approved' ),
			'1 is approved'                  => array( 1, 'unapproved', 'approved', 'unapproved' ),
			'old status hold is unapproved'  => array( 'approved', 'hold', 'approved', 'unapproved' ),
			'old status approve is approved' => array( 'spam', 'approve', 'spam', 'approved' ),
			'old status 0 is unapproved'     => array( 'approved', 0, 'approved', 'unapproved' ),
			'old status 1 is approved'       => array( 'trash', 1, 'trash', 'approved' ),
		);
	}

	/**
	 * Tests that only the action of the new status fires when the status does not change.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_unchanged_statuses
	 *
	 * @param string|int $new_status   New status.
	 * @param string|int $old_status   Old status.
	 * @param string     $expected_new Status that the action of the new status uses.
	 */
	public function test_should_only_fire_the_action_of_the_status_when_it_does_not_change( $new_status, $old_status, $expected_new ) {
		$calls = array();
		$this->record_calls( $calls );

		wp_transition_comment_status( $new_status, $old_status, self::$comment );

		$this->assertSame(
			array(
				array( "comment_{$expected_new}_comment", self::$comment->comment_ID, true ),
			),
			$calls
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unchanged_statuses() {
		return array(
			'approved'             => array( 'approved', 'approved', 'approved' ),
			'unapproved'           => array( 'unapproved', 'unapproved', 'unapproved' ),
			'spam'                 => array( 'spam', 'spam', 'spam' ),
			'approve and approved' => array( 'approve', 'approved', 'approved' ),
			'1 and approved'       => array( 1, 'approved', 'approved' ),
			'hold and unapproved'  => array( 'hold', 'unapproved', 'unapproved' ),
			'0 and hold'           => array( 0, 'hold', 'unapproved' ),
		);
	}

	/**
	 * Tests that the action of the new status includes the type of the comment.
	 *
	 * @ticket 66494
	 *
	 * @dataProvider data_comment_types
	 *
	 * @param string $type Comment type.
	 */
	public function test_should_include_the_comment_type_in_the_status_action( $type ) {
		$comment               = clone self::$comment;
		$comment->comment_type = $type;

		$ids = array();
		add_action(
			"comment_approved_{$type}",
			static function ( $comment_id ) use ( &$ids ) {
				$ids[] = $comment_id;
			}
		);

		wp_transition_comment_status( 'approved', 'unapproved', $comment );

		$this->assertSame( array( $comment->comment_ID ), $ids );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_comment_types() {
		return array(
			'comment'   => array( 'comment' ),
			'pingback'  => array( 'pingback' ),
			'trackback' => array( 'trackback' ),
			'custom'    => array( 'review' ),
		);
	}
}
