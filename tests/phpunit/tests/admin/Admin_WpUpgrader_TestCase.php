<?php

abstract class Admin_WpUpgrader_TestCase extends WP_UnitTestCase {

	/**
	 * An instance of the WP_Upgrader class being tested.
	 *
	 * @var WP_Upgrader
	 */
	protected static $instance;

	/**
	 * @var WP_Upgrader_Skin&PHPUnit\Framework\MockObject\MockObject
	 */
	protected static $upgrader_skin_mock;

	/**
	 * Filesystem mock.
	 *
	 * @var WP_Filesystem_Base&PHPUnit\Framework\MockObject\MockObject
	 */
	protected static $wp_filesystem_mock;

	/**
	 * A backup of the existing 'wp_filesystem' global.
	 *
	 * @var mixed|null
	 */
	protected static $wp_filesystem_backup = null;

	/**
	 * Loads the class to be tested.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	}

	/**
	 * Sets up the class instance and mocks needed for each test.
	 */
	public function set_up() {
		parent::set_up();

		self::$upgrader_skin_mock = $this->getMockBuilder( 'WP_Upgrader_Skin' )->getMock();

		self::$instance = new WP_Upgrader( self::$upgrader_skin_mock );

		self::$wp_filesystem_mock = $this->getMockBuilder( 'WP_Filesystem_Base' )->getMock();

		if ( array_key_exists( 'wp_filesystem', $GLOBALS ) ) {
			self::$wp_filesystem_backup = $GLOBALS['wp_filesystem'];
		}

		$GLOBALS['wp_filesystem'] = self::$wp_filesystem_mock;
	}

	/**
	 * Cleans up after each test.
	 */
	public function tear_down() {
		if ( null !== self::$wp_filesystem_backup ) {
			$GLOBALS['wp_filesystem'] = self::$wp_filesystem_backup;
		} else {
			unset( $GLOBALS['wp_filesystem'] );
		}

		parent::tear_down();
	}
}
