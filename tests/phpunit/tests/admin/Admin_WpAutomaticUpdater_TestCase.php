<?php

abstract class Admin_WpAutomaticUpdater_TestCase extends WP_UnitTestCase {
	/**
	 * An instance of WP_Automatic_Updater.
	 *
	 * @var WP_Automatic_Updater
	 */
	protected static $updater;

	/**
	 * WP_Automatic_Updater::send_plugin_theme_email
	 * made accessible.
	 *
	 * @var ReflectionMethod
	 */
	protected static $send_plugin_theme_email;

	/**
	 * Sets up shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
		self::$updater = new WP_Automatic_Updater();

		self::$send_plugin_theme_email = new ReflectionMethod( self::$updater, 'send_plugin_theme_email' );
		self::$send_plugin_theme_email->setAccessible( true );
	}

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_wp_mail', '__return_false' );
	}
}
