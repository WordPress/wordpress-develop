<?php

/**
 * Test dbDelta()
 *
 * @group upgrade
 * @group dbdelta
 */
class Tests_dbDelta extends WP_UnitTestCase {

	/**
	 * Make sure the upgrade code is loaded before the tests are run.
	 */
	public static function setUpBeforeClass() {

		parent::setUpBeforeClass();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	}

	/**
	 * Create a custom table to be used in each test.
	 */
	public function setUp() {

		global $wpdb;

		$wpdb->query(
			"
			CREATE TABLE {$wpdb->prefix}dbdelta_test (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				column_1 varchar(255) NOT NULL,
				column_2 text,
				column_3 blob,
				PRIMARY KEY  (id),
				KEY key_1 (column_1),
				KEY compound_key (id,column_1)
			)
			"
		);

		parent::setUp();
	}

	/**
	 * Delete the custom table on teardown.
	 */
	public function tearDown() {

		global $wpdb;

		parent::tearDown();

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dbdelta_test" );
	}

	function test_create_new_table() {
		$table_name = 'test_new_table';

		$create = "CREATE TABLE $table_name (\n a varchar(255)\n)";
		$expected = array( $table_name => "Created table $table_name" );

		$actual = dbDelta( $create, false );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 31869
	 */
	function test_truncated_index() {
		global $wpdb;

		if ( ! $wpdb->has_cap( 'utf8mb4' ) ) {
			$this->markTestSkipped( 'This test requires utf8mb4 support in MySQL.' );
		}

		include_once( ABSPATH . 'wp-admin/includes/upgrade.php');
		$table_name = 'test_truncated_index';

		$create = "CREATE TABLE $table_name (\n a varchar(255) COLLATE utf8mb4_unicode_ci,\n KEY a (a)\n)";
		$wpdb->query( $create );

		$actual = dbDelta( $create, false );

		$this->assertSame( array(), $actual );
	}

	/**
	 * @ticket 36748
	 */
	function test_dont_downsize_text_fields() {
		global $wpdb;

		$result = dbDelta(
			"
			CREATE TABLE {$wpdb->prefix}dbdelta_test (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				column_1 varchar(255) NOT NULL,
				column_2 tinytext,
				column_3 blob,
				PRIMARY KEY  (id),
				KEY key_1 (column_1),
				KEY compound_key (id,column_1)
			) ENGINE=MyISAM
			", false );

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 36748
	 */
	function test_dont_downsize_blob_fields() {
		global $wpdb;

		$result = dbDelta(
			"
			CREATE TABLE {$wpdb->prefix}dbdelta_test (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				column_1 varchar(255) NOT NULL,
				column_2 text,
				column_3 tinyblob,
				PRIMARY KEY  (id),
				KEY key_1 (column_1),
				KEY compound_key (id,column_1)
			) ENGINE=MyISAM
			", false );

		$this->assertSame( array(), $result );
	}

	/**
	 * @ticket 36748
	 */
	function test_upsize_text_fields() {
		global $wpdb;

		$result = dbDelta(
			"
			CREATE TABLE {$wpdb->prefix}dbdelta_test (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				column_1 varchar(255) NOT NULL,
				column_2 bigtext,
				column_3 blob,
				PRIMARY KEY  (id),
				KEY key_1 (column_1),
				KEY compound_key (id,column_1)
			) ENGINE=MyISAM
			", false );

		$this->assertSame(
			array(
				"{$wpdb->prefix}dbdelta_test.column_2"
					=> "Changed type of {$wpdb->prefix}dbdelta_test.column_2 from text to bigtext"
			), $result );
	}

	/**
	 * @ticket 36748
	 */
	function test_upsize_blob_fields() {
		global $wpdb;

		$result = dbDelta(
			"
			CREATE TABLE {$wpdb->prefix}dbdelta_test (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				column_1 varchar(255) NOT NULL,
				column_2 text,
				column_3 mediumblob,
				PRIMARY KEY  (id),
				KEY key_1 (column_1),
				KEY compound_key (id,column_1)
			) ENGINE=MyISAM
			", false );

		$this->assertSame(
			array(
				"{$wpdb->prefix}dbdelta_test.column_3"
					=> "Changed type of {$wpdb->prefix}dbdelta_test.column_3 from blob to mediumblob"
			), $result );
	}
}
