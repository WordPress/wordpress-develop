<?php
/**
 * Tests for $_old_files in wp-admin/includes/update-core.php.
 *
 * @group admin
 * @group upgrade
 */
class Tests_Admin_Includes_Update_OldFiles extends WP_UnitTestCase {

	/**
	 * Sets up shared fixtures before running tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/update-core.php';
	}

	/**
	 * Data provider of files removed in WordPress 7.2.
	 *
	 * @return array[] Array of arguments containing removed file paths.
	 */
	public function data_removed_7_2_files() {
		return array(
			'default-editor-styles.css'             => array( 'wp-includes/css/dist/block-editor/default-editor-styles.css' ),
			'default-editor-styles.min.css'         => array( 'wp-includes/css/dist/block-editor/default-editor-styles.min.css' ),
			'default-editor-styles-rtl.css'         => array( 'wp-includes/css/dist/block-editor/default-editor-styles-rtl.css' ),
			'default-editor-styles-rtl.min.css'     => array( 'wp-includes/css/dist/block-editor/default-editor-styles-rtl.min.css' ),
			'block-library form view.js'            => array( 'wp-includes/js/dist/script-modules/block-library/form/view.js' ),
			'block-library form view.min.js'        => array( 'wp-includes/js/dist/script-modules/block-library/form/view.min.js' ),
			'block-library form view.min.asset.php' => array( 'wp-includes/js/dist/script-modules/block-library/form/view.min.asset.php' ),
			'content-types index.js'                => array( 'wp-includes/js/dist/script-modules/content-types/index.js' ),
			'content-types index.min.js'            => array( 'wp-includes/js/dist/script-modules/content-types/index.min.js' ),
			'content-types index.min.asset.php'     => array( 'wp-includes/js/dist/script-modules/content-types/index.min.asset.php' ),
			'sync.js'                               => array( 'wp-includes/js/dist/sync.js' ),
			'sync.min.js'                           => array( 'wp-includes/js/dist/sync.min.js' ),
		);
	}

	/**
	 * Tests that $_old_files is populated with old files.
	 *
	 * @ticket 66456
	 */
	public function test_old_files_is_populated_array() {
		global $_old_files;

		$this->assertIsArray( $_old_files, '$_old_files must be an array.' );
		$this->assertNotEmpty( $_old_files, '$_old_files must not be empty.' );
	}

	/**
	 * Tests that files removed in 7.2 are present in $_old_files.
	 *
	 * @ticket 66456
	 *
	 * @dataProvider data_removed_7_2_files
	 *
	 * @param string $file Relative path to removed file.
	 */
	public function test_old_files_contains_removed_7_2_files( $file ) {
		global $_old_files;

		$this->assertContains(
			$file,
			$_old_files,
			sprintf( '$_old_files must contain "%s".', $file )
		);
	}

	/**
	 * Tests that removed 7.2 files do not exist in the current core codebase.
	 *
	 * @ticket 66456
	 *
	 * @dataProvider data_removed_7_2_files
	 *
	 * @param string $file Relative path to removed file.
	 */
	public function test_removed_7_2_files_do_not_exist_in_source( $file ) {
		$this->assertFileDoesNotExist(
			ABSPATH . $file,
			sprintf( 'File "%s" is listed in $_old_files but still exists in the installation.', $file )
		);
	}
}
