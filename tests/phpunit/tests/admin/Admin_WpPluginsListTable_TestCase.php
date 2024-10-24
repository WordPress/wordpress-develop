<?php

abstract class Admin_WpPluginsListTable_TestCase extends WP_UnitTestCase {
	/**
	 * @var WP_Plugins_List_Table
	 */
	public $table = false;

	/**
	 * An admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * The original value of the `$s` global.
	 *
	 * @var string|null
	 */
	protected static $original_s;

	/**
	 * @var array
	 */
	public $fake_plugin = array(
		'fake-plugin.php' => array(
			'Name'        => 'Fake Plugin',
			'PluginURI'   => 'https://wordpress.org/',
			'Version'     => '1.0.0',
			'Description' => 'A fake plugin for testing.',
			'Author'      => 'WordPress',
			'AuthorURI'   => 'https://wordpress.org/',
			'TextDomain'  => 'fake-plugin',
			'DomainPath'  => '/languages',
			'Network'     => false,
			'Title'       => 'Fake Plugin',
			'AuthorName'  => 'WordPress',
		),
	);

	/**
	 * Creates an admin user before any tests run and backs up the `$s` global.
	 */
	public static function set_up_before_class() {
		global $s;

		parent::set_up_before_class();

		self::$admin_id   = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_wp_plugins_list_table',
				'user_pass'  => 'password',
				'user_email' => 'testadmin@test.com',
			)
		);
		self::$original_s = $s;
	}

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );
	}

	/**
	 * Restores the `$s` global after each test.
	 */
	public function tear_down() {
		global $s;

		$s = self::$original_s;

		parent::tear_down();
	}

	/**
	 * Adds a fake plugin to an array of plugins.
	 *
	 * Used as a callback for the 'plugins_list' hook.
	 *
	 * @return array
	 */
	public function plugins_list_filter( $plugins_list ) {
		$plugins_list['mustuse'] = $this->fake_plugin;

		return $plugins_list;
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_status_mustuse_and_dropins() {
		return array(
			'Must-Use' => array( 'mustuse' ),
			'Drop-ins' => array( 'dropins' ),
		);
	}
}
