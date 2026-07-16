<?php
/**
 * Test cases for the `wp_schedule_delete_old_privacy_export_files()` function.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 4.9.6
 *
 * @group functions
 * @group privacy
 * @covers ::wp_schedule_delete_old_privacy_export_files
 */
class Tests_Functions_wpScheduleDeleteOldPrivacyExportFiles extends WP_UnitTestCase {

	/**
	 * Ensures each test starts without the event scheduled.
	 */
	public function set_up() {
		parent::set_up();

		wp_unschedule_event( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'wp_privacy_delete_old_export_files' );
	}

	/**
	 * Resets the installing state that some tests set.
	 */
	public function tear_down() {
		wp_installing( false );

		parent::tear_down();
	}

	/**
	 * Tests that the event is scheduled when it is not already.
	 *
	 * @ticket 59707
	 */
	public function test_wp_schedule_delete_old_privacy_export_files() {
		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'The event should not be scheduled before the function runs.' );

		wp_schedule_delete_old_privacy_export_files();

		$this->assertIsInt( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'The event should be scheduled after the function runs.' );
	}

	/**
	 * Tests that no event is scheduled while WordPress is installing.
	 *
	 * @ticket 59707
	 */
	public function test_wp_schedule_delete_old_privacy_export_files_is_installing() {
		wp_installing( true );

		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'The event should not be scheduled before the function runs.' );

		wp_schedule_delete_old_privacy_export_files();

		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'The event should not be scheduled while WordPress is installing.' );
	}

	/**
	 * Tests that calling the function again does not create a duplicate event.
	 *
	 * @ticket 59707
	 */
	public function test_wp_schedule_delete_old_privacy_export_files_already_scheduled() {
		wp_schedule_delete_old_privacy_export_files();
		$first = wp_next_scheduled( 'wp_privacy_delete_old_export_files' );

		wp_schedule_delete_old_privacy_export_files();

		$this->assertSame( $first, wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'Calling the function again should not reschedule the event.' );
	}
}
