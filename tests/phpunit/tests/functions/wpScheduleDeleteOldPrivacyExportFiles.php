<?php
/**
 * @group functions
 *
 * @covers ::test_wp_schedule_delete_old_privacy_export_files
 */
class Tests_Functions_wpScheduleDeleteOldPrivacyExportFiles extends WP_UnitTestCase {

	/**
	 * Setup test
	 */
	public function set_up() {
		wp_unschedule_event( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ), 'wp_privacy_delete_old_export_files' );
	}

	/**
	 * check that a schedule is set
	 *
	 * @ticket 59707
	 */
	public function test_wp_schedule_delete_old_privacy_export_files() {

		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ) );
		wp_schedule_delete_old_privacy_export_files();
		$this->assertIsInt( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ) );
	}

	/**
	 * check that no schedule is set when in installing mode
	 *
	 * @ticket 59707
	 */
	public function test_wp_schedule_delete_old_privacy_export_files_is_installing() {
		// set to installing mode
		wp_installing( true );

		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ) );
		wp_schedule_delete_old_privacy_export_files();
		$this->assertFalse( wp_next_scheduled( 'wp_privacy_delete_old_export_files' ) );
		// Remove installing mode
		wp_installing( false );
	}
}
