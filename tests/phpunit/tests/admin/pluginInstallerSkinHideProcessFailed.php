<?php

/**
 * Tests for Plugin_Installer_Skin::hide_process_failed().
 *
 * @group admin
 * @group plugins
 * @group upgrade
 *
 * @covers Plugin_Installer_Skin::hide_process_failed
 */
class Tests_Admin_PluginInstallerSkinHideProcessFailed extends WP_UnitTestCase {

	/**
	 * Loads the upgrader classes.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Tests whether the "process failed" error is hidden.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_errors
	 *
	 * @param string $type       The skin type.
	 * @param string $overwrite  The overwrite argument.
	 * @param string $error_code The error code.
	 * @param bool   $expected   Whether the error is expected to be hidden.
	 */
	public function test_should_hide_folder_exists_error_for_first_upload( $type, $overwrite, $error_code, $expected ) {
		$skin = new Plugin_Installer_Skin(
			array(
				'type'      => $type,
				'overwrite' => $overwrite,
			)
		);

		$this->assertSame( $expected, $skin->hide_process_failed( new WP_Error( $error_code, 'Error.' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_errors() {
		return array(
			'upload, existing folder'       => array( 'upload', '', 'folder_exists', true ),
			'upload, other error'           => array( 'upload', '', 'incompatible_archive', false ),
			'upload replacing the plugin'   => array( 'upload', 'update-plugin', 'folder_exists', false ),
			'upload downgrading the plugin' => array( 'upload', 'downgrade-plugin', 'folder_exists', false ),
			'install from the directory'    => array( 'web', '', 'folder_exists', false ),
		);
	}

	/**
	 * Tests that the default skin arguments hide nothing.
	 *
	 * @ticket 65819
	 */
	public function test_should_not_hide_error_with_default_arguments() {
		$skin = new Plugin_Installer_Skin();

		$this->assertFalse( $skin->hide_process_failed( new WP_Error( 'folder_exists', 'Error.' ) ) );
	}
}
