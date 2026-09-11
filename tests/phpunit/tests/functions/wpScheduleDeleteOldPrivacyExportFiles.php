<?php

/**
 * Tests for the wp_schedule_delete_old_privacy_export_files() function.
 *
 * @group functions
 *
 * @covers ::wp_schedule_delete_old_privacy_export_files
 */
class Tests_Functions_wpScheduleDeleteOldPrivacyExportFiles extends WP_UnitTestCase {

	/**
	 * Setup test
	 */
	public function set_up() {
		parent::set_up();
		wp_clear_scheduled_hook( 'wp_privacy_delete_old_export_files' );
	}

	public function tear_down() {
		wp_clear_scheduled_hook( 'wp_privacy_delete_old_export_files' );

		parent::tear_down();
	}

	/**
	 * check that a schedule is set
	 *
	 * @ticket 59707
	 */
	public function test_wp_schedule_delete_old_privacy_export_files() {

		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'no export should be scheduled' );
		wp_schedule_delete_old_privacy_export_files();
		$this->assertIsInt( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'export should be scheduled' );
	}

	/**
	 * check that no schedule is set when WP is in installing mode
	 *
	 * @ticket 59707
	 */
	public function test_wp_schedule_delete_old_privacy_export_files_is_installing() {
		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'no export should be scheduled' );

		// set to installing mode
		$prior = wp_installing();
		wp_installing( true );

		wp_schedule_delete_old_privacy_export_files();

		wp_installing( $prior );

		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'no export should be scheduled while installing' );
	}

	/**
	 * Check that calling the function when already scheduled does not create a duplicate.
	 *
	 * @ticket 59707
	 */
	public function test_wp_schedule_delete_old_privacy_export_files_already_scheduled() {
		// Schedule ahead of time() so that a duplicate would land on a different timestamp.
		wp_schedule_event( strtotime( '+1 hour' ), 'hourly', 'wp_privacy_delete_old_export_files' );

		// Take a snapshot of the cron option while the event is scheduled.
		$expected = _get_cron_array();

		// The event is already scheduled, so this call should be a no-op.
		wp_schedule_delete_old_privacy_export_files();

		// Check cron option is unchanged.
		$this->assertSame( $expected, _get_cron_array(), 'the event should not be scheduled again' );
	}
}
