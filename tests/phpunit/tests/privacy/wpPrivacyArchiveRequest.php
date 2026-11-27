<?php
/**
 * Test cases for the `_wp_privacy_archive_request()` and `_wp_privacy_unarchive_request()` functions.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.3.0
 *
 * @group privacy
 * @covers ::_wp_privacy_archive_request
 * @covers ::_wp_privacy_unarchive_request
 */
class Tests_Privacy_wpPrivacyArchiveRequest extends WP_UnitTestCase {

	/**
	 * Request ID.
	 *
	 * @var int
	 */
	protected $request_id;

	public function set_up() {
		parent::set_up();

		$this->request_id = wp_create_user_request( 'requester@example.com', 'remove_personal_data' );
	}

	/**
	 * @ticket 44222
	 */
	public function test_archive_should_return_error_for_invalid_request_id() {
		$actual = _wp_privacy_archive_request( 0 );
		$this->assertWPError( $actual );
		$this->assertSame( 'privacy_request_error', $actual->get_error_code() );

		$actual = _wp_privacy_archive_request( PHP_INT_MAX );
		$this->assertWPError( $actual );
		$this->assertSame( 'privacy_request_error', $actual->get_error_code() );
	}

	/**
	 * Requests still in progress must not be archived.
	 *
	 * @ticket 44222
	 *
	 * @dataProvider data_unarchivable_statuses
	 *
	 * @param string $status Request status.
	 */
	public function test_archive_should_return_error_for_requests_in_progress( $status ) {
		wp_update_post(
			array(
				'ID'          => $this->request_id,
				'post_status' => $status,
			)
		);

		$actual = _wp_privacy_archive_request( $this->request_id );

		$this->assertWPError( $actual );
		$this->assertSame( 'privacy_request_error', $actual->get_error_code() );
		$this->assertSame( $status, get_post_status( $this->request_id ) );
		$this->assertEmpty( get_post_meta( $this->request_id, '_wp_user_request_archived_timestamp', true ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_unarchivable_statuses() {
		return array(
			'pending'   => array( 'request-pending' ),
			'confirmed' => array( 'request-confirmed' ),
		);
	}

	/**
	 * @ticket 44222
	 *
	 * @dataProvider data_archivable_statuses
	 *
	 * @param string $status Request status.
	 */
	public function test_archive_should_mark_request_archived_and_log_timestamp( $status ) {
		wp_update_post(
			array(
				'ID'          => $this->request_id,
				'post_status' => $status,
			)
		);

		$this->assertSame( $this->request_id, _wp_privacy_archive_request( $this->request_id ) );
		$this->assertSame( 'request-archived', get_post_status( $this->request_id ) );
		$this->assertSame( $status, get_post_meta( $this->request_id, '_wp_user_request_pre_archive_status', true ) );

		$request = wp_get_user_request( $this->request_id );
		$this->assertGreaterThan( 0, $request->archived_timestamp );
	}

	/**
	 * @ticket 44222
	 *
	 * @dataProvider data_archivable_statuses
	 *
	 * @param string $status Request status.
	 */
	public function test_unarchive_should_restore_previous_status_and_clear_meta( $status ) {
		wp_update_post(
			array(
				'ID'          => $this->request_id,
				'post_status' => $status,
			)
		);
		_wp_privacy_archive_request( $this->request_id );

		$this->assertSame( $this->request_id, _wp_privacy_unarchive_request( $this->request_id ) );
		$this->assertSame( $status, get_post_status( $this->request_id ) );
		$this->assertSame( '', get_post_meta( $this->request_id, '_wp_user_request_pre_archive_status', true ) );
		$this->assertSame( 0, wp_get_user_request( $this->request_id )->archived_timestamp );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_archivable_statuses() {
		return array(
			'completed' => array( 'request-completed' ),
			'failed'    => array( 'request-failed' ),
		);
	}

	/**
	 * Archiving must keep the completion data intact.
	 *
	 * @ticket 44222
	 */
	public function test_archive_should_keep_completed_timestamp() {
		_wp_privacy_completed_request( $this->request_id );
		$completed = wp_get_user_request( $this->request_id )->completed_timestamp;

		_wp_privacy_archive_request( $this->request_id );

		$this->assertSame( $completed, wp_get_user_request( $this->request_id )->completed_timestamp );
	}

	/**
	 * @ticket 44222
	 */
	public function test_unarchive_should_return_error_for_request_that_is_not_archived() {
		_wp_privacy_completed_request( $this->request_id );

		$actual = _wp_privacy_unarchive_request( $this->request_id );

		$this->assertWPError( $actual );
		$this->assertSame( 'privacy_request_error', $actual->get_error_code() );
		$this->assertSame( 'request-completed', get_post_status( $this->request_id ) );
	}

	/**
	 * Requests archived without a recorded previous status are restored as completed.
	 *
	 * @ticket 44222
	 */
	public function test_unarchive_should_fall_back_to_completed_status() {
		wp_update_post(
			array(
				'ID'          => $this->request_id,
				'post_status' => 'request-archived',
			)
		);

		$this->assertSame( $this->request_id, _wp_privacy_unarchive_request( $this->request_id ) );
		$this->assertSame( 'request-completed', get_post_status( $this->request_id ) );
	}

	/**
	 * Archived requests are excluded from `post_status => any` queries, which the "All" view uses.
	 *
	 * @ticket 44222
	 */
	public function test_archived_requests_should_be_excluded_from_any_status_queries() {
		_wp_privacy_completed_request( $this->request_id );
		_wp_privacy_archive_request( $this->request_id );

		$query_args = array(
			'post_type'   => 'user_request',
			'fields'      => 'ids',
			'post_status' => 'any',
		);

		$this->assertNotContains( $this->request_id, get_posts( $query_args ) );

		$query_args['post_status'] = 'request-archived';
		$this->assertSame( array( $this->request_id ), get_posts( $query_args ) );
	}

	/**
	 * An archived request must not block a new request for the same email and action.
	 *
	 * @ticket 44222
	 */
	public function test_archived_request_should_not_block_new_request() {
		_wp_privacy_completed_request( $this->request_id );
		_wp_privacy_archive_request( $this->request_id );

		$new_request_id = wp_create_user_request( 'requester@example.com', 'remove_personal_data' );

		$this->assertIsInt( $new_request_id );
		$this->assertNotSame( $this->request_id, $new_request_id );
	}

	/**
	 * @ticket 44222
	 *
	 * @covers ::_wp_privacy_statuses
	 */
	public function test_archived_status_is_registered() {
		$this->assertArrayHasKey( 'request-archived', _wp_privacy_statuses() );

		$status = get_post_status_object( 'request-archived' );
		$this->assertInstanceOf( 'stdClass', $status );
		$this->assertTrue( $status->exclude_from_search );
	}
}
