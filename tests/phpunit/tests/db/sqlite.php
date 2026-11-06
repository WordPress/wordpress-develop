<?php

/**
 * Tests for the SQLite support of the wpdb class.
 *
 * These tests only run when the tests use SQLite.
 *
 * @group wpdb
 * @group sqlite
 */
class Tests_DB_SQLite extends WP_UnitTestCase {

	public function set_up() {
		global $wpdb;

		parent::set_up();

		if ( 'sqlite' !== $wpdb->dbengine ) {
			$this->markTestSkipped( 'This test requires SQLite.' );
		}
	}

	/**
	 * @covers wpdb::db_connect
	 */
	public function test_connection_uses_mysql_on_sqlite_driver() {
		global $wpdb;

		$this->assertInstanceOf( 'WP_MySQL_On_SQLite', $wpdb->dbh );
		$this->assertTrue( $wpdb->is_mysql, 'SQLite should use the MySQL code paths of wpdb.' );
	}

	/**
	 * @covers wpdb::db_server_info
	 * @covers wpdb::db_version
	 */
	public function test_db_version_is_emulated_mysql_version() {
		global $wpdb;

		$this->assertStringStartsWith( $wpdb->db_version(), $wpdb->db_server_info() );
		$this->assertTrue( version_compare( $wpdb->db_version(), '5.7', '>=' ), 'The emulated MySQL version should be 5.7 or newer.' );
	}

	/**
	 * @covers wpdb::determine_charset
	 *
	 * @dataProvider data_determine_charset_uses_utf8mb4
	 *
	 * @param string $charset          The character set to check.
	 * @param string $collate          The collation to check.
	 * @param string $expected_collate The expected collation.
	 */
	public function test_determine_charset_uses_utf8mb4( $charset, $collate, $expected_collate ) {
		global $wpdb;

		$this->assertSame(
			array(
				'charset' => 'utf8mb4',
				'collate' => $expected_collate,
			),
			$wpdb->determine_charset( $charset, $collate )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_determine_charset_uses_utf8mb4() {
		return array(
			'no charset'     => array( '', '', 'utf8mb4_unicode_520_ci' ),
			'latin1 charset' => array( 'latin1', '', 'utf8mb4_unicode_520_ci' ),
			'utf8 charset'   => array( 'utf8', 'utf8_general_ci', 'utf8mb4_unicode_520_ci' ),
			'utf8mb4 binary' => array( 'utf8mb4', 'utf8mb4_bin', 'utf8mb4_bin' ),
		);
	}

	/**
	 * @covers wpdb::select
	 */
	public function test_select_does_nothing() {
		global $wpdb;

		$wpdb->select( 'nonexistent_database' );

		$this->assertTrue( $wpdb->ready );
		$this->assertSame( '1', $wpdb->get_var( 'SELECT 1' ) );
	}

	/**
	 * @covers wpdb::query
	 */
	public function test_query_sets_rows_affected_and_insert_id() {
		global $wpdb;

		$rows = $wpdb->query( "INSERT INTO $wpdb->options (option_name, option_value) VALUES ('test_sqlite_a', 'a'), ('test_sqlite_b', 'b')" );
		$this->assertSame( 2, $rows );
		$this->assertSame( 2, $wpdb->rows_affected );

		$last_id = (int) $wpdb->get_var( "SELECT option_id FROM $wpdb->options WHERE option_name = 'test_sqlite_b'" );
		$wpdb->query( "INSERT INTO $wpdb->options (option_name, option_value) VALUES ('test_sqlite_c', 'c')" );
		$this->assertSame( $last_id + 1, $wpdb->insert_id );

		$rows = $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'test\\_sqlite\\_%'" );
		$this->assertSame( 3, $rows );
	}

	/**
	 * @covers wpdb::query
	 */
	public function test_query_returns_number_of_selected_rows() {
		global $wpdb;

		$rows = $wpdb->query( 'SELECT 1 AS a UNION ALL SELECT 2' );

		$this->assertSame( 2, $rows );
		$this->assertSame( 2, $wpdb->num_rows );
		$this->assertEquals(
			array(
				(object) array( 'a' => '1' ),
				(object) array( 'a' => '2' ),
			),
			$wpdb->last_result,
			'The values should be fetched as strings, like with mysqli.'
		);
	}

	/**
	 * @covers wpdb::flush
	 */
	public function test_flush_releases_result() {
		global $wpdb;

		$wpdb->query( 'SELECT 1' );
		$this->assertInstanceOf( 'PDOStatement', $wpdb->result );

		$wpdb->flush();
		$this->assertNull( $wpdb->result );
	}

	/**
	 * @covers wpdb::get_col_info
	 * @covers wpdb::load_col_info
	 */
	public function test_get_col_info_has_mysqli_properties() {
		global $wpdb;

		$wpdb->get_results( "SELECT ID, post_title AS title FROM $wpdb->posts LIMIT 1" );

		$this->assertSame( array( 'ID', 'title' ), $wpdb->get_col_info( 'name' ) );
		$this->assertSame( array( $wpdb->posts, $wpdb->posts ), $wpdb->get_col_info( 'table' ) );
		// The MYSQLI_TYPE_LONGLONG and MYSQLI_TYPE_BLOB values, as mysqli may not be installed.
		$this->assertSame( array( 8, 252 ), $wpdb->get_col_info( 'type' ) );
	}

	/**
	 * @covers wpdb::print_error
	 */
	public function test_print_error_uses_last_error() {
		global $wpdb, $EZSQL_ERROR;

		$suppress = $wpdb->suppress_errors( true );
		$wpdb->query( 'SELECT * FROM nonexistent_table' );
		$wpdb->suppress_errors( $suppress );

		$error = end( $EZSQL_ERROR );
		$this->assertSame( 'SELECT * FROM nonexistent_table', $error['query'] );
		$this->assertSame( $wpdb->last_error, $error['error_str'] );
		$this->assertNotEmpty( $error['error_str'] );
	}

	/**
	 * @covers wpdb::bail
	 */
	public function test_bail_shows_escaped_last_error() {
		global $wpdb;

		$wpdb->last_error = '<b>error</b>';

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( '<p><code>&lt;b&gt;error&lt;/b&gt;</code></p>' );

		$show_errors = $wpdb->show_errors();
		try {
			$wpdb->bail( 'Database is dead.' );
		} finally {
			$wpdb->show_errors( $show_errors );
		}
	}

	/**
	 * @covers wpdb::check_connection
	 */
	public function test_check_connection_reconnects() {
		global $wpdb;

		$wpdb->close();
		$this->assertNull( $wpdb->dbh );

		$this->assertTrue( $wpdb->check_connection() );
		$this->assertInstanceOf( 'WP_MySQL_On_SQLite', $wpdb->dbh );
	}
}
