<?php

/**
 * @group comment
 *
 * @covers ::get_comment_statuses
 * @covers ::wp_get_comment_status
 * @covers ::wp_set_comment_status
 * @covers ::wp_count_comments
 * @covers ::_wp_get_custom_comment_statuses
 */
class Tests_Comment_Statuses extends WP_UnitTestCase {

	public function tear_down() {
		remove_filter( 'comment_statuses', array( $this, 'filter_comment_statuses' ) );
		remove_filter( 'comment_statuses', array( $this, 'filter_invalid_comment_statuses' ) );
		remove_filter( 'comment_statuses', array( $this, 'filter_reserved_comment_statuses' ) );

		parent::tear_down();
	}

	/**
	 * Adds a valid custom comment status.
	 *
	 * @ticket 20977
	 *
	 * @param string[] $statuses Comment statuses.
	 * @return string[] Filtered comment statuses.
	 */
	public function filter_comment_statuses( $statuses ) {
		$statuses['read'] = 'Read';

		return $statuses;
	}

	/**
	 * Adds invalid custom comment statuses.
	 *
	 * @ticket 20977
	 *
	 * @param string[] $statuses Comment statuses.
	 * @return string[] Filtered comment statuses.
	 */
	public function filter_invalid_comment_statuses( $statuses ) {
		$statuses['needs review']            = 'Needs Review';
		$statuses['more-than-20-chars-long'] = 'Too Long';

		return $statuses;
	}

	/**
	 * Adds reserved custom comment statuses.
	 *
	 * @ticket 20977
	 *
	 * @param string[] $statuses Comment statuses.
	 * @return string[] Filtered comment statuses.
	 */
	public function filter_reserved_comment_statuses( $statuses ) {
		$statuses['approved']  = 'Custom Approved';
		$statuses['moderated'] = 'Custom Moderated';
		$statuses['all']       = 'Custom All';
		$statuses['unspam']    = 'Custom Unspam';

		return $statuses;
	}

	/**
	 * @ticket 20977
	 */
	public function test_get_comment_statuses_is_filterable() {
		add_filter( 'comment_statuses', array( $this, 'filter_comment_statuses' ) );

		$this->assertSame( 'Read', get_comment_statuses()['read'] );
	}

	/**
	 * @ticket 20977
	 */
	public function test_wp_set_comment_status_supports_custom_statuses() {
		add_filter( 'comment_statuses', array( $this, 'filter_comment_statuses' ) );

		$comment_id = self::factory()->comment->create();

		$this->assertTrue( wp_set_comment_status( $comment_id, 'read' ) );
		$this->assertSame( 'read', get_comment( $comment_id )->comment_approved );
		$this->assertSame( 'read', wp_get_comment_status( $comment_id ) );
	}

	/**
	 * @ticket 20977
	 */
	public function test_wp_set_comment_status_rejects_unregistered_custom_statuses() {
		$comment_id = self::factory()->comment->create();

		$this->assertFalse( wp_set_comment_status( $comment_id, 'read' ) );
	}

	/**
	 * @ticket 20977
	 */
	public function test_wp_set_comment_status_rejects_invalid_custom_statuses() {
		add_filter( 'comment_statuses', array( $this, 'filter_invalid_comment_statuses' ) );

		$comment_id = self::factory()->comment->create();

		$this->assertFalse( wp_set_comment_status( $comment_id, 'needs review' ) );
		$this->assertFalse( wp_set_comment_status( $comment_id, 'more-than-20-chars-long' ) );
		$this->assertFalse( wp_set_comment_status( $comment_id, array( 'read' ) ) );
	}

	/**
	 * @ticket 20977
	 */
	public function test_wp_set_comment_status_rejects_reserved_custom_statuses() {
		add_filter( 'comment_statuses', array( $this, 'filter_reserved_comment_statuses' ) );

		$comment_id = self::factory()->comment->create();

		$this->assertFalse( wp_set_comment_status( $comment_id, 'approved' ) );
		$this->assertFalse( wp_set_comment_status( $comment_id, 'moderated' ) );
		$this->assertFalse( wp_set_comment_status( $comment_id, 'all' ) );
		$this->assertFalse( wp_set_comment_status( $comment_id, 'unspam' ) );
	}

	/**
	 * @ticket 20977
	 */
	public function test_wp_count_comments_includes_custom_status_counts() {
		add_filter( 'comment_statuses', array( $this, 'filter_comment_statuses' ) );

		self::factory()->comment->create(
			array(
				'comment_approved' => 'read',
			)
		);

		$count = wp_count_comments();

		$this->assertSame( 1, $count->read );
		$this->assertSame( 1, $count->total_comments );
		$this->assertSame( 1, $count->all );
	}

	/**
	 * @ticket 20977
	 */
	/**
	 * @ticket 20977
	 */
	public function test_get_comments_all_includes_custom_statuses() {
		add_filter( 'comment_statuses', array( $this, 'filter_comment_statuses' ) );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 'read',
			)
		);

		$comments = get_comments(
			array(
				'status' => 'all',
				'fields' => 'ids',
			)
		);

		$this->assertContains( $comment_id, $comments );
	}

	public function test_wp_count_comments_ignores_invalid_custom_statuses() {
		add_filter( 'comment_statuses', array( $this, 'filter_invalid_comment_statuses' ) );

		self::factory()->comment->create(
			array(
				'comment_approved' => 'needs review',
			)
		);

		$count = wp_count_comments();

		$this->assertFalse( property_exists( $count, 'needs review' ) );
		$this->assertFalse( property_exists( $count, 'more-than-20-chars-long' ) );
	}

	/**
	 * @ticket 20977
	 */
	public function test_wp_count_comments_ignores_reserved_custom_statuses() {
		add_filter( 'comment_statuses', array( $this, 'filter_reserved_comment_statuses' ) );

		$count = wp_count_comments();

		$this->assertFalse( property_exists( $count, 'unspam' ) );
	}

	/**
	 * @ticket 20977
	 *
	 * @dataProvider data_invalid_edit_comment_statuses
	 *
	 * @param mixed $comment_status Invalid comment status.
	 */
	public function test_edit_comment_rejects_invalid_comment_status( $comment_status ) {
		if ( ! function_exists( 'edit_comment' ) ) {
			require_once ABSPATH . 'wp-admin/includes/comment.php';
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => '0',
			)
		);

		$_POST = add_magic_quotes(
			array(
				'comment_ID'              => $comment_id,
				'comment_status'          => $comment_status,
				'newcomment_author'       => 'Test Author',
				'newcomment_author_url'   => '',
				'newcomment_author_email' => '',
				'content'                 => 'Test content',
			)
		);

		edit_comment();

		$this->assertSame( '0', get_comment( $comment_id )->comment_approved );
	}

	/**
	 * Data provider for invalid comment statuses.
	 *
	 * @ticket 20977
	 *
	 * @return array[] Invalid comment statuses.
	 */
	public function data_invalid_edit_comment_statuses() {
		return array(
			'invalid string' => array( 'invalid-status' ),
			'array'          => array( array( 'spam' ) ),
		);
	}
}
