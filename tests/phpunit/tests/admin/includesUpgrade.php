<?php

/**
 * Test the admin includes upgrade functions.
 *
 * @group admin
 * @group upgrade
 */
class Tests_Admin_IncludesUpgrade extends WP_UnitTestCase {

	/**
	 * The name of the table used by the tests.
	 *
	 * @var string
	 */
	private static $table;

	/**
	 * Whether the server reports utf8 as utf8mb3 (MariaDB 10.6.1+ or MySQL 8.0.30+).
	 *
	 * @var bool
	 */
	private static $utf8_is_utf8mb3 = false;

	/**
	 * Load the upgrade code and determine how the server reports utf8.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		self::$table = $wpdb->prefix . 'utf8mb3_test';

		$db_server_info = $wpdb->db_server_info();
		$db_version     = $wpdb->db_version();

		// Account for MariaDB version being prefixed with '5.5.5-' on older PHP versions.
		if ( '5.5.5' === $db_version && str_contains( $db_server_info, 'MariaDB' ) && PHP_VERSION_ID < 80016 ) {
			$db_server_info = preg_replace( '/^5\.5\.5-(.*)/', '$1', $db_server_info );
			$db_version     = preg_replace( '/[^0-9.].*/', '', $db_server_info );
		}

		/*
		 * MariaDB 10.6.1 or later and MySQL 8.0.30 or later
		 * use utf8mb3 instead of utf8 in various commands output.
		 */
		if ( ( str_contains( $db_server_info, 'MariaDB' ) && version_compare( $db_version, '10.6.1', '>=' ) )
			|| ( ! str_contains( $db_server_info, 'MariaDB' ) && version_compare( $db_version, '8.0.30', '>=' ) )
		) {
			self::$utf8_is_utf8mb3 = true;
		}
	}

	/**
	 * Drop the table used by the tests.
	 */
	public static function tear_down_after_class() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::$table ) );

		parent::tear_down_after_class();
	}

	/**
	 * @ticket 60002
	 *
	 * @covers ::maybe_convert_table_to_utf8mb4
	 */
	public function test_maybe_convert_table_to_utf8mb4_converts_utf8_table() {
		global $wpdb;

		$table = self::$table;

		$charset = self::$utf8_is_utf8mb3 ? 'utf8mb3' : 'utf8';
		$collate = self::$utf8_is_utf8mb3 ? 'utf8mb3_general_ci' : 'utf8_general_ci';

		// Remove the temporary table filters as `SHOW TABLE STATUS` cannot see temporary tables.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );

		$create = "CREATE TABLE `$table` (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(50),
			PRIMARY KEY (id)
		) DEFAULT CHARACTER SET {$charset} COLLATE {$collate}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertNotFalse( $wpdb->query( $create ) );

		$this->assertTrue( maybe_convert_table_to_utf8mb4( $table ) );

		$table_details = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $table ) );

		$this->assertSame( 'utf8mb4_unicode_ci', $table_details->Collation );

		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	/**
	 * @ticket 60002
	 *
	 * @covers ::maybe_convert_table_to_utf8mb4
	 */
	public function test_maybe_convert_table_to_utf8mb4_skips_non_utf8_columns() {
		global $wpdb;

		$table = self::$table;

		// Remove the temporary table filters as `SHOW TABLE STATUS` cannot see temporary tables.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );

		$create = "CREATE TABLE `$table` (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci,
			PRIMARY KEY (id)
		) DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertNotFalse( $wpdb->query( $create ) );

		$this->assertFalse( maybe_convert_table_to_utf8mb4( $table ) );

		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}
