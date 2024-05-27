<?php

require_once __DIR__ . '/Admin_WpUpgrader_TestCase.php';

/**
 * @group admin
 * @group upgrade
 * @covers WP_Upgrader::generic_strings()
 */
class Admin_WpUpgrader_GenericStrings_Test extends Admin_WpUpgrader_TestCase {

	/**
	 * Tests that `WP_Upgrader::init()` initializes the `$strings` property.
	 *
	 * @ticket 54245
	 *
	 * @dataProvider data_init_should_initialize_strings
	 *
	 * @param string $key The key to check.
	 */
	public function test_init_should_initialize_strings( $key ) {
		$this->assertEmpty( self::$instance->strings, '"$strings" has already been initialized' );

		self::$instance->init();

		$this->assertArrayHasKey( $key, self::$instance->strings, "The '$key' key was not created" );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_init_should_initialize_strings() {
		return self::text_array_to_dataprovider(
			array(
				'bad_request',
				'fs_unavailable',
				'fs_error',
				'fs_no_root_dir',
				'fs_no_content_dir',
				'fs_no_plugins_dir',
				'fs_no_themes_dir',
				'fs_no_folder',
				'no_package',
				'download_failed',
				'installing_package',
				'no_files',
				'folder_exists',
				'mkdir_failed',
				'incompatible_archive',
				'files_not_writable',
				'maintenance_start',
				'maintenance_end',
				'temp_backup_mkdir_failed',
				'temp_backup_move_failed',
				'temp_backup_restore_failed',
				'temp_backup_delete_failed',
			)
		);
	}
}
