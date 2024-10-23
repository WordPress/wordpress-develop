<?php

/**
 * Test WPDB methods
 *
 * @group wpdb
 */
class Tests_DB extends WP_UnitTestCase {

	/**
	 * Query log
	 *
	 * @var array
	 */
	protected $_queries = array();

	/**
	 * Our special WPDB
	 *
	 * @var resource
	 */
	protected static $_wpdb;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$_wpdb = new WpdbExposedMethodsForTesting();
	}

	/**
	 * Set up the test fixture
	 */
	public function set_up() {
		parent::set_up();
		$this->_queries = array();
		add_filter( 'query', array( $this, 'query_filter' ) );
		self::$_wpdb->last_error     = null;
		$GLOBALS['wpdb']->last_error = null;
	}

	/**
	 * Log each query
	 *
	 * @param string $sql
	 * @return string
	 */
	public function query_filter( $sql ) {
		$this->_queries[] = $sql;
		return $sql;
	}

	/**
	 * Test that WPDB will reconnect when the DB link dies
	 *
	 * @ticket 5932
	 */
	public function test_db_reconnect() {
		global $wpdb;

		$var = $wpdb->get_var( "SELECT ID FROM $wpdb->users LIMIT 1" );
		$this->assertGreaterThan( 0, $var );

		$wpdb->close();

		$var = $wpdb->get_var( "SELECT ID FROM $wpdb->users LIMIT 1" );

		// Ensure all database handles have been properly reconnected after this test.
		$wpdb->db_connect();
		self::$_wpdb->db_connect();

		$this->assertGreaterThan( 0, $var );
	}

	/**
	 * Test that floats formatted as "0,700" get sanitized properly by wpdb
	 *
	 * @global mixed $wpdb
	 *
	 * @ticket 19861
	 */
	public function test_locale_floats() {
		global $wpdb;

		// Save the current locale settings.
		$current_locales = explode( ';', setlocale( LC_ALL, 0 ) );

		// Switch to a locale using comma as a decimal point separator.
		$flag = setlocale( LC_ALL, 'ru_RU.utf8', 'rus', 'fr_FR.utf8', 'fr_FR', 'de_DE.utf8', 'de_DE', 'es_ES.utf8', 'es_ES' );
		if ( false === $flag ) {
			$this->markTestSkipped( 'No European locales available for testing.' );
		}

		// Try an update query.
		$wpdb->suppress_errors( true );
		$wpdb->update(
			'test_table',
			array( 'float_column' => 0.7 ),
			array( 'meta_id' => 5 ),
			array( '%f' ),
			array( '%d' )
		);
		$wpdb->suppress_errors( false );

		// Ensure the float isn't 0,700.
		$this->assertStringContainsString( '0.700', array_pop( $this->_queries ) );

		// Try a prepare.
		$sql = $wpdb->prepare( 'UPDATE test_table SET float_column = %f AND meta_id = %d', 0.7, 5 );
		$this->assertStringContainsString( '0.700', $sql );

		// Restore locale settings.
		foreach ( $current_locales as $locale_setting ) {
			if ( false !== strpos( $locale_setting, '=' ) ) {
				list( $category, $locale ) = explode( '=', $locale_setting );
				if ( defined( $category ) ) {
					setlocale( constant( $category ), $locale );
				}
			} else {
				setlocale( LC_ALL, $locale_setting );
			}
		}
	}

	/**
	 * @ticket 10041
	 */
	public function test_esc_like() {
		global $wpdb;

		$inputs   = array(
			'howdy%',              // Single percent.
			'howdy_',              // Single underscore.
			'howdy\\',             // Single slash.
			'howdy\\howdy%howdy_', // The works.
			'howdy\'"[[]*#[^howdy]!+)(*&$#@!~|}{=--`/.,<>?', // Plain text.
		);
		$expected = array(
			'howdy\\%',
			'howdy\\_',
			'howdy\\\\',
			'howdy\\\\howdy\\%howdy\\_',
			'howdy\'"[[]*#[^howdy]!+)(*&$#@!~|}{=--`/.,<>?',
		);

		foreach ( $inputs as $key => $input ) {
			$this->assertSame( $expected[ $key ], $wpdb->esc_like( $input ) );
		}
	}

	/**
	 * Test LIKE Queries
	 *
	 * Make sure $wpdb is fully compatible with esc_like() by testing the identity of various strings.
	 * When escaped properly, a string literal is always LIKE itself (1)
	 * and never LIKE any other string literal (0) no matter how crazy the SQL looks.
	 *
	 * @ticket 10041
	 * @dataProvider data_like_query
	 * @param $data string The haystack, raw.
	 * @param $like string The like phrase, raw.
	 * @param $result string The expected comparison result; '1' = true, '0' = false
	 */
	public function test_like_query( $data, $like, $result ) {
		global $wpdb;
		return $this->assertSame( $result, $wpdb->get_var( $wpdb->prepare( 'SELECT %s LIKE %s', $data, $wpdb->esc_like( $like ) ) ) );
	}

	public function data_like_query() {
		return array(
			array(
				'aaa',
				'aaa',
				'1',
			),
			array(
				'a\\aa', // SELECT 'a\\aa'  # This represents a\aa in both languages.
				'a\\aa', // LIKE 'a\\\\aa'
				'1',
			),
			array(
				'a%aa',
				'a%aa',
				'1',
			),
			array(
				'aaaa',
				'a%aa',
				'0',
			),
			array(
				'a\\%aa', // SELECT 'a\\%aa'
				'a\\%aa', // LIKE 'a\\\\\\%aa' # The PHP literal would be "LIKE 'a\\\\\\\\\\\\%aa'". This is why we need reliable escape functions!
				'1',
			),
			array(
				'a%aa',
				'a\\%aa',
				'0',
			),
			array(
				'a\\%aa',
				'a%aa',
				'0',
			),
			array(
				'a_aa',
				'a_aa',
				'1',
			),
			array(
				'aaaa',
				'a_aa',
				'0',
			),
			array(
				'howdy\'"[[]*#[^howdy]!+)(*&$#@!~|}{=--`/.,<>?',
				'howdy\'"[[]*#[^howdy]!+)(*&$#@!~|}{=--`/.,<>?',
				'1',
			),
		);
	}

	/**
	 * @ticket 18510
	 */
	public function test_wpdb_supposedly_protected_properties() {
		global $wpdb;

		$this->assertNotEmpty( $wpdb->dbh );
		$dbh = $wpdb->dbh;
		$this->assertNotEmpty( $dbh );
		$this->assertTrue( isset( $wpdb->dbh ) ); // Test __isset().
		unset( $wpdb->dbh );
		$this->assertTrue( empty( $wpdb->dbh ) );
		$wpdb->dbh = $dbh;
		$this->assertNotEmpty( $wpdb->dbh );
	}

	/**
	 * @ticket 21212
	 */
	public function test_wpdb_actually_protected_properties() {
		global $wpdb;

		$new_meta = "HAHA I HOPE THIS DOESN'T WORK";

		$col_meta       = $wpdb->col_meta;
		$wpdb->col_meta = $new_meta;

		$this->assertNotEquals( $col_meta, $new_meta );
		$this->assertSame( $col_meta, $wpdb->col_meta );
	}

	/**
	 * @ticket 18510
	 */
	public function test_wpdb_nonexistent_properties() {
		global $wpdb;

		$this->assertTrue( empty( $wpdb->nonexistent_property ) );
		$wpdb->nonexistent_property = true;
		$this->assertTrue( $wpdb->nonexistent_property );
		$this->assertTrue( isset( $wpdb->nonexistent_property ) );
		unset( $wpdb->nonexistent_property );
		$this->assertTrue( empty( $wpdb->nonexistent_property ) );
	}

	/**
	 * Test that an escaped %%f is not altered
	 *
	 * @ticket 19861
	 */
	public function test_double_escaped_placeholders() {
		global $wpdb;
		$sql = $wpdb->prepare( "UPDATE test_table SET string_column = '%%f is a float, %%d is an int %d, %%s is a string', field = %s", 3, '4' );
		$this->assertStringContainsString( $wpdb->placeholder_escape(), $sql );

		$sql = $wpdb->remove_placeholder_escape( $sql );
		$this->assertSame( "UPDATE test_table SET string_column = '%f is a float, %d is an int 3, %s is a string', field = '4'", $sql );
	}


	/**
	 * Test that SQL modes are set correctly
	 *
	 * @ticket 26847
	 */
	public function test_set_sql_mode() {
		global $wpdb;

		$current_modes = $wpdb->get_var( 'SELECT @@SESSION.sql_mode;' );

		$new_modes = array( 'IGNORE_SPACE', 'NO_AUTO_VALUE_ON_ZERO' );

		$wpdb->set_sql_mode( $new_modes );

		$check_new_modes = $wpdb->get_var( 'SELECT @@SESSION.sql_mode;' );
		$this->assertSameSets( $new_modes, explode( ',', $check_new_modes ) );

		$wpdb->set_sql_mode( explode( ',', $current_modes ) );
	}

	/**
	 * Test that incompatible SQL modes are blocked
	 *
	 * @ticket 26847
	 */
	public function test_set_incompatible_sql_mode() {
		global $wpdb;

		$current_modes = $wpdb->get_var( 'SELECT @@SESSION.sql_mode;' );

		$new_modes = array( 'IGNORE_SPACE', 'NO_ZERO_DATE', 'NO_AUTO_VALUE_ON_ZERO' );
		$wpdb->set_sql_mode( $new_modes );
		$check_new_modes = $wpdb->get_var( 'SELECT @@SESSION.sql_mode;' );
		$this->assertNotContains( 'NO_ZERO_DATE', explode( ',', $check_new_modes ) );

		$wpdb->set_sql_mode( explode( ',', $current_modes ) );
	}

	/**
	 * Test that incompatible SQL modes can be changed
	 *
	 * @ticket 26847
	 */
	public function test_set_allowed_incompatible_sql_mode() {
		global $wpdb;

		$current_modes = $wpdb->get_var( 'SELECT @@SESSION.sql_mode;' );

		$new_modes = array( 'IGNORE_SPACE', 'ONLY_FULL_GROUP_BY', 'NO_AUTO_VALUE_ON_ZERO' );

		add_filter( 'incompatible_sql_modes', array( $this, 'filter_allowed_incompatible_sql_mode' ), 1, 1 );
		$wpdb->set_sql_mode( $new_modes );
		remove_filter( 'incompatible_sql_modes', array( $this, 'filter_allowed_incompatible_sql_mode' ), 1 );

		$check_new_modes = $wpdb->get_var( 'SELECT @@SESSION.sql_mode;' );
		$this->assertContains( 'ONLY_FULL_GROUP_BY', explode( ',', $check_new_modes ) );

		$wpdb->set_sql_mode( explode( ',', $current_modes ) );
	}

	public function filter_allowed_incompatible_sql_mode( $modes ) {
		$pos = array_search( 'ONLY_FULL_GROUP_BY', $modes, true );
		$this->assertGreaterThanOrEqual( 0, $pos );

		if ( false === $pos ) {
			return $modes;
		}

		unset( $modes[ $pos ] );
		return $modes;
	}

	/**
	 * @ticket 25604
	 * @expectedIncorrectUsage wpdb::prepare
	 */
	public function test_prepare_without_arguments() {
		global $wpdb;
		$id = 0;
		// This, obviously, is an incorrect prepare.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = $id", $id );
		$this->assertSame( "SELECT * FROM $wpdb->users WHERE id = 0", $prepared );
	}

	public function test_prepare_sprintf() {
		global $wpdb;

		$prepared = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", 1, 'admin' );
		$this->assertSame( "SELECT * FROM $wpdb->users WHERE id = 1 AND user_login = 'admin'", $prepared );
	}

	/**
	 * @expectedIncorrectUsage wpdb::prepare
	 */
	public function test_prepare_sprintf_invalid_args() {
		global $wpdb;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$prepared = @$wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", 1, array( 'admin' ) );
		$this->assertSame( "SELECT * FROM $wpdb->users WHERE id = 1 AND user_login = ''", $prepared );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$prepared = @$wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", array( 1 ), 'admin' );
		$this->assertSame( "SELECT * FROM $wpdb->users WHERE id = 0 AND user_login = 'admin'", $prepared );
	}

	public function test_prepare_vsprintf() {
		global $wpdb;

		$prepared = $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", array( 1, 'admin' ) );
		$this->assertSame( "SELECT * FROM $wpdb->users WHERE id = 1 AND user_login = 'admin'", $prepared );
	}

	/**
	 * @expectedIncorrectUsage wpdb::prepare
	 */
	public function test_prepare_vsprintf_invalid_args() {
		global $wpdb;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$prepared = @$wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", array( 1, array( 'admin' ) ) );
		$this->assertSame( "SELECT * FROM $wpdb->users WHERE id = 1 AND user_login = ''", $prepared );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$prepared = @$wpdb->prepare( "SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s", array( array( 1 ), 'admin' ) );
		$this->assertSame( "SELECT * FROM $wpdb->users WHERE id = 0 AND user_login = 'admin'", $prepared );
	}

	/**
	 * @ticket 42040
	 * @dataProvider data_prepare_incorrect_arg_count
	 * @expectedIncorrectUsage wpdb::prepare
	 */
	public function test_prepare_incorrect_arg_count( $query, $args, $expected ) {
		global $wpdb;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.DB.PreparedSQL
		$prepared = @$wpdb->prepare( $query, ...$args );
		$this->assertSame( $expected, $prepared );
	}

	public function data_prepare_incorrect_arg_count() {
		global $wpdb;

		$placeholder_escape = $wpdb->placeholder_escape();

		return array(
			array(
				"SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s",     // Query.
				array( 1, 'admin', 'extra-arg' ),                                   // ::prepare() args, to be passed via call_user_func_array().
				"SELECT * FROM $wpdb->users WHERE id = 1 AND user_login = 'admin'", // Expected output.
			),
			array(
				"SELECT * FROM $wpdb->users WHERE id = %%%d AND user_login = %s",
				array( 1 ),
				'',
			),
			array(
				"SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s",
				array( array( 1, 'admin', 'extra-arg' ) ),
				"SELECT * FROM $wpdb->users WHERE id = 1 AND user_login = 'admin'",
			),
			array(
				"SELECT * FROM $wpdb->users WHERE id = %d AND %% AND user_login = %s",
				array( 1, 'admin', 'extra-arg' ),
				"SELECT * FROM $wpdb->users WHERE id = 1 AND {$placeholder_escape} AND user_login = 'admin'",
			),
			array(
				"SELECT * FROM $wpdb->users WHERE id = %%%d AND %F AND %f AND user_login = %s",
				array( 1, 2.3, '4.5', 'admin', 'extra-arg' ),
				"SELECT * FROM $wpdb->users WHERE id = {$placeholder_escape}1 AND 2.300000 AND 4.500000 AND user_login = 'admin'",
			),
			array(
				"SELECT * FROM $wpdb->users WHERE id = %d AND user_login = %s",
				array( array( 1 ), 'admin', 'extra-arg' ),
				"SELECT * FROM $wpdb->users WHERE id = 0 AND user_login = 'admin'",
			),
			array(
				"SELECT * FROM $wpdb->users WHERE id = %d and user_nicename = %s and user_status = %d and user_login = %s",
				array( 1, 'admin', 0 ),
				'',
			),
			array(
				"SELECT * FROM $wpdb->users WHERE id = %d and user_nicename = %s and user_status = %d and user_login = %s",
				array( array( 1, 'admin', 0 ) ),
				'',
			),
			array(
				"SELECT * FROM $wpdb->users WHERE id = %d and %% and user_login = %s and user_status = %d and user_login = %s",
				array( 1, 'admin', 'extra-arg' ),
				'',
			),
		);
	}

	public function test_db_version() {
		global $wpdb;

		$this->assertTrue( version_compare( $wpdb->db_version(), '5.0', '>=' ) );
	}

	public function test_get_caller() {
		global $wpdb;
		$str    = $wpdb->get_caller();
		$calls  = explode( ', ', $str );
		$called = implode( '->', array( __CLASS__, __FUNCTION__ ) );
		$this->assertSame( $called, end( $calls ) );
	}

	public function test_has_cap() {
		global $wpdb;
		$this->assertTrue( $wpdb->has_cap( 'collation' ) );
		$this->assertTrue( $wpdb->has_cap( 'group_concat' ) );
		$this->assertTrue( $wpdb->has_cap( 'subqueries' ) );
		$this->assertTrue( $wpdb->has_cap( 'identifier_placeholders' ) );
		$this->assertTrue( $wpdb->has_cap( 'variadic_placeholders' ) );
		$this->assertTrue( $wpdb->has_cap( 'COLLATION' ) );
		$this->assertTrue( $wpdb->has_cap( 'GROUP_CONCAT' ) );
		$this->assertTrue( $wpdb->has_cap( 'SUBQUERIES' ) );
		$this->assertTrue( $wpdb->has_cap( 'IDENTIFIER_PLACEHOLDERS' ) );
		$this->assertTrue( $wpdb->has_cap( 'VARIADIC_PLACEHOLDERS' ) );
		$this->assertSame(
			version_compare( $wpdb->db_version(), '5.0.7', '>=' ),
			$wpdb->has_cap( 'set_charset' )
		);
		$this->assertSame(
			version_compare( $wpdb->db_version(), '5.0.7', '>=' ),
			$wpdb->has_cap( 'SET_CHARSET' )
		);
	}

	/**
	 * @expectedDeprecated supports_collation
	 */
	public function test_supports_collation() {
		global $wpdb;
		$this->assertTrue( $wpdb->supports_collation() );
	}

	public function test_check_database_version() {
		global $wpdb;
		$this->assertEmpty( $wpdb->check_database_version() );
	}

	public function test_bail() {
		global $wpdb;

		$this->expectException( 'WPDieException' );
		$wpdb->bail( 'Database is dead.' );
	}

	public function test_timers() {
		global $wpdb;

		$wpdb->timer_start();
		usleep( 5 );
		$stop = $wpdb->timer_stop();

		$this->assertNotEquals( $wpdb->time_start, $stop );
		$this->assertGreaterThan( $stop, $wpdb->time_start );
	}

	public function test_get_col_info() {
		global $wpdb;

		$wpdb->get_results( "SELECT ID FROM $wpdb->users" );

		$this->assertSame( array( 'ID' ), $wpdb->get_col_info() );
		$this->assertSame( array( $wpdb->users ), $wpdb->get_col_info( 'table' ) );
		$this->assertSame( $wpdb->users, $wpdb->get_col_info( 'table', 0 ) );
	}

	public function test_query_and_delete() {
		global $wpdb;
		$rows = $wpdb->query( "INSERT INTO $wpdb->users (display_name) VALUES ('Walter Sobchak')" );
		$this->assertSame( 1, $rows );
		$this->assertNotEmpty( $wpdb->insert_id );
		$d_rows = $wpdb->delete( $wpdb->users, array( 'ID' => $wpdb->insert_id ) );
		$this->assertSame( 1, $d_rows );
	}

	public function test_get_row() {
		global $wpdb;
		$rows = $wpdb->query( "INSERT INTO $wpdb->users (display_name) VALUES ('Walter Sobchak')" );
		$this->assertSame( 1, $rows );
		$this->assertNotEmpty( $wpdb->insert_id );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE ID = %d", $wpdb->insert_id ) );
		$this->assertIsObject( $row );
		$this->assertSame( 'Walter Sobchak', $row->display_name );
	}

	/**
	 * Test the `get_col()` method.
	 *
	 * @param string|null       $query       The query to run.
	 * @param string|array      $expected    The expected resulting value.
	 * @param array|string|null $last_result The value to assign to `$wpdb->last_result`.
	 * @param int|string        $column      The column index to retrieve.
	 *
	 * @dataProvider data_get_col
	 *
	 * @ticket 45299
	 */
	public function test_get_col( $query, $expected, $last_result, $column ) {
		global $wpdb;

		$wpdb->last_result = $last_result;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_col( $query, $column );

		if ( $query ) {
			$this->assertSame( $query, $wpdb->last_query );
		}

		if ( is_array( $expected ) ) {
			$this->assertSame( $expected, $result );
		} else {
			$this->assertContains( $expected, $result );
		}
	}

	/**
	 * Data provider for testing `get_col()`.
	 *
	 * @return array {
	 *     Arguments for testing `get_col()`.
	 *
	 *     @type string|null       $query       The query to run.
	 *     @type string|array      $expected    The resulting expected value.
	 *     @type array|string|null $last_result The value to assign to `$wpdb->last_result`.
	 *     @type int|string        $column      The column index to retrieve.
	 */
	public function data_get_col() {
		global $wpdb;

		return array(
			array(
				"SELECT display_name FROM $wpdb->users",
				'admin',
				array(),
				0,
			),
			array(
				"SELECT user_login, user_email FROM $wpdb->users",
				'admin',
				array(),
				0,
			),
			array(
				"SELECT user_login, user_email FROM $wpdb->users",
				'admin@example.org',
				array(),
				1,
			),
			array(
				"SELECT user_login, user_email FROM $wpdb->users",
				'admin@example.org',
				array(),
				'1',
			),
			array(
				"SELECT user_login, user_email FROM $wpdb->users",
				array( null ),
				array(),
				3,
			),
			array(
				'',
				array(),
				null,
				0,
			),
			array(
				null,
				array(),
				'',
				0,
			),
		);
	}

	public function test_replace() {
		global $wpdb;
		$rows1 = $wpdb->insert( $wpdb->users, array( 'display_name' => 'Walter Sobchak' ) );
		$this->assertSame( 1, $rows1 );
		$this->assertNotEmpty( $wpdb->insert_id );
		$last = $wpdb->insert_id;

		$rows2 = $wpdb->replace(
			$wpdb->users,
			array(
				'ID'           => $last,
				'display_name' => 'Walter Replace Sobchak',
			)
		);
		$this->assertSame( 2, $rows2 );
		$this->assertNotEmpty( $wpdb->insert_id );

		$this->assertSame( $last, $wpdb->insert_id );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE ID = %d", $last ) );
		$this->assertSame( 'Walter Replace Sobchak', $row->display_name );
	}

	/**
	 * wpdb::update() requires a WHERE condition.
	 *
	 * @ticket 26106
	 */
	public function test_empty_where_on_update() {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		$wpdb->update( $wpdb->posts, array( 'post_name' => 'burrito' ), array() );

		$expected1 = "UPDATE `{$wpdb->posts}` SET `post_name` = 'burrito' WHERE ";
		$this->assertNotEmpty( $wpdb->last_error );
		$this->assertSame( $expected1, $wpdb->last_query );

		$wpdb->update( $wpdb->posts, array( 'post_name' => 'burrito' ), array( 'post_status' => 'taco' ) );

		$expected2 = "UPDATE `{$wpdb->posts}` SET `post_name` = 'burrito' WHERE `post_status` = 'taco'";
		$this->assertEmpty( $wpdb->last_error );
		$this->assertSame( $expected2, $wpdb->last_query );
		$wpdb->suppress_errors( $suppress );
	}

	/**
	 * mysqli_ incorrect flush and further sync issues.
	 *
	 * @ticket 28155
	 */
	public function test_mysqli_flush_sync() {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );

		$wpdb->query( 'DROP PROCEDURE IF EXISTS `test_mysqli_flush_sync_procedure`' );
		$wpdb->query(
			'CREATE PROCEDURE `test_mysqli_flush_sync_procedure`() BEGIN
			SELECT ID FROM `' . $wpdb->posts . '` LIMIT 1;
		END'
		);

		if ( count( $wpdb->get_results( 'SHOW CREATE PROCEDURE `test_mysqli_flush_sync_procedure`' ) ) < 1 ) {
			$wpdb->suppress_errors( $suppress );
			$this->fail( 'Procedure could not be created (missing privileges?)' );
		}

		$post_id = self::factory()->post->create();

		$this->assertNotEmpty( $wpdb->get_results( 'CALL `test_mysqli_flush_sync_procedure`' ) );
		$this->assertNotEmpty( $wpdb->get_results( "SELECT ID FROM `{$wpdb->posts}` LIMIT 1" ) );

		// DROP PROCEDURE will cause a COMMIT, so we delete the post manually before that happens.
		wp_delete_post( $post_id, true );

		$wpdb->query( 'DROP PROCEDURE IF EXISTS `test_mysqli_flush_sync_procedure`' );
		$wpdb->suppress_errors( $suppress );
	}

	/**
	 * @ticket 21212
	 * @ticket 32763
	 */
	public function data_get_table_from_query() {
		$table       = 'a_test_table_name';
		$more_tables = array(
			// table_name => expected_value
			'`a_test_db`.`another_test_table`' => 'a_test_db.another_test_table',
			'a-test-with-dashes'               => 'a-test-with-dashes',
		);

		$queries = array(
			// Basic.
			"SELECT * FROM $table",
			"SELECT * FROM `$table`",

			"SELECT * FROM (SELECT * FROM $table) as subquery",

			"INSERT $table",
			"INSERT IGNORE $table",
			"INSERT IGNORE INTO $table",
			"INSERT INTO $table",
			"INSERT LOW_PRIORITY $table",
			"INSERT DELAYED $table",
			"INSERT HIGH_PRIORITY $table",
			"INSERT LOW_PRIORITY IGNORE $table",
			"INSERT LOW_PRIORITY INTO $table",
			"INSERT LOW_PRIORITY IGNORE INTO $table",

			"REPLACE $table",
			"REPLACE INTO $table",
			"REPLACE LOW_PRIORITY $table",
			"REPLACE DELAYED $table",
			"REPLACE LOW_PRIORITY INTO $table",

			"UPDATE LOW_PRIORITY $table",
			"UPDATE LOW_PRIORITY IGNORE $table",

			"DELETE $table",
			"DELETE IGNORE $table",
			"DELETE IGNORE FROM $table",
			"DELETE FROM $table",
			"DELETE LOW_PRIORITY $table",
			"DELETE QUICK $table",
			"DELETE IGNORE $table",
			"DELETE LOW_PRIORITY FROM $table",
			"DELETE a FROM $table a",
			"DELETE `a` FROM $table a",

			// Extended.
			"EXPLAIN SELECT * FROM $table",
			"EXPLAIN EXTENDED SELECT * FROM $table",
			"EXPLAIN EXTENDED SELECT * FROM `$table`",

			"DESCRIBE $table",
			"DESC $table",
			"EXPLAIN $table",
			"HANDLER $table",

			"LOCK TABLE $table",
			"LOCK TABLES $table",
			"UNLOCK TABLE $table",

			"RENAME TABLE $table",
			"OPTIMIZE TABLE $table",
			"BACKUP TABLE $table",
			"RESTORE TABLE $table",
			"CHECK TABLE $table",
			"CHECKSUM TABLE $table",
			"ANALYZE TABLE $table",
			"REPAIR TABLE $table",

			"TRUNCATE $table",
			"TRUNCATE TABLE $table",

			"CREATE TABLE $table",
			"CREATE TEMPORARY TABLE $table",
			"CREATE TABLE IF NOT EXISTS $table",

			"ALTER TABLE $table",
			"ALTER IGNORE TABLE $table",

			"DROP TABLE $table",
			"DROP TABLE IF EXISTS $table",

			"CREATE INDEX foo(bar(20)) ON $table",
			"CREATE UNIQUE INDEX foo(bar(20)) ON $table",
			"CREATE FULLTEXT INDEX foo(bar(20)) ON $table",
			"CREATE SPATIAL INDEX foo(bar(20)) ON $table",

			"DROP INDEX foo ON $table",

			"LOAD DATA INFILE 'wp.txt' INTO TABLE $table",
			"LOAD DATA LOW_PRIORITY INFILE 'wp.txt' INTO TABLE $table",
			"LOAD DATA CONCURRENT INFILE 'wp.txt' INTO TABLE $table",
			"LOAD DATA LOW_PRIORITY LOCAL INFILE 'wp.txt' INTO TABLE $table",
			"LOAD DATA INFILE 'wp.txt' REPLACE INTO TABLE $table",
			"LOAD DATA INFILE 'wp.txt' IGNORE INTO TABLE $table",

			"GRANT ALL ON TABLE $table",
			"REVOKE ALL ON TABLE $table",

			"SHOW COLUMNS FROM $table",
			"SHOW FULL COLUMNS FROM $table",
			"SHOW CREATE TABLE $table",
			"SHOW INDEX FROM $table",

			// @ticket 32763
			'SELECT ' . str_repeat( 'a', 10000 ) . " FROM (SELECT * FROM $table) as subquery",
		);

		$querycount = count( $queries );
		for ( $ii = 0; $ii < $querycount; $ii++ ) {
			foreach ( $more_tables as $name => $expected_name ) {
				$new_query = str_replace( $table, $name, $queries[ $ii ] );
				$queries[] = array( $new_query, $expected_name );
			}

			$queries[ $ii ] = array( $queries[ $ii ], $table );
		}
		return $queries;
	}

	/**
	 * @dataProvider data_get_table_from_query
	 * @ticket 21212
	 */
	public function test_get_table_from_query( $query, $table ) {
		$this->assertSame( $table, self::$_wpdb->get_table_from_query( $query ) );
	}

	public function data_get_table_from_query_false() {
		$table = 'a_test_table_name';
		return array(
			array( "LOL THIS ISN'T EVEN A QUERY $table" ),
		);
	}

	/**
	 * @dataProvider data_get_table_from_query_false
	 * @ticket 21212
	 */
	public function test_get_table_from_query_false( $query ) {
		$this->assertFalse( self::$_wpdb->get_table_from_query( $query ) );
	}

	/**
	 * @ticket 38751
	 */
	public function data_get_escaped_table_from_show_query() {
		return array(
			// Equality.
			array( "SHOW TABLE STATUS WHERE Name = 'test_name'", 'test_name' ),
			array( 'SHOW TABLE STATUS WHERE NAME="test_name"', 'test_name' ),
			array( 'SHOW TABLES WHERE Name = "test_name"', 'test_name' ),
			array( "SHOW FULL TABLES WHERE Name='test_name'", 'test_name' ),

			// LIKE.
			array( "SHOW TABLE STATUS LIKE 'test\_prefix\_%'", 'test_prefix_' ),
			array( 'SHOW TABLE STATUS LIKE "test\_prefix\_%"', 'test_prefix_' ),
			array( "SHOW TABLES LIKE 'test\_prefix\_%'", 'test_prefix_' ),
			array( 'SHOW FULL TABLES LIKE "test\_prefix\_%"', 'test_prefix_' ),
		);
	}

	/**
	 * @dataProvider data_get_escaped_table_from_show_query
	 * @ticket 38751
	 */
	public function test_get_escaped_table_from_show_query( $query, $table ) {
		$this->assertSame( $table, self::$_wpdb->get_table_from_query( $query ) );
	}

	/**
	 * @ticket 21212
	 */
	public function data_process_field_formats() {
		$core_db_fields_no_format_specified = array(
			array(
				'post_content' => 'foo',
				'post_parent'  => 0,
			),
			null,
			array(
				'post_content' => array(
					'value'  => 'foo',
					'format' => '%s',
				),
				'post_parent'  => array(
					'value'  => 0,
					'format' => '%d',
				),
			),
		);

		$core_db_fields_formats_specified = array(
			array(
				'post_content' => 'foo',
				'post_parent'  => 0,
			),
			array( '%d', '%s' ), // These override core field_types.
			array(
				'post_content' => array(
					'value'  => 'foo',
					'format' => '%d',
				),
				'post_parent'  => array(
					'value'  => 0,
					'format' => '%s',
				),
			),
		);

		$misc_fields_no_format_specified = array(
			array(
				'this_is_not_a_core_field' => 'foo',
				'this_is_not_either'       => 0,
			),
			null,
			array(
				'this_is_not_a_core_field' => array(
					'value'  => 'foo',
					'format' => '%s',
				),
				'this_is_not_either'       => array(
					'value'  => 0,
					'format' => '%s',
				),
			),
		);

		$misc_fields_formats_specified = array(
			array(
				'this_is_not_a_core_field' => 0,
				'this_is_not_either'       => 1.2,
			),
			array( '%d', '%f' ),
			array(
				'this_is_not_a_core_field' => array(
					'value'  => 0,
					'format' => '%d',
				),
				'this_is_not_either'       => array(
					'value'  => 1.2,
					'format' => '%f',
				),
			),
		);

		$misc_fields_insufficient_formats_specified = array(
			array(
				'this_is_not_a_core_field' => 0,
				'this_is_not_either'       => 's',
				'nor_this'                 => 1,
			),
			array( '%d', '%s' ), // The first format is used for the third.
			array(
				'this_is_not_a_core_field' => array(
					'value'  => 0,
					'format' => '%d',
				),
				'this_is_not_either'       => array(
					'value'  => 's',
					'format' => '%s',
				),
				'nor_this'                 => array(
					'value'  => 1,
					'format' => '%d',
				),
			),
		);

		$vars = get_defined_vars();
		// Push the variable name onto the end for assertSame() $message.
		foreach ( $vars as $var_name => $var ) {
			$vars[ $var_name ][] = $var_name;
		}
		return array_values( $vars );
	}

	/**
	 * @dataProvider data_process_field_formats
	 * @ticket 21212
	 */
	public function test_process_field_formats( $data, $format, $expected, $message ) {
		$actual = self::$_wpdb->process_field_formats( $data, $format );
		$this->assertSame( $expected, $actual, $message );
	}

	/**
	 * @ticket 21212
	 */
	public function test_process_fields() {
		global $wpdb;

		if ( $wpdb->charset ) {
			$expected_charset = $wpdb->charset;
		} else {
			$expected_charset = $wpdb->get_col_charset( $wpdb->posts, 'post_content' );
		}

		if ( ! in_array( $expected_charset, array( 'utf8', 'utf8mb4', 'latin1' ), true ) ) {
			$this->markTestSkipped( 'This test only works with utf8, utf8mb4 or latin1 character sets.' );
		}

		$data     = array( 'post_content' => '¡foo foo foo!' );
		$expected = array(
			'post_content' => array(
				'value'   => '¡foo foo foo!',
				'format'  => '%s',
				'charset' => $expected_charset,
				'length'  => $wpdb->get_col_length( $wpdb->posts, 'post_content' ),
			),
		);

		$this->assertSame( $expected, self::$_wpdb->process_fields( $wpdb->posts, $data, null ) );
	}

	/**
	 * @ticket 21212
	 * @depends test_process_fields
	 */
	public function test_process_fields_on_nonexistent_table( $data ) {
		self::$_wpdb->suppress_errors( true );
		$data = array( 'post_content' => '¡foo foo foo!' );
		$this->assertFalse( self::$_wpdb->process_fields( 'nonexistent_table', $data, null ) );
		self::$_wpdb->suppress_errors( false );
	}

	/**
	 * @ticket 21212
	 */
	public function test_pre_get_table_charset_filter() {
		add_filter( 'pre_get_table_charset', array( $this, 'filter_pre_get_table_charset' ), 10, 2 );
		$charset = self::$_wpdb->get_table_charset( 'some_table' );
		remove_filter( 'pre_get_table_charset', array( $this, 'filter_pre_get_table_charset' ), 10 );

		$this->assertSame( $charset, 'fake_charset' );
	}
	public function filter_pre_get_table_charset( $charset, $table ) {
		return 'fake_charset';
	}

	/**
	 * @ticket 21212
	 */
	public function test_pre_get_col_charset_filter() {
		add_filter( 'pre_get_col_charset', array( $this, 'filter_pre_get_col_charset' ), 10, 3 );
		$charset = self::$_wpdb->get_col_charset( 'some_table', 'some_col' );
		remove_filter( 'pre_get_col_charset', array( $this, 'filter_pre_get_col_charset' ), 10 );

		$this->assertSame( $charset, 'fake_col_charset' );
	}
	public function filter_pre_get_col_charset( $charset, $table, $column ) {
		return 'fake_col_charset';
	}

	/**
	 * @dataProvider data_process_single_field_invalid_data
	 * @dataProvider data_process_multiple_fields_invalid_data
	 *
	 * @ticket 32315
	 *
	 * @covers wpdb::process_fields
	 *
	 * @param array  $data           Data to process.
	 * @param string $errored_fields Expected fields in the error message.
	 */
	public function test_process_fields_value_too_long_for_field( array $data, $errored_fields ) {
		global $wpdb;

		$this->assertFalse( self::$_wpdb->process_fields( $wpdb->posts, $data, null ) );
		$this->assertSame( $this->get_db_error_value_too_long( $errored_fields ), self::$_wpdb->last_error );
	}

	/**
	 * @dataProvider data_process_single_field_invalid_data
	 *
	 * @ticket 32315
	 *
	 * @covers wpdb::insert
	 *
	 * @param array  $data           Data to process.
	 * @param string $errored_fields Expected fields in the error message.
	 */
	public function test_insert_value_too_long_for_field( array $data, $errored_fields ) {
		global $wpdb;

		$this->assertFalse( $wpdb->insert( $wpdb->posts, $data ) );
		$this->assertSame( $this->get_db_error_value_too_long( $errored_fields ), $wpdb->last_error );
	}

	/**
	 * @dataProvider data_process_single_field_invalid_data
	 *
	 * @ticket 32315
	 *
	 * @covers wpdb::replace
	 *
	 * @param array  $data           Data to process.
	 * @param string $errored_fields Expected fields in the error message.
	 */
	public function test_replace_value_too_long_for_field( array $data, $errored_fields ) {
		global $wpdb;

		$this->assertFalse( $wpdb->replace( $wpdb->posts, $data ) );
		$this->assertSame( $this->get_db_error_value_too_long( $errored_fields ), $wpdb->last_error );
	}

	/**
	 * @dataProvider data_process_single_field_invalid_data
	 *
	 * @ticket 32315
	 *
	 * @covers wpdb::update
	 *
	 * @param array  $data           Data to process.
	 * @param string $errored_fields Expected fields in the error message.
	 */
	public function test_update_value_too_long_for_field( array $data, $errored_fields ) {
		global $wpdb;

		$this->assertFalse( $wpdb->update( $wpdb->posts, $data, array() ) );
		$this->assertSame( $this->get_db_error_value_too_long( $errored_fields ), $wpdb->last_error );
	}

	/**
	 * @dataProvider data_process_single_field_invalid_data
	 *
	 * @ticket 32315
	 *
	 * @covers wpdb::delete
	 *
	 * @param array  $data           Data to process.
	 * @param string $errored_fields Expected fields in the error message.
	 */
	public function test_delete_value_too_long_for_field( array $data, $errored_fields ) {
		global $wpdb;

		$this->assertFalse( $wpdb->delete( $wpdb->posts, $data, array() ) );
		$this->assertSame( $this->get_db_error_value_too_long( $errored_fields ), $wpdb->last_error );
	}

	/**
	 * Assert the error message matches the fields.
	 *
	 * @param string $errored_fields Expected fields in the error message.
	 */
	private function get_db_error_value_too_long( $errored_fields ) {
		if ( str_contains( $errored_fields, ', ' ) ) {
			return sprintf(
				'WordPress database error: Processing the values for the following fields failed: %s. ' .
				'The supplied values may be too long or contain invalid data.',
				$errored_fields
			);
		}
		return sprintf(
			'WordPress database error: Processing the value for the following field failed: %s. ' .
			'The supplied value may be too long or contains invalid data.',
			$errored_fields
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_process_single_field_invalid_data() {
		return array(
			'too long'      => array(
				'data'           => array( 'post_status' => str_repeat( 'a', 21 ) ),
				'errored_fields' => 'post_status',
			),
			'invalid chars' => array(
				'data'           => array( 'post_status' => "\xF5" ),
				'errored_fields' => 'post_status',
			),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_process_multiple_fields_invalid_data() {
		return array(
			'too long'      => array(
				'data'           => array(
					'post_status'  => str_repeat( 'a', 21 ),
					'post_content' => "\xF5",
				),
				'errored_fields' => 'post_status, post_content',
			),
			'invalid chars' => array(
				'data'           => array(
					'post_status' => "\xF5",
					'post_name'   => str_repeat( "\xF5", 21 ),
				),
				'errored_fields' => 'post_status, post_name',
			),
		);
	}

	/**
	 * @ticket 32315
	 */
	public function test_query_value_contains_invalid_chars() {
		global $wpdb;

		$this->assertFalse(
			$wpdb->query( "INSERT INTO {$wpdb->posts} (post_status) VALUES ('\xF5')" )
		);

		$this->assertSame(
			'WordPress database error: Could not perform query because it contains invalid data.',
			$wpdb->last_error
		);
	}

	/**
	 * @ticket 15158
	 */
	public function test_null_insert() {
		global $wpdb;

		$key = 'null_insert_key';

		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'meta_key'   => $key,
				'meta_value' => null,
			),
			array( '%s', '%s' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key=%s", $key ) );

		$this->assertNull( $row->meta_value );
	}

	/**
	 * @ticket 15158
	 */
	public function test_null_update_value() {
		global $wpdb;

		$key   = 'null_update_value_key';
		$value = 'null_update_value_key';

		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'meta_key'   => $key,
				'meta_value' => $value,
			),
			array( '%s', '%s' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key=%s", $key ) );

		$this->assertSame( $value, $row->meta_value );

		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => null ),
			array(
				'meta_key'   => $key,
				'meta_value' => $value,
			),
			array( '%s' ),
			array( '%s', '%s' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key=%s", $key ) );

		$this->assertNull( $row->meta_value );
	}

	/**
	 * @ticket 15158
	 */
	public function test_null_update_where() {
		global $wpdb;

		$key   = 'null_update_where_key';
		$value = 'null_update_where_key';

		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'meta_key'   => $key,
				'meta_value' => null,
			),
			array( '%s', '%s' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key=%s", $key ) );

		$this->assertNull( $row->meta_value );

		$wpdb->update(
			$wpdb->postmeta,
			array( 'meta_value' => $value ),
			array(
				'meta_key'   => $key,
				'meta_value' => null,
			),
			array( '%s' ),
			array( '%s', '%s' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key=%s", $key ) );

		$this->assertSame( $value, $row->meta_value );
	}

	/**
	 * @ticket 15158
	 */
	public function test_null_delete() {
		global $wpdb;

		$key   = 'null_update_where_key';
		$value = 'null_update_where_key';

		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'meta_key'   => $key,
				'meta_value' => null,
			),
			array( '%s', '%s' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key=%s", $key ) );

		$this->assertNull( $row->meta_value );

		$wpdb->delete(
			$wpdb->postmeta,
			array(
				'meta_key'   => $key,
				'meta_value' => null,
			),
			array( '%s', '%s' )
		);

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key=%s", $key ) );

		$this->assertNull( $row );
	}

	/**
	 * @ticket 34903
	 */
	public function test_close() {
		global $wpdb;

		$this->assertTrue( $wpdb->close() );
		$this->assertFalse( $wpdb->close() );

		$this->assertFalse( $wpdb->ready );
		$this->assertFalse( $wpdb->has_connected );

		$wpdb->check_connection();

		$this->assertTrue( $wpdb->close() );

		$wpdb->check_connection();
	}

	/**
	 * @ticket 36917
	 */
	public function test_charset_not_determined_when_disconnected() {
		global $wpdb;

		$charset = 'utf8';
		$collate = 'this_isnt_a_collation';

		$wpdb->close();

		$result = $wpdb->determine_charset( $charset, $collate );

		$this->assertSame( compact( 'charset', 'collate' ), $result );

		$wpdb->check_connection();
	}

	/**
	 * @ticket 36917
	 */
	public function test_charset_switched_to_utf8mb4() {
		global $wpdb;

		$charset = 'utf8';
		$collate = 'utf8_general_ci';

		$result = $wpdb->determine_charset( $charset, $collate );

		$this->assertSame( 'utf8mb4', $result['charset'] );
	}

	/**
	 * @ticket 32105
	 * @ticket 36917
	 */
	public function test_collate_switched_to_utf8mb4_520() {
		global $wpdb;

		if ( ! $wpdb->has_cap( 'utf8mb4_520' ) ) {
			$this->markTestSkipped( 'This test requires utf8mb4_520 support.' );
		}

		$charset = 'utf8';
		$collate = 'utf8_general_ci';

		$result = $wpdb->determine_charset( $charset, $collate );

		$this->assertSame( 'utf8mb4_unicode_520_ci', $result['collate'] );
	}

	/**
	 * @ticket 32405
	 * @ticket 36917
	 */
	public function test_non_unicode_collations() {
		global $wpdb;

		$charset = 'utf8';
		$collate = 'utf8_swedish_ci';

		$result = $wpdb->determine_charset( $charset, $collate );

		$this->assertSame( 'utf8mb4_swedish_ci', $result['collate'] );
	}

	/**
	 * @dataProvider data_prepare_with_placeholders
	 */
	public function test_prepare_with_placeholders_and_individual_args( $sql, $values, $incorrect_usage, $expected ) {
		global $wpdb;

		if ( is_string( $incorrect_usage ) || true === $incorrect_usage ) {
			$this->setExpectedIncorrectUsage( 'wpdb::prepare' );
		}

		if ( ! is_array( $values ) ) {
			$values = array( $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL
		$sql = $wpdb->prepare( $sql, ...$values );
		$this->assertSame( $expected, $sql, 'The expected SQL does not match' );

		if ( is_string( $incorrect_usage ) && array_key_exists( 'wpdb::prepare', $this->caught_doing_it_wrong ) ) {
			$this->assertStringContainsString( $incorrect_usage, $this->caught_doing_it_wrong['wpdb::prepare'], 'The "_doing_it_wrong" message does not match' );
		}
	}

	/**
	 * @dataProvider data_prepare_with_placeholders
	 */
	public function test_prepare_with_placeholders_and_array_args( $sql, $values, $incorrect_usage, $expected ) {
		global $wpdb;

		if ( is_string( $incorrect_usage ) || true === $incorrect_usage ) {
			$this->setExpectedIncorrectUsage( 'wpdb::prepare' );
		}

		if ( ! is_array( $values ) ) {
			$values = array( $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL
		$sql = $wpdb->prepare( $sql, $values );
		$this->assertSame( $expected, $sql, 'The expected SQL does not match' );

		if ( is_string( $incorrect_usage ) && array_key_exists( 'wpdb::prepare', $this->caught_doing_it_wrong ) ) {
			$this->assertStringContainsString( $incorrect_usage, $this->caught_doing_it_wrong['wpdb::prepare'], 'The "_doing_it_wrong" message does not match' );
		}
	}

	public function data_prepare_with_placeholders() {
		global $wpdb;

		$placeholder_escape = $wpdb->placeholder_escape();

		return array(
			array(
				'%5s',   // SQL to prepare.
				'foo',   // Value to insert in the SQL.
				false,   // Whether to expect an incorrect usage error or not.
				'  foo', // Expected output.
			),
			array(
				'%1$d %%% % %%1$d%% %%%1$d%%',
				1,
				true,
				"1 {$placeholder_escape}{$placeholder_escape} {$placeholder_escape} {$placeholder_escape}1\$d{$placeholder_escape} {$placeholder_escape}1{$placeholder_escape}",
			),
			array(
				'%-5s',
				'foo',
				false,
				'foo  ',
			),
			array(
				'%05s',
				'foo',
				false,
				'00foo',
			),
			array(
				"%'#5s",
				'foo',
				false,
				'##foo',
			),
			array(
				'%.3s',
				'foobar',
				false,
				'foo',
			),
			array(
				'%.3f',
				5.123456,
				false,
				'5.123',
			),
			array(
				'%.3f',
				5.12,
				false,
				'5.120',
			),
			array(
				'%s',
				' %s ',
				false,
				"' {$placeholder_escape}s '",
			),
			array(
				'%1$s',
				' %s ',
				false,
				" {$placeholder_escape}s ",
			),
			array(
				'%1$s',
				' %1$s ',
				false,
				" {$placeholder_escape}1\$s ",
			),
			array(
				'%d %1$d %%% %',
				1,
				true,
				"1 1 {$placeholder_escape}{$placeholder_escape} {$placeholder_escape}",
			),
			array(
				'%d %2$s',
				array( 1, 'hello' ),
				false,
				'1 hello',
			),
			array(
				"'%s'",
				'hello',
				false,
				"'hello'",
			),
			array(
				'"%s"',
				'hello',
				false,
				"'hello'",
			),
			array(
				"%s '%1\$s'",
				'hello',
				true,
				"'hello' 'hello'",
			),
			array(
				"%s '%1\$s'",
				'hello',
				true,
				"'hello' 'hello'",
			),
			array(
				'%s "%1$s"',
				'hello',
				true,
				"'hello' \"hello\"",
			),
			array(
				"%%s %%'%1\$s'",
				'hello',
				false,
				"{$placeholder_escape}s {$placeholder_escape}'hello'",
			),
			array(
				'%%s %%"%1$s"',
				'hello',
				false,
				"{$placeholder_escape}s {$placeholder_escape}\"hello\"",
			),
			array(
				'%s',
				' %  s ',
				false,
				"' {$placeholder_escape}  s '",
			),
			array(
				'%%f %%"%1$f"',
				3,
				false,
				"{$placeholder_escape}f {$placeholder_escape}\"3.000000\"",
			),
			array(
				'WHERE second=\'%2$s\' AND first=\'%1$s\'',
				array( 'first arg', 'second arg' ),
				false,
				"WHERE second='second arg' AND first='first arg'",
			),
			array(
				'WHERE second=%2$d AND first=%1$d',
				array( 1, 2 ),
				false,
				'WHERE second=2 AND first=1',
			),
			array(
				"'%'%%s",
				'hello',
				true,
				"'{$placeholder_escape}'{$placeholder_escape}s",
			),

			/*
			 * @ticket 56933
			 * When preparing a '%%%s%%', test that the inserted value
			 * is not wrapped in single quotes between the 2 "%".
			 */
			array(
				'%%s %d',
				1,
				false,
				"{$placeholder_escape}s 1",
			),
			array(
				'%%%s',
				'hello',
				false,
				"{$placeholder_escape}hello",
			),
			array(
				'%%%%s',
				'hello',
				'The query does not contain the correct number of placeholders (0) for the number of arguments passed (1).',
				"{$placeholder_escape}{$placeholder_escape}s",
			),
			array(
				'%%%%%s',
				'hello',
				false,
				"{$placeholder_escape}{$placeholder_escape}hello",
			),
			array(
				'%%%s%%',
				'hello',
				false,
				"{$placeholder_escape}hello{$placeholder_escape}",
			),
			array(
				"'%'%%s%s",
				'hello',
				false,
				"'{$placeholder_escape}'{$placeholder_escape}s'hello'",
			),
			array(
				"'%'%%s %s",
				'hello',
				false,
				"'{$placeholder_escape}'{$placeholder_escape}s 'hello'",
			),
			array(
				"'%-'#5s' '%'#-+-5s'",
				array( 'hello', 'foo' ),
				false,
				"'hello' 'foo##'",
			),

			/*
			 * Before WP 6.2 the "force floats to be locale-unaware" RegEx didn't
			 * convert "%%%f" to "%%%F" (note the uppercase F).
			 * This was because it didn't check to see if the leading "%" was escaped.
			 * And because the "Escape any unescaped percents" RegEx used "[sdF]" in its
			 * negative lookahead assertion, when there was an odd number of "%", it added
			 * an extra "%", to give the fully escaped "%%%%f" (not a placeholder).
			 */
			array(
				'%f OR id = %d',
				array( 3, 5 ),
				false,
				'3.000000 OR id = 5',
			),
			array(
				'%%f OR id = %d',
				array( 5 ),
				false,
				"{$placeholder_escape}f OR id = 5",
			),
			array(
				'%%%f OR id = %d',
				array( 5 ),
				false,
				"{$placeholder_escape}{$placeholder_escape}f OR id = 5",
			),
			array(
				'%%%%f OR id = %d',
				array( 5 ),
				false,
				"{$placeholder_escape}{$placeholder_escape}f OR id = 5",
			),
			array(
				"WHERE id = %d AND content LIKE '%.4f'",
				array( 1, 2 ),
				false,
				"WHERE id = 1 AND content LIKE '2.0000'",
			),
			array(
				"WHERE id = %d AND content LIKE '%%.4f'",
				array( 1 ),
				false,
				"WHERE id = 1 AND content LIKE '{$placeholder_escape}.4f'",
			),
			array(
				"WHERE id = %d AND content LIKE '%%%.4f'",
				array( 1 ),
				false,
				"WHERE id = 1 AND content LIKE '{$placeholder_escape}{$placeholder_escape}.4f'",
			),
			array(
				"WHERE id = %d AND content LIKE '%%%%.4f'",
				array( 1 ),
				false,
				"WHERE id = 1 AND content LIKE '{$placeholder_escape}{$placeholder_escape}.4f'",
			),
			array(
				"WHERE id = %d AND content LIKE '%%%%%.4f'",
				array( 1 ),
				false,
				"WHERE id = 1 AND content LIKE '{$placeholder_escape}{$placeholder_escape}{$placeholder_escape}.4f'",
			),
			array(
				'%.4f',
				array( 1 ),
				false,
				'1.0000',
			),
			array(
				'%.4f OR id = %d',
				array( 1, 5 ),
				false,
				'1.0000 OR id = 5',
			),
			array(
				'%%.4f OR id = %d',
				array( 5 ),
				false,
				"{$placeholder_escape}.4f OR id = 5",
			),
			array(
				'%%%.4f OR id = %d',
				array( 5 ),
				false,
				"{$placeholder_escape}{$placeholder_escape}.4f OR id = 5",
			),
			array(
				'%%%%.4f OR id = %d',
				array( 5 ),
				false,
				"{$placeholder_escape}{$placeholder_escape}.4f OR id = 5",
			),
			array(
				'%%%%%.4f OR id = %d',
				array( 5 ),
				false,
				"{$placeholder_escape}{$placeholder_escape}{$placeholder_escape}.4f OR id = 5",
			),
			array(
				'WHERE id IN (%...d) AND lat > %f',
				array( array( 1, 2, 3 ), 5.678 ),
				false,
				'WHERE id IN (1,2,3) AND lat > 5.678000',
			),
			array(
				'WHERE id IN (%...d) AND lat > %%f',
				array( array( 1, 2, 3 ) ),
				false,
				"WHERE id IN (1,2,3) AND lat > {$placeholder_escape}f",
			),
			array(
				'WHERE id IN (%...d) AND lat > %%%f',
				array( array( 1, 2, 3 ) ),
				false,
				"WHERE id IN (1,2,3) AND lat > {$placeholder_escape}{$placeholder_escape}f",
			),
			array(
				'WHERE id IN (%...d) AND lat > %%%1$f',
				array( array( 1, 2, 3 ) ),
				false,
				"WHERE id IN (1,2,3) AND lat > {$placeholder_escape}{$placeholder_escape}1\$f",
			),

			/*
			 * @ticket 52506
			 * Adding an escape method for Identifiers (e.g. table/field names).
			 */
			array(
				'SELECT * FROM %i WHERE %i = %d;',
				array( 'my_table', 'my_field', 321 ),
				false,
				'SELECT * FROM `my_table` WHERE `my_field` = 321;',
			),
			array(
				'WHERE %i = %d;',
				array( 'evil_`_field', 321 ),
				false,
				'WHERE `evil_``_field` = 321;', // To quote the identifier itself, then you need to double the character, e.g. `a``b`.
			),
			array(
				'WHERE %i = %d;',
				array( 'evil_````````_field', 321 ),
				false,
				'WHERE `evil_````````````````_field` = 321;',
			),
			array(
				'WHERE %i = %d;',
				array( '``evil_field``', 321 ),
				false,
				'WHERE `````evil_field````` = 321;',
			),
			array(
				'WHERE %i = %d;',
				array( 'evil\'field', 321 ),
				false,
				'WHERE `evil\'field` = 321;',
			),
			array(
				'WHERE %i = %d;',
				array( 'evil_\``_field', 321 ),
				false,
				'WHERE `evil_\````_field` = 321;',
			),
			array(
				'WHERE %i = %d;',
				array( 'evil_%s_field', 321 ),
				false,
				"WHERE `evil_{$placeholder_escape}s_field` = 321;",
			),
			array(
				'WHERE %i = %d;',
				array( 'value`', 321 ),
				false,
				'WHERE `value``` = 321;',
			),
			array(
				'WHERE `%i = %d;',
				array( ' AND evil_value', 321 ),
				false,
				'WHERE `` AND evil_value` = 321;', // Won't run (SQL parse error: "Unclosed quote").
			),
			array(
				'WHERE %i` = %d;',
				array( 'evil_value -- ', 321 ),
				false,
				'WHERE `evil_value -- `` = 321;', // Won't run (SQL parse error: "Unclosed quote").
			),
			array(
				'WHERE `%i`` = %d;',
				array( ' AND true -- ', 321 ),
				false,
				'WHERE `` AND true -- ``` = 321;', // Won't run (Unknown column '').
			),
			array(
				'WHERE ``%i` = %d;',
				array( ' AND true -- ', 321 ),
				false,
				'WHERE ``` AND true -- `` = 321;', // Won't run (SQL parse error: "Unclosed quote").
			),
			array(
				'WHERE %2$i = %1$d;',
				array( '1', 'two' ),
				false,
				'WHERE `two` = 1;',
			),
			array(
				'WHERE \'%i\' = 1 AND "%i" = 2 AND `%i` = 3 AND ``%i`` = 4 AND %15i = 5',
				array( 'my_field1', 'my_field2', 'my_field3', 'my_field4', 'my_field5' ),
				false,
				'WHERE \'`my_field1`\' = 1 AND "`my_field2`" = 2 AND ``my_field3`` = 3 AND ```my_field4``` = 4 AND `      my_field5` = 5', // Does not remove any existing quotes, always adds it's own (safer).
			),
			array(
				'WHERE id = %d AND %i LIKE %2$s LIMIT 1',
				array( 123, 'field -- ', false ),
				'Arguments cannot be prepared as both an Identifier and Value. Found the following conflicts: %i and %2$s',
				null, // Should be rejected, otherwise the `%1$s` could use Identifier escaping, e.g. 'WHERE `field -- ` LIKE field --  LIMIT 1' (thanks @vortfu).
			),
			array(
				'WHERE %i LIKE %s LIMIT 1',
				array( "field' -- ", "field' -- " ),
				false,
				"WHERE `field' -- ` LIKE 'field\' -- ' LIMIT 1", // In contrast to the above, Identifier vs String escaping is used.
			),
			array(
				'WHERE %2$i IN ( %s , %s ) LIMIT 1',
				array( 'a', 'b' ),
				'Arguments cannot be prepared as both an Identifier and Value. Found the following conflicts: %2$i and %s',
				null,
			),
			array(
				'WHERE %1$i = %1$s',
				array( 'a', 'b' ),
				'Arguments cannot be prepared as both an Identifier and Value. Found the following conflicts: %1$i and %1$s',
				null,
			),
			array(
				'WHERE %1$i = %1$s OR %2$i = %2$s',
				array( 'a', 'b' ),
				'Arguments cannot be prepared as both an Identifier and Value. Found the following conflicts: %1$i and %1$s, %2$i and %2$s',
				null,
			),
			array(
				'WHERE %1$i = %1$s OR %2$i = %1$s',
				array( 'a', 'b' ),
				'Arguments cannot be prepared as both an Identifier and Value. Found the following conflicts: %1$i and %1$s and %1$s',
				null,
			),

			/*
			 * @ticket 54042.
			 * Support IN() operator with `%...d`.
			 */
			array(
				'id IN (%...d)',
				array( array( 1, 2, 3, 'not-int' ) ),
				false,
				'id IN (1,2,3,0)',
			),
			array(
				'id IN (%...f)',
				array( array( 1.1, 2, 3.3, 'not-float' ) ),
				false,
				'id IN (1.100000,2.000000,3.300000,0.000000)',
			),
			array(
				'type IN (%...s)',
				array( array( 'post', 'page' ) ),
				false,
				"type IN ('post','page')",
			),
			array(
				'SELECT %...i FROM %i WHERE id IN (%...d)',
				array( array( 'field_1', 'field_2', 'field`3' ), 'table', array( 1, 2, 'non-int' ) ),
				false,
				'SELECT `field_1`,`field_2`,`field``3` FROM `table` WHERE id IN (1,2,0)',
			),
			array(
				'id IN (%...d) AND type IN (%...s)',
				array( array( 4, 29, 51 ), array( 'post', 'page' ) ),
				false,
				"id IN (4,29,51) AND type IN ('post','page')",
			),
			array(
				'code IN (%...5s)',
				array( array( 1, 2, 3 ) ),
				false,
				"code IN ('    1','    2','    3')",
			),
			array(
				'id = %d AND code IN (%...2$5s)',
				array( 5, array( 'a', 'b', 'c' ) ),
				false,
				"id = 5 AND code IN ('    a','    b','    c')",
			),
			array(
				'id IN (%...d) AND (name = "%3$s" OR name = "%2$s")',
				array( array( 1, 2, 3 ), 'A', 'B' ),
				false,
				'id IN (1,2,3) AND (name = "B" OR name = "A")',
			),
			array(
				'id IN (%%...d)',
				array(),
				false,
				"id IN ({$placeholder_escape}...d)",
			),
			array(
				'id IN (%%%...d)',
				array( array( 1, 2, 3 ) ),
				false,
				"id IN ({$placeholder_escape}1,2,3)",
			),
			array(
				'id IN (%%%%...d)',
				null,
				'The query does not contain the correct number of placeholders (0) for the number of arguments passed (1).',
				"id IN ({$placeholder_escape}{$placeholder_escape}...d)",
			),
			array(
				'id IN (%...d) AND lat > %%%2$F',
				array( array( 1, 2, 3 ), 5 ),
				false,
				"id IN (1,2,3) AND lat > {$placeholder_escape}5.000000",
			),
			array(
				'id IN (%...d) AND lat > %%%2$f', // Note the lower case `f`, from the "force floats to be locale-unaware" RegEx issue (see above).
				array( array( 1, 2, 3 ) ),
				false,
				"id IN (1,2,3) AND lat > {$placeholder_escape}{$placeholder_escape}2\$f",
			),
			array(
				'id IN (%...d) AND lat > %%%1$F',
				array( array( 1, 2, 3 ) ),
				'The query does not contain the correct number of placeholders (2) for the number of arguments passed (1).',
				"id IN (1,2,3) AND lat > {$placeholder_escape}3.000000", // Should this also be considered $dual_use?
			),

			// TODO: Has a problem with argnum's
			// array(
			// 	'a IN (%...2$d) AND b IN (%...d) AND c IN (%...1$d)',
			// 	array( array( 1, 2, 3 ), array( 4, 5, 6 ), array( 7, 8, 9 ) ),
			// 	false,
			// 	'a IN (4,5,6) AND b IN (1,2,3) AND c IN (1,2,3)',
			// ),

			// TODO: Has a problem with $passed_as_array, and argnum
			// array(
			// 	'a IN (%...2$d) AND id = %1$d',
			// 	array( 5, array( 1, 2, 3 ) ),
			// 	false,
			// 	'a IN (1,2,3) AND id = 5',
			// ),

			// TODO: Mixing of variable types, e.g. "%...d OR name = %1$s", ref $dual_use ?

		);
	}

	/**
	 * The wpdb->allow_unsafe_unquoted_parameters is true (for now), purely for backwards compatibility reasons.
	 *
	 * @ticket 52506
	 *
	 * @dataProvider data_prepare_should_respect_the_allow_unsafe_unquoted_parameters_property
	 *
	 * @covers wpdb::prepare
	 *
	 * @param bool   $allow    Whether to allow unsafe unquoted parameters.
	 * @param string $sql      The SQL to prepare.
	 * @param array  $values   The values for prepare.
	 * @param string $expected The expected prepared parameters.
	 */
	public function test_prepare_should_respect_the_allow_unsafe_unquoted_parameters_property( $allow, $sql, $values, $expected ) {
		global $wpdb;

		$default = $wpdb->allow_unsafe_unquoted_parameters;

		$property = new ReflectionProperty( $wpdb, 'allow_unsafe_unquoted_parameters' );
		$property->setAccessible( true );
		$property->setValue( $wpdb, $allow );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$actual = $wpdb->prepare( $sql, $values );

		// Reset.
		$property->setValue( $wpdb, $default );
		$property->setAccessible( false );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_prepare_should_respect_the_allow_unsafe_unquoted_parameters_property().
	 *
	 * @return array[]
	 */
	public function data_prepare_should_respect_the_allow_unsafe_unquoted_parameters_property() {
		global $wpdb;

		$placeholder_escape = $wpdb->placeholder_escape();

		return array(

			'numbered-true-1'  => array(
				'allow'    => true,
				'sql'      => 'WHERE (%i = %s) OR (%3$i = %4$s)',
				'values'   => array( 'field_a', 'string_a', 'field_b', 'string_b' ),
				'expected' => 'WHERE (`field_a` = \'string_a\') OR (`field_b` = string_b)',
			),
			'numbered-false-1' => array(
				'allow'    => false,
				'sql'      => 'WHERE (%i = %s) OR (%3$i = %4$s)',
				'values'   => array( 'field_a', 'string_a', 'field_b', 'string_b' ),
				'expected' => 'WHERE (`field_a` = \'string_a\') OR (`field_b` = \'string_b\')',
			),
			'numbered-true-2'  => array(
				'allow'    => true,
				'sql'      => 'WHERE (%i = %s) OR (%3$i = %4$s)',
				'values'   => array( 'field_a', 'string_a', 'field_b', '0 OR EvilSQL' ),
				'expected' => 'WHERE (`field_a` = \'string_a\') OR (`field_b` = 0 OR EvilSQL)',
			),
			'numbered-false-2' => array(
				'allow'    => false,
				'sql'      => 'WHERE (%i = %s) OR (%3$i = %4$s)',
				'values'   => array( 'field_a', 'string_a', 'field_b', '0 OR EvilSQL' ),
				'expected' => 'WHERE (`field_a` = \'string_a\') OR (`field_b` = \'0 OR EvilSQL\')',
			),

			'format-true-1'    => array(
				'allow'    => true,
				'sql'      => 'WHERE (%10i = %10s)',
				'values'   => array( 'field_a', 'string_a' ),
				'expected' => 'WHERE (`   field_a` =   string_a)',
			),
			'format-false-1'   => array(
				'allow'    => false,
				'sql'      => 'WHERE (%10i = %10s)',
				'values'   => array( 'field_a', 'string_a' ),
				'expected' => 'WHERE (`   field_a` = \'  string_a\')',
			),
			'format-true-2'    => array(
				'allow'    => true,
				'sql'      => 'WHERE (%10i = %10s)',
				'values'   => array( 'field_a', '0 OR EvilSQL' ),
				'expected' => 'WHERE (`   field_a` = 0 OR EvilSQL)',
			),
			'format-false-2'   => array(
				'allow'    => false,
				'sql'      => 'WHERE (%10i = %10s)',
				'values'   => array( 'field_a', '0 OR EvilSQL' ),
				'expected' => 'WHERE (`   field_a` = \'0 OR EvilSQL\')',
			),

			'escaped-true-1'   => array(
				'allow'    => true,
				'sql'      => 'SELECT 9%%%s',
				'values'   => array( '7' ),
				'expected' => "SELECT 9{$placeholder_escape}7", // SELECT 9%7.
			),
			'escaped-false-1'  => array(
				'allow'    => false,
				'sql'      => 'SELECT 9%%%s',
				'values'   => array( '7' ),
				'expected' => "SELECT 9{$placeholder_escape}'7'", // SELECT 9%'7'.
			),
			'escaped-true-2'   => array(
				'allow'    => true,
				'sql'      => 'SELECT 9%%%s',
				'values'   => array( '7 OR EvilSQL' ),
				'expected' => "SELECT 9{$placeholder_escape}7 OR EvilSQL", // SELECT 9%7 OR EvilSQL.
			),
			'escaped-false-2'  => array(
				'allow'    => false,
				'sql'      => 'SELECT 9%%%s',
				'values'   => array( '7 OR EvilSQL' ),
				'expected' => "SELECT 9{$placeholder_escape}'7 OR EvilSQL'", // SELECT 9%'7 OR EvilSQL'.
			),

		);
	}

	/**
	 * @ticket 54042
	 *
	 * @covers wpdb::prepare
	 */
	public function test_allow_unsafe_unquoted_parameters() {
		global $wpdb;

		$sql    = 'WHERE (%i = %s) OR (%10i = %10s) OR (%5$i = %6$s)';
		$values = array( 'field_a', 'string_a', 'field_b', 'string_b', 'field_c', 'string_c' );

		$default = $wpdb->allow_unsafe_unquoted_parameters;

		$property = new ReflectionProperty( $wpdb, 'allow_unsafe_unquoted_parameters' );
		$property->setAccessible( true );

		$property->setValue( $wpdb, true );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$part = $wpdb->prepare( $sql, $values );
		$this->assertSame( 'WHERE (`field_a` = \'string_a\') OR (`   field_b` =   string_b) OR (`field_c` = string_c)', $part ); // Unsafe, unquoted parameters.

		$property->setValue( $wpdb, false );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$part = $wpdb->prepare( $sql, $values );
		$this->assertSame( 'WHERE (`field_a` = \'string_a\') OR (`   field_b` = \'  string_b\') OR (`field_c` = \'string_c\')', $part );

		$property->setValue( $wpdb, $default );
		$property->setAccessible( false );

	}

	/**
	 * @dataProvider data_escape_and_prepare
	 */
	public function test_escape_and_prepare( $escape, $sql, $values, $incorrect_usage, $expected ) {
		global $wpdb;

		if ( is_string( $incorrect_usage ) || true === $incorrect_usage ) {
			$this->setExpectedIncorrectUsage( 'wpdb::prepare' );
		}

		$escape = esc_sql( $escape );

		$sql = str_replace( '{ESCAPE}', $escape, $sql );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$actual = $wpdb->prepare( $sql, $values );

		$this->assertSame( $expected, $actual );

		if ( is_string( $incorrect_usage ) && array_key_exists( 'wpdb::prepare', $this->caught_doing_it_wrong ) ) {
			$this->assertStringContainsString( $incorrect_usage, $this->caught_doing_it_wrong['wpdb::prepare'] );
		}
	}

	public function data_escape_and_prepare() {
		global $wpdb;

		$placeholder_escape = $wpdb->placeholder_escape();

		return array(
			array(
				'%s',                                  // String to pass through esc_url().
				' {ESCAPE} ',                          // Query to insert the output of esc_url() into, replacing "{ESCAPE}".
				'foo',                                 // Data to send to prepare().
				true,                                  // Whether to expect an incorrect usage error or not.
				" {$placeholder_escape}s ",    // Expected output.
			),
			array(
				'foo%sbar',
				"SELECT * FROM bar WHERE foo='{ESCAPE}' OR baz=%s",
				array( ' SQLi -- -', 'pewpewpew' ),
				true,
				null,
			),
			array(
				'%s',
				' %s {ESCAPE} ',
				'foo',
				false,
				" 'foo' {$placeholder_escape}s ",
			),
		);
	}

	/**
	 * @expectedIncorrectUsage wpdb::prepare
	 */
	public function test_double_prepare() {
		global $wpdb;

		$part = $wpdb->prepare( ' AND meta_value = %s', ' %s ' );
		$this->assertStringNotContainsString( '%s', $part );
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$query = $wpdb->prepare( 'SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s $part', array( 'foo', 'bar' ) );
		$this->assertNull( $query );
	}

	public function test_prepare_numeric_placeholders_float_args() {
		global $wpdb;

		$actual = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
			'WHERE second=%2$f AND first=%1$f',
			1.1,
			2.2
		);

		/* Floats can be right padded, need to assert differently */
		$this->assertStringContainsString( ' first=1.1', $actual );
		$this->assertStringContainsString( ' second=2.2', $actual );
	}

	public function test_prepare_numeric_placeholders_float_array() {
		global $wpdb;

		$actual = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder
			'WHERE second=%2$f AND first=%1$f',
			array( 1.1, 2.2 )
		);

		/* Floats can be right padded, need to assert differently */
		$this->assertStringContainsString( ' first=1.1', $actual );
		$this->assertStringContainsString( ' second=2.2', $actual );
	}

	public function test_query_unescapes_placeholders() {
		global $wpdb;

		$value = ' %s ';

		$wpdb->query( "CREATE TABLE {$wpdb->prefix}test_placeholder( a VARCHAR(100) );" );
		$sql = $wpdb->prepare( "INSERT INTO {$wpdb->prefix}test_placeholder VALUES(%s)", $value );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );

		$actual = $wpdb->get_var( "SELECT a FROM {$wpdb->prefix}test_placeholder" );

		$wpdb->query( "DROP TABLE {$wpdb->prefix}test_placeholder" );

		$this->assertStringNotContainsString( '%s', $sql );
		$this->assertSame( $value, $actual );
	}

	public function test_esc_sql_with_unsupported_placeholder_type() {
		global $wpdb;

		$sql = $wpdb->prepare( ' %s %1$c ', 'foo' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare( " $sql %s ", 'foo' );

		$this->assertSame( "  'foo' {$wpdb->placeholder_escape()}1\$c  'foo' ", $sql );
	}

	/**
	 * @dataProvider data_parse_db_host
	 * @ticket 41722
	 * @ticket 54877
	 */
	public function test_parse_db_host( $host_string, $expect_bail, $host, $port, $socket, $is_ipv6 ) {
		global $wpdb;
		$data = $wpdb->parse_db_host( $host_string );
		if ( $expect_bail ) {
			$this->assertFalse( $data );
		} else {
			$this->assertIsArray( $data );

			list( $parsed_host, $parsed_port, $parsed_socket, $parsed_is_ipv6 ) = $data;

			$this->assertSame( $host, $parsed_host );
			$this->assertSame( $port, $parsed_port );
			$this->assertSame( $socket, $parsed_socket );
			$this->assertSame( $is_ipv6, $parsed_is_ipv6 );
		}
	}

	public function data_parse_db_host() {
		return array(
			array(
				'',    // DB_HOST.
				false, // Expect parse_db_host to bail for this hostname.
				'',    // Parsed host.
				null,  // Parsed port.
				null,  // Parsed socket.
				false, // $is_ipv6.
			),
			array(
				':3306',
				false,
				'',
				3306,
				null,
				false,
			),
			array(
				':/tmp/mysql.sock',
				false,
				'',
				null,
				'/tmp/mysql.sock',
				false,
			),
			array(
				':/tmp/mysql:with_colon.sock',
				false,
				'',
				null,
				'/tmp/mysql:with_colon.sock',
				false,
			),
			array(
				'127.0.0.1',
				false,
				'127.0.0.1',
				null,
				null,
				false,
			),
			array(
				'127.0.0.1:port_as_string',
				false,
				'127.0.0.1',
				null,
				null,
				false,
			),
			array(
				'127.0.0.1:3306',
				false,
				'127.0.0.1',
				3306,
				null,
				false,
			),
			array(
				'127.0.0.1:3306:/tmp/mysql:with_colon.sock',
				false,
				'127.0.0.1',
				3306,
				'/tmp/mysql:with_colon.sock',
				false,
			),
			array(
				'example.com',
				false,
				'example.com',
				null,
				null,
				false,
			),
			array(
				'example.com:port_as_string',
				false,
				'example.com',
				null,
				null,
				false,
			),
			array(
				'example.com:3306',
				false,
				'example.com',
				3306,
				null,
				false,
			),
			array(
				'localhost',
				false,
				'localhost',
				null,
				null,
				false,
			),
			array(
				'localhost:port_as_string',
				false,
				'localhost',
				null,
				null,
				false,
			),
			array(
				'localhost:/tmp/mysql.sock',
				false,
				'localhost',
				null,
				'/tmp/mysql.sock',
				false,
			),
			array(
				'localhost:/tmp/mysql:with_colon.sock',
				false,
				'localhost',
				null,
				'/tmp/mysql:with_colon.sock',
				false,
			),
			array(
				'localhost:port_as_string:/tmp/mysql:with_colon.sock',
				false,
				'localhost',
				null,
				'/tmp/mysql:with_colon.sock',
				false,
			),
			array(
				'0000:0000:0000:0000:0000:0000:0000:0001',
				false,
				'0000:0000:0000:0000:0000:0000:0000:0001',
				null,
				null,
				true,
			),
			array(
				'::1',
				false,
				'::1',
				null,
				null,
				true,
			),
			array(
				'[::1]',
				false,
				'::1',
				null,
				null,
				true,
			),
			array(
				'[::1]:3306',
				false,
				'::1',
				3306,
				null,
				true,
			),
			array(
				'[::1]:port_as_string',
				false,
				'::1',
				null,
				null,
				true,
			),
			array(
				'[::1]:3306:/tmp/mysql:with_colon.sock',
				false,
				'::1',
				3306,
				'/tmp/mysql:with_colon.sock',
				true,
			),
			array(
				'2001:0db8:0000:0000:0000:ff00:0042:8329',
				false,
				'2001:0db8:0000:0000:0000:ff00:0042:8329',
				null,
				null,
				true,
			),
			array(
				'2001:db8:0:0:0:ff00:42:8329',
				false,
				'2001:db8:0:0:0:ff00:42:8329',
				null,
				null,
				true,
			),
			array(
				'2001:db8::ff00:42:8329',
				false,
				'2001:db8::ff00:42:8329',
				null,
				null,
				true,
			),
			array(
				'?::',
				true,
				null,
				null,
				null,
				false,
			),
		);
	}

	/**
	 * This private property is no longer used but needs to be retained as it can be
	 * accessed externally due to the `__get()` magic method.
	 *
	 * @ticket 59118
	 * @ticket 59846
	 */
	public function test_use_mysqli_property_access() {
		global $wpdb;

		$this->assertTrue( $wpdb->use_mysqli );
	}

	/**
	 * Verify "pinging" the database works cross-version PHP.
	 *
	 * @ticket 62061
	 */
	public function test_check_connection_returns_true_when_there_is_a_connection() {
		global $wpdb;

		$this->assertTrue( $wpdb->check_connection( false ) );
	}
}
