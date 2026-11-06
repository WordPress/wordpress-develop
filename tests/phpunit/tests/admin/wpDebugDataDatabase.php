<?php

/**
 * Tests for the database sections of WP_Debug_Data.
 *
 * @group admin
 * @group site-health
 * @group wpdb
 */
class Tests_Admin_wpDebugDataDatabase extends WP_UnitTestCase {

	/**
	 * Loads the debug data class.
	 */
	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
	}

	/**
	 * @covers WP_Debug_Data::get_wp_constants
	 */
	public function test_constants_include_db_engine() {
		$fields = $this->call( 'get_wp_constants' )['fields'];

		$this->assertArrayHasKey( 'DB_ENGINE', $fields );
		$this->assertSame( defined( 'DB_ENGINE' ) ? DB_ENGINE : 'undefined', $fields['DB_ENGINE']['debug'] );
	}

	/**
	 * @covers WP_Debug_Data::get_wp_database
	 */
	public function test_database_type_matches_database_engine() {
		global $wpdb;

		$fields = $this->call( 'get_wp_database' )['fields'];

		$this->assertSame( $wpdb->dbengine, $fields['db_engine']['debug'] );
	}

	/**
	 * @covers WP_Debug_Data::get_wp_database
	 * @covers WP_Debug_Data::get_wp_database_sqlite
	 */
	public function test_sqlite_database_fields() {
		global $wpdb;

		if ( 'sqlite' !== $wpdb->dbengine ) {
			$this->markTestSkipped( 'This test requires SQLite.' );
		}

		$fields = $this->call( 'get_wp_database' )['fields'];

		$this->assertSame(
			array(
				'db_engine',
				'database_version',
				'sqlite_driver_version',
				'mysql_version',
				'database_file',
				'database_prefix',
				'database_charset',
				'database_collate',
			),
			array_keys( $fields ),
			'SQLite should only show the fields that apply to it.'
		);

		$this->assertSame( $wpdb->dbh->get_sqlite_version(), $fields['database_version']['value'] );
		$this->assertSame( SQLITE_DRIVER_VERSION, $fields['sqlite_driver_version']['value'] );
		$this->assertSame( $wpdb->db_version(), $fields['mysql_version']['value'] );
		$this->assertSame( DB_PATH, $fields['database_file']['value'] );
		$this->assertTrue( $fields['database_file']['private'], 'The database file path should be private.' );
	}

	/**
	 * @covers WP_Debug_Data::get_database_size
	 */
	public function test_get_database_size() {
		global $wpdb;

		clearstatcache();
		$size = WP_Debug_Data::get_database_size();

		if ( 'sqlite' === $wpdb->dbengine ) {
			$expected = filesize( DB_PATH ) + ( is_file( DB_PATH . '-wal' ) ? filesize( DB_PATH . '-wal' ) : 0 );
			$this->assertSame( $expected, $size, 'The SQLite database size should be the size of the database files.' );
		} else {
			$this->assertGreaterThan( 0, $size );
		}
	}

	/**
	 * Calls a private static method of WP_Debug_Data.
	 *
	 * @param string $method The method name.
	 * @return array The method's return value.
	 */
	private function call( $method ) {
		$reflection = new ReflectionMethod( 'WP_Debug_Data', $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->invoke( null );
	}
}
