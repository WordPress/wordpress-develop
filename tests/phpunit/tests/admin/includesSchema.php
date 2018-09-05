<?php

/**
 * @group admin
 */
class Tests_Admin_Includes_Schema extends WP_UnitTestCase {

	private static $options;

	/**
	 * Make sure the schema code is loaded before the tests are run.
	 */
	public static function wpSetUpBeforeClass() {
		global $wpdb;

		self::$options  = 'testprefix_options';

		$options = self::$options;

		require_once( ABSPATH . 'wp-admin/includes/schema.php' );

		$charset_collate  = $wpdb->get_charset_collate();
		$max_index_length = 191;

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
	}

	/**
	 * Drop tables that were created before running the tests.
	 */
	public static function wpTearDownAfterClass() {
		global $wpdb;

		$options = self::$options;

		$wpdb->query( "DROP TABLE IF EXISTS {$options}" );
	}

	/**
	 * @ticket 44893
	 * @dataProvider data_populate_options
	 */
	function test_populate_options( $options, $expected ) {
		global $wpdb;

		remove_all_filters( 'option_admin_email' );
		remove_all_filters( 'pre_option_admin_email' );
		remove_all_filters( 'default_option_admin_email' );

		$orig_options  = $wpdb->options;
		$wpdb->options = self::$options;

		populate_options( $options );

		wp_cache_delete( 'alloptions', 'options' );

		$results = array();
		foreach ( $expected as $option => $value ) {
			$results[ $option ] = get_option( $option );
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->options}" );

		$wpdb->options = $orig_options;

		$this->assertEquals( $expected, $results );
	}

	public function data_populate_options() {
		return array(
			array(
				array(),
				array(
					// Random options to check.
					'posts_per_rss'    => 10,
					'rss_use_excerpt'  => 0,
					'mailserver_url'   => 'mail.example.com',
					'mailserver_login' => 'login@example.com',
					'mailserver_pass'  => 'password',
				),
			),
			array(
				array(
					'posts_per_rss'    => 7,
					'rss_use_excerpt'  => 1,
				),
				array(
					// Random options to check.
					'posts_per_rss'    => 7,
					'rss_use_excerpt'  => 1,
					'mailserver_url'   => 'mail.example.com',
					'mailserver_login' => 'login@example.com',
					'mailserver_pass'  => 'password',
				),
			),
			array(
				array(
					'custom_option' => '1',
				),
				array(
					// Random options to check.
					'custom_option'    => '1',
					'posts_per_rss'    => 10,
					'rss_use_excerpt'  => 0,
					'mailserver_url'   => 'mail.example.com',
					'mailserver_login' => 'login@example.com',
					'mailserver_pass'  => 'password',
				),
			),
			array(
				array(
					'use_quicktags' => '1',
				),
				array(
					// This option is on a blacklist and should never exist.
					'use_quicktags' => false,
				),
			),
			array(
				array(
					'rss_0123456789abcdef0123456789abcdef'    => '1',
					'rss_0123456789abcdef0123456789abcdef_ts' => '1',
				),
				array(
					// These options would be obsolete magpie cache data and should never exist.
					'rss_0123456789abcdef0123456789abcdef'    => false,
					'rss_0123456789abcdef0123456789abcdef_ts' => false,
				),
			),
		);
	}
}
