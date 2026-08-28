<?php
/**
 * Tests for the personal data cleanup request functions introduced in #44498.
 *
 * Covers:
 *  - wp_schedule_personal_data_cleanup_requests()
 *  - wp_privacy_personal_data_cleanup_requests()
 *  - _wp_personal_data_cleanup_requests() (including the post_modified timezone fix)
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 7.1.0
 *
 * @group privacy
 * @group cron
 * @covers ::wp_schedule_personal_data_cleanup_requests
 * @covers ::wp_privacy_personal_data_cleanup_requests
 * @covers ::_wp_personal_data_cleanup_requests
 */
class Tests_Privacy_WpPersonalDataCleanupRequests extends WP_UnitTestCase {

	/**
	 * Monotonic counter to produce a unique email address per test.
	 *
	 * @var int
	 */
	private static int $email_counter = 0;

	/**
	 * Load privacy-tools.php (not auto-loaded outside admin) and reset cron.
	 */
	public function set_up(): void {
		parent::set_up();
		_set_cron_array( array() );
		require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Return a fresh, unique email address for each call.
	 *
	 * @return string
	 */
	private function unique_email(): string {
		return 'privacy-test-' . ( ++self::$email_counter ) . '@example.com';
	}

	/**
	 * Overwrite the post_modified (local) and post_modified_gmt (UTC) columns
	 * for a post so it appears to have been created $seconds ago.
	 *
	 * @global wpdb $wpdb WordPress database abstraction.
	 *
	 * @param int $post_id      The post to backdate.
	 * @param int $seconds      How far back to move the timestamps.
	 * @param int $gmt_offset_h Site GMT offset in hours, used to derive local time.
	 */
	private function backdate_request( int $post_id, int $seconds, int $gmt_offset_h = 0 ): void {
		global $wpdb;

		$utc_timestamp   = time() - $seconds;
		$local_timestamp = $utc_timestamp + $gmt_offset_h * HOUR_IN_SECONDS;

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => gmdate( 'Y-m-d H:i:s', $local_timestamp ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $utc_timestamp ),
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );
	}

	/**
	 * Return all scheduled cron entries for the given hook name.
	 *
	 * @param string $hook Action hook.
	 * @return list<array{ schedule: string|false, args: array<mixed>, interval?: non-negative-int }>
	 */
	private function get_cron_events( string $hook ): array {
		$events = array();
		foreach ( _get_cron_array() as $hooks ) {
			if ( isset( $hooks[ $hook ] ) ) {
				foreach ( $hooks[ $hook ] as $event ) {
					$events[] = $event;
				}
			}
		}
		return $events;
	}

	// =========================================================================
	// wp_schedule_personal_data_cleanup_requests()
	// =========================================================================

	/**
	 * Should register a cron event when none exists.
	 *
	 * @ticket 44498
	 */
	public function test_schedule_registers_cron_event_when_not_already_scheduled(): void {
		$this->assertFalse(
			wp_next_scheduled( 'wp_privacy_personal_data_cleanup_requests' ),
			'No event should be scheduled before the function runs.'
		);

		wp_schedule_personal_data_cleanup_requests();

		$this->assertIsInt(
			wp_next_scheduled( 'wp_privacy_personal_data_cleanup_requests' ),
			'An event should be scheduled after the function runs.'
		);
	}

	/**
	 * The cron event should use the daily recurrence.
	 *
	 * @ticket 44498
	 */
	public function test_schedule_uses_daily_recurrence(): void {
		wp_schedule_personal_data_cleanup_requests();

		$this->assertSame(
			'daily',
			wp_get_schedule( 'wp_privacy_personal_data_cleanup_requests' ),
			'The cron event should recur daily.'
		);
	}

	/**
	 * Calling the function twice must not create a duplicate cron event.
	 *
	 * @ticket 44498
	 */
	public function test_schedule_does_not_create_duplicate_event(): void {
		wp_schedule_personal_data_cleanup_requests();
		$first_timestamp = wp_next_scheduled( 'wp_privacy_personal_data_cleanup_requests' );

		wp_schedule_personal_data_cleanup_requests();

		$this->assertSame(
			$first_timestamp,
			wp_next_scheduled( 'wp_privacy_personal_data_cleanup_requests' ),
			'The scheduled timestamp should be unchanged on a second call.'
		);

		$this->assertCount(
			1,
			$this->get_cron_events( 'wp_privacy_personal_data_cleanup_requests' ),
			'Only one cron event entry should exist.'
		);
	}

	/**
	 * Should return early and schedule nothing during WordPress installation.
	 *
	 * @ticket 44498
	 */
	public function test_schedule_does_nothing_during_installation(): void {
		$prior = wp_installing();
		wp_installing( true );

		wp_schedule_personal_data_cleanup_requests();

		wp_installing( $prior );

		$this->assertFalse(
			wp_next_scheduled( 'wp_privacy_personal_data_cleanup_requests' ),
			'No event should be scheduled while WordPress is being installed.'
		);
	}

	// =========================================================================
	// _wp_personal_data_cleanup_requests()
	// =========================================================================

	/**
	 * Expired request-pending posts should be changed to request-failed.
	 *
	 * @ticket 44498
	 */
	public function test_expired_pending_request_is_marked_failed(): void {
		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );

		// Push the modification timestamp two days into the past.
		$this->backdate_request( $id, 2 * DAY_IN_SECONDS );

		$this->assertSame( 'request-pending', get_post_status( $id ) );

		_wp_personal_data_cleanup_requests();

		$this->assertSame(
			'request-failed',
			get_post_status( $id ),
			'An expired request-pending post should be set to request-failed.'
		);
	}

	/**
	 * Failing an expired request should also clear its confirmation key.
	 *
	 * The hashed confirmation key is stored in the post_password column by
	 * wp_generate_user_request_key(), and the cleanup routine blanks it when
	 * marking a request as failed.
	 *
	 * @ticket 44498
	 */
	public function test_expired_request_confirmation_key_is_cleared(): void {
		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );

		// Sends the confirmation email, generating and storing the hashed key.
		$this->assertTrue( wp_send_user_request( $id ) );
		$this->assertNotSame(
			'',
			get_post_field( 'post_password', $id ),
			'Sending the request should store a hashed confirmation key in post_password.'
		);

		// Backdate after sending, since wp_send_user_request() bumps post_modified.
		$this->backdate_request( $id, 2 * DAY_IN_SECONDS );

		_wp_personal_data_cleanup_requests();

		$this->assertSame( 'request-failed', get_post_status( $id ) );
		$this->assertSame(
			'',
			get_post_field( 'post_password', $id ),
			'Failing an expired request should clear the stored confirmation key.'
		);
	}

	/**
	 * Multiple expired requests should all be cleaned up in a single pass.
	 *
	 * @ticket 44498
	 */
	public function test_multiple_expired_requests_are_all_marked_failed(): void {
		$ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
			$this->assertIsInt( $id );
			$this->backdate_request( $id, 2 * DAY_IN_SECONDS );
			$ids[] = $id;
		}

		_wp_personal_data_cleanup_requests();

		foreach ( $ids as $id ) {
			$this->assertSame(
				'request-failed',
				get_post_status( $id ),
				"Request {$id} should be marked request-failed."
			);
		}
	}

	/**
	 * A request whose modification time is within the expiry window should
	 * remain request-pending.
	 *
	 * @ticket 44498
	 */
	public function test_unexpired_pending_request_remains_pending(): void {
		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );

		_wp_personal_data_cleanup_requests();

		$this->assertSame(
			'request-pending',
			get_post_status( $id ),
			'A freshly-created request should stay request-pending.'
		);
	}

	/**
	 * Only request-pending posts should be targeted; request-confirmed posts
	 * must not be affected even when they are older than the expiry threshold.
	 *
	 * @ticket 44498
	 */
	public function test_confirmed_requests_are_not_affected(): void {
		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data', array(), 'confirmed' );
		$this->assertIsInt( $id );
		$this->backdate_request( $id, 2 * DAY_IN_SECONDS );

		_wp_personal_data_cleanup_requests();

		$this->assertSame(
			'request-confirmed',
			get_post_status( $id ),
			'request-confirmed posts should be left untouched.'
		);
	}

	/**
	 * The expiry duration is configurable via the user_request_key_expiration filter.
	 * A request that is 2 days old should remain pending when the filter extends
	 * the expiry to 3 days.
	 *
	 * @ticket 44498
	 */
	public function test_expiry_duration_is_filterable(): void {
		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );
		// 2 days old — past the 1-day default but within a 3-day window.
		$this->backdate_request( $id, 2 * DAY_IN_SECONDS );

		add_filter(
			'user_request_key_expiration',
			static fn () => 3 * DAY_IN_SECONDS
		);
		_wp_personal_data_cleanup_requests();

		$this->assertSame(
			'request-pending',
			get_post_status( $id ),
			'With a 3-day expiry, a 2-day-old request should remain request-pending.'
		);
	}

	/**
	 * Timezone fix: a request-pending post that is only 20 hours old must not
	 * be marked failed on a UTC+5 site.
	 *
	 * Before the fix, the date_query used 'post_modified_gmt' (a UTC column) but
	 * WP_Date_Query::build_mysql_datetime() resolved the relative 'before' string
	 * (e.g. "86400 seconds ago") using the site's local timezone and returned a
	 * *local-time* string. Comparing a UTC column against a local-time threshold
	 * shifted the effective expiry window by the UTC offset — on UTC+5 sites,
	 * requests expired 5 hours too early, flagging posts that were as recent as
	 * 19 hours old as expired.
	 *
	 * The fix changes the column to 'post_modified' (local time) so both sides
	 * of the comparison are in the same timezone.
	 *
	 * @ticket 44498
	 */
	public function test_unexpired_request_not_expired_on_utcplus_site(): void {
		$offset_hours = 5; // UTC+5
		update_option( 'gmt_offset', $offset_hours );
		update_option( 'timezone_string', '' );

		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );

		/*
		 * Backdate to 20 hours ago. The default expiry is DAY_IN_SECONDS (24 h),
		 * so 20 h is safely within the window.
		 *
		 * With the old buggy code, the UTC post_modified_gmt value was compared
		 * against a local-time threshold that was shifted 5 hours forward, making
		 * the effective expiry only 19 h — which would incorrectly mark this post
		 * as failed.
		 */
		$this->backdate_request( $id, 20 * HOUR_IN_SECONDS, $offset_hours );

		_wp_personal_data_cleanup_requests();

		$this->assertSame(
			'request-pending',
			get_post_status( $id ),
			'A 20-hour-old request should not be expired on a UTC+5 site.'
		);
	}

	/**
	 * Symmetric check for UTC- sites: a request just over the expiry threshold
	 * must be correctly marked as failed even on a UTC- site.
	 *
	 * On UTC- sites, the old buggy code shifted the effective expiry window in
	 * the opposite direction — requests would linger longer than intended before
	 * being cleaned up. With the fix, the threshold is always applied consistently.
	 *
	 * @ticket 44498
	 */
	public function test_expired_request_is_marked_failed_on_utcminus_site(): void {
		$offset_hours = -5; // UTC-5
		update_option( 'gmt_offset', $offset_hours );
		update_option( 'timezone_string', '' );

		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );

		// Backdate to 25 hours ago — clearly past the 24-hour expiry window.
		$this->backdate_request( $id, 25 * HOUR_IN_SECONDS, $offset_hours );

		_wp_personal_data_cleanup_requests();

		$this->assertSame(
			'request-failed',
			get_post_status( $id ),
			'A 25-hour-old request should be expired on a UTC-5 site.'
		);
	}

	// =========================================================================
	// wp_privacy_personal_data_cleanup_requests() (cron callback)
	// =========================================================================

	/**
	 * The cron callback should invoke the underlying cleanup routine and move
	 * expired requests to request-failed.
	 *
	 * @ticket 44498
	 */
	public function test_cron_callback_cleans_up_expired_requests(): void {
		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );
		$this->backdate_request( $id, 2 * DAY_IN_SECONDS );

		wp_privacy_personal_data_cleanup_requests();

		$this->assertSame(
			'request-failed',
			get_post_status( $id ),
			'The cron callback should delegate to _wp_personal_data_cleanup_requests().'
		);
	}

	/**
	 * The cron callback must not affect unexpired requests.
	 *
	 * @ticket 44498
	 */
	public function test_cron_callback_does_not_affect_unexpired_requests(): void {
		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );

		wp_privacy_personal_data_cleanup_requests();

		$this->assertSame(
			'request-pending',
			get_post_status( $id ),
			'The cron callback should leave unexpired requests alone.'
		);
	}

	// =========================================================================
	// Default hook wiring (default-filters.php)
	// =========================================================================

	/**
	 * The scheduler and the cron callback should be registered by default.
	 *
	 * @ticket 44498
	 */
	public function test_default_hooks_are_registered(): void {
		$this->assertSame(
			10,
			has_action( 'init', 'wp_schedule_personal_data_cleanup_requests' ),
			'The scheduler should be hooked to init.'
		);

		$this->assertSame(
			10,
			has_action( 'wp_privacy_personal_data_cleanup_requests', 'wp_privacy_personal_data_cleanup_requests' ),
			'The cleanup callback should be hooked to the cron action.'
		);
	}

	/**
	 * Firing the cron action, as WP-Cron does, should run the cleanup end to end
	 * via the callback registered in default-filters.php.
	 *
	 * @ticket 44498
	 */
	public function test_cron_action_triggers_cleanup(): void {
		$id = wp_create_user_request( $this->unique_email(), 'export_personal_data' );
		$this->assertIsInt( $id );
		$this->backdate_request( $id, 2 * DAY_IN_SECONDS );

		do_action( 'wp_privacy_personal_data_cleanup_requests' );

		$this->assertSame(
			'request-failed',
			get_post_status( $id ),
			'Firing the cron action should mark the expired request as failed.'
		);
	}
}
