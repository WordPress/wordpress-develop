<?php

/**
 * @group admin
 */
abstract class Admin_Includes_Schema_TestCase extends WP_UnitTestCase {

	protected static $options;
	protected static $blogmeta;
	protected static $sitemeta;

	/**
	 * Make sure the schema code is loaded before the tests are run.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		global $wpdb;

		self::$options  = 'testprefix_options';
		self::$blogmeta = 'testprefix_blogmeta';
		self::$sitemeta = 'testprefix_sitemeta';

		$options  = self::$options;
		$blogmeta = self::$blogmeta;
		$sitemeta = self::$sitemeta;

		require_once ABSPATH . 'wp-admin/includes/schema.php';

		$charset_collate  = $wpdb->get_charset_collate();
		$max_index_length = 191;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"
			CREATE TABLE {$options} (
				option_id bigint(20) unsigned NOT NULL auto_increment,
				option_name varchar(191) NOT NULL default '',
				option_value longtext NOT NULL,
				autoload varchar(20) NOT NULL default 'yes',
				PRIMARY KEY  (option_id),
				UNIQUE KEY option_name (option_name)
			) {$charset_collate}
			"
		);
		$wpdb->query(
			"
			CREATE TABLE {$blogmeta} (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				blog_id bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY meta_key (meta_key({$max_index_length})),
				KEY blog_id (blog_id)
			) {$charset_collate}
			"
		);
		$wpdb->query(
			"
			CREATE TABLE {$sitemeta} (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				site_id bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY meta_key (meta_key({$max_index_length})),
				KEY site_id (site_id)
			) {$charset_collate}
			"
		);
		// phpcs:enable
	}

	/**
	 * Drop tables that were created before running the tests.
	 */
	public static function wpTearDownAfterClass() {
		global $wpdb;

		$options  = self::$options;
		$blogmeta = self::$blogmeta;
		$sitemeta = self::$sitemeta;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$options}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$blogmeta}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$sitemeta}" );
		// phpcs:enable
	}
}
