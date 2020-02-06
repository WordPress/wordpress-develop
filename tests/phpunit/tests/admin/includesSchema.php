<?php

/**
 * @group admin
 */
class Tests_Admin_Includes_Schema extends WP_UnitTestCase {

	private static $options;
	private static $blogmeta;
	private static $sitemeta;

	/**
	 * Make sure the schema code is loaded before the tests are run.
	 */
	public static function wpSetUpBeforeClass() {
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

	/**
	 * @ticket 44893
	 * @dataProvider data_populate_options
	 */
	function test_populate_options( $options, $expected ) {
		global $wpdb;

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
					'posts_per_rss'   => 7,
					'rss_use_excerpt' => 1,
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
					'rss_0123456789abcdef0123456789abcdef' => '1',
					'rss_0123456789abcdef0123456789abcdef_ts' => '1',
				),
				array(
					// These options would be obsolete magpie cache data and should never exist.
					'rss_0123456789abcdef0123456789abcdef' => false,
					'rss_0123456789abcdef0123456789abcdef_ts' => false,
				),
			),
		);
	}

	/**
	 * @ticket 44896
	 * @group multisite
	 * @group ms-required
	 * @dataProvider data_populate_site_meta
	 */
	function test_populate_site_meta( $meta, $expected ) {
		global $wpdb;

		$orig_blogmeta  = $wpdb->blogmeta;
		$wpdb->blogmeta = self::$blogmeta;

		populate_site_meta( 42, $meta );

		$results = array();
		foreach ( $expected as $meta_key => $value ) {
			$results[ $meta_key ] = get_site_meta( 42, $meta_key, true );
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->blogmeta}" );

		$wpdb->blogmeta = $orig_blogmeta;

		$this->assertEquals( $expected, $results );
	}

	public function data_populate_site_meta() {
		return array(
			array(
				array(),
				array(
					'unknown_value' => '',
				),
			),
			array(
				array(
					'custom_meta' => '1',
				),
				array(
					'custom_meta' => '1',
				),
			),
		);
	}

	/**
	 * @ticket 44895
	 * @group multisite
	 * @dataProvider data_populate_network_meta
	 */
	function test_populate_network_meta( $meta, $expected ) {
		global $wpdb;

		$orig_sitemeta  = $wpdb->sitemeta;
		$wpdb->sitemeta = self::$sitemeta;

		populate_network_meta( 42, $meta );

		$results = array();
		foreach ( $expected as $meta_key => $value ) {
			if ( is_multisite() ) {
				$results[ $meta_key ] = get_network_option( 42, $meta_key );
			} else {
				$results[ $meta_key ] = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", $meta_key, 42 ) );
			}
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->sitemeta}" );

		$wpdb->sitemeta = $orig_sitemeta;

		$this->assertEquals( $expected, $results );
	}

	public function data_populate_network_meta() {
		return array(
			array(
				array(),
				array(
					// Random meta to check.
					'registration'      => 'none',
					'blog_upload_space' => 100,
					'fileupload_maxk'   => 1500,
				),
			),
			array(
				array(
					'site_name' => 'My Great Network',
					'WPLANG'    => 'fr_FR',
				),
				array(
					// Random meta to check.
					'site_name'         => 'My Great Network',
					'registration'      => 'none',
					'blog_upload_space' => 100,
					'fileupload_maxk'   => 1500,
					'WPLANG'            => 'fr_FR',
				),
			),
			array(
				array(
					'custom_meta' => '1',
				),
				array(
					// Random meta to check.
					'custom_meta'       => '1',
					'registration'      => 'none',
					'blog_upload_space' => 100,
					'fileupload_maxk'   => 1500,
				),
			),
		);
	}
}
