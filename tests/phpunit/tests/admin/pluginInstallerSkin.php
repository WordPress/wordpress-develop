<?php

/**
 * Tests the `Plugin_Installer_Skin` class.
 *
 * @group admin
 * @group upgrade
 *
 * @covers Plugin_Installer_Skin
 */
class Tests_Admin_PluginInstallerSkin extends WP_UnitTestCase {

	/**
	 * Loads the classes to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-installer-skin.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}

	/**
	 * Restores global state after each test.
	 */
	public function tear_down() {
		unset( $_GET['from'] );
		parent::tear_down();
	}

	/**
	 * Tests that after() provides a link to the plugins page when installed via upload.
	 *
	 * @ticket 66186
	 *
	 * @covers Plugin_Installer_Skin::after
	 */
	public function test_after_should_link_to_plugins_page_when_installed_via_upload() {
		$skin     = new Plugin_Installer_Skin(
			array(
				'type' => 'upload',
			)
		);
		$upgrader = new Plugin_Upgrader( $skin );
		$skin->set_upgrader( $upgrader );
		$skin->result = true;

		$captured_actions = array();
		add_filter(
			'install_plugin_complete_actions',
			static function ( $actions ) use ( &$captured_actions ) {
				$captured_actions = $actions;
				return array();
			}
		);

		$skin->after();

		$this->assertArrayHasKey( 'plugins_page', $captured_actions );
		$this->assertStringContainsString( 'plugins.php', $captured_actions['plugins_page'] );
		$this->assertStringContainsString( 'Go to Plugins page', $captured_actions['plugins_page'] );
		$this->assertStringNotContainsString( 'plugin-install.php', $captured_actions['plugins_page'] );
	}

	/**
	 * Tests that after() provides a link to the plugin installer when installed from the web.
	 *
	 * @ticket 66186
	 *
	 * @covers Plugin_Installer_Skin::after
	 */
	public function test_after_should_link_to_plugin_installer_when_installed_from_web() {
		$skin     = new Plugin_Installer_Skin(
			array(
				'type' => 'web',
			)
		);
		$upgrader = new Plugin_Upgrader( $skin );
		$skin->set_upgrader( $upgrader );
		$skin->result = true;

		$captured_actions = array();
		add_filter(
			'install_plugin_complete_actions',
			static function ( $actions ) use ( &$captured_actions ) {
				$captured_actions = $actions;
				return array();
			}
		);

		$skin->after();

		$this->assertArrayHasKey( 'plugins_page', $captured_actions );
		$this->assertStringContainsString( 'plugin-install.php', $captured_actions['plugins_page'] );
		$this->assertStringContainsString( 'Go to Plugin Installer', $captured_actions['plugins_page'] );
	}

	/**
	 * Tests that after() provides an importers page link when from is import.
	 *
	 * @covers Plugin_Installer_Skin::after
	 */
	public function test_after_should_link_to_importers_page_when_from_is_import() {
		$_GET['from'] = 'import';

		$skin     = new Plugin_Installer_Skin(
			array(
				'type' => 'upload',
			)
		);
		$upgrader = new Plugin_Upgrader( $skin );
		$skin->set_upgrader( $upgrader );
		$skin->result = true;

		$captured_actions = array();
		add_filter(
			'install_plugin_complete_actions',
			static function ( $actions ) use ( &$captured_actions ) {
				$captured_actions = $actions;
				return array();
			}
		);

		$skin->after();

		$this->assertArrayHasKey( 'importers_page', $captured_actions );
		$this->assertStringContainsString( 'import.php', $captured_actions['importers_page'] );
		$this->assertStringContainsString( 'Go to Importers', $captured_actions['importers_page'] );
	}

	/**
	 * Tests hide_process_failed() for folder_exists error during upload without overwrite.
	 *
	 * @covers Plugin_Installer_Skin::hide_process_failed
	 */
	public function test_hide_process_failed_should_return_true_on_folder_exists_for_upload() {
		$skin = new Plugin_Installer_Skin(
			array(
				'type'      => 'upload',
				'overwrite' => '',
			)
		);

		$error = new WP_Error( 'folder_exists', 'Folder exists.' );
		$this->assertTrue( $skin->hide_process_failed( $error ) );

		$other_error = new WP_Error( 'other_error', 'Other error.' );
		$this->assertFalse( $skin->hide_process_failed( $other_error ) );

		$skin_web = new Plugin_Installer_Skin(
			array(
				'type'      => 'web',
				'overwrite' => '',
			)
		);
		$this->assertFalse( $skin_web->hide_process_failed( $error ) );
	}
}
