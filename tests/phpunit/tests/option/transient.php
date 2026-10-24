<?php

/**
 * @group option
 */
class Tests_Option_Transient extends WP_UnitTestCase {

	/**
	 * Previous `$wpdb->suppress_errors()` state, restored after a test that forces a database error.
	 *
	 * @var bool|null
	 */
	private $suppress_errors = null;

	public function set_up() {
		parent::set_up();

		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}
	}

	public function tear_down() {
		if ( null !== $this->suppress_errors ) {
			$GLOBALS['wpdb']->suppress_errors( $this->suppress_errors );
			$this->suppress_errors = null;
		}

		parent::tear_down();
	}

	/**
	 * @covers ::get_transient
	 * @covers ::set_transient
	 * @covers ::delete_transient
	 */
	public function test_the_basics() {
		$key    = 'key1';
		$value  = 'value1';
		$value2 = 'value2';

		$this->assertFalse( get_transient( 'doesnotexist' ) );
		$this->assertTrue( set_transient( $key, $value ) );
		$this->assertSame( $value, get_transient( $key ) );
		$this->assertFalse( set_transient( $key, $value ) );
		$this->assertTrue( set_transient( $key, $value2 ) );
		$this->assertSame( $value2, get_transient( $key ) );
		$this->assertTrue( delete_transient( $key ) );
		$this->assertFalse( get_transient( $key ) );
		$this->assertFalse( delete_transient( $key ) );
	}

	/**
	 * @covers ::get_transient
	 * @covers ::set_transient
	 * @covers ::delete_transient
	 */
	public function test_serialized_data() {
		$key   = rand_str();
		$value = array(
			'foo' => true,
			'bar' => true,
		);

		$this->assertTrue( set_transient( $key, $value ) );
		$this->assertSame( $value, get_transient( $key ) );

		$value = (object) $value;
		$this->assertTrue( set_transient( $key, $value ) );
		// Keep assertEquals() because the objects are intentionally compared by value.
		$this->assertEquals( $value, get_transient( $key ) );
		$this->assertTrue( delete_transient( $key ) );
	}

	/**
	 * @ticket 22807
	 *
	 * @covers ::get_option
	 * @covers ::set_transient
	 * @covers ::update_option
	 */
	public function test_transient_data_with_timeout() {
		$key   = rand_str();
		$value = rand_str();

		$this->assertFalse( get_option( '_transient_timeout_' . $key ) );
		$now = time();

		$this->assertTrue( set_transient( $key, $value, 100 ) );

		// Ensure the transient timeout is set for 100-101 seconds in the future.
		$this->assertGreaterThanOrEqual( $now + 100, get_option( '_transient_timeout_' . $key ) );
		$this->assertLessThanOrEqual( $now + 101, get_option( '_transient_timeout_' . $key ) );

		// Update the timeout to a second in the past and watch the transient be invalidated.
		update_option( '_transient_timeout_' . $key, $now - 1 );
		$this->assertFalse( get_transient( $key ) );
	}

	/**
	 * Ensure get_transient() makes a single database request.
	 *
	 * @ticket 61193
	 *
	 * @covers ::get_transient
	 */
	public function test_get_transient_with_timeout_makes_a_single_database_call() {
		global $wpdb;
		$key                        = 'test_transient';
		$value                      = 'test_value';
		$timeout                    = 100;
		$expected_query             = "SELECT option_name, option_value FROM $wpdb->options WHERE option_name IN ('_transient_{$key}','_transient_timeout_{$key}')";
		$unexpected_query_transient = "SELECT option_value FROM $wpdb->options WHERE option_name = '_transient_{$key}' LIMIT 1";
		$unexpected_query_timeout   = "SELECT option_value FROM $wpdb->options WHERE option_name = '_transient_timeout_{$key}' LIMIT 1";
		$queries                    = array();

		set_transient( $key, $value, $timeout );

		// Clear the cache of both the transient and the timeout.
		$option_names = array(
			'_transient_' . $key,
			'_transient_timeout_' . $key,
		);
		foreach ( $option_names as $option_name ) {
			wp_cache_delete( $option_name, 'options' );
		}

		add_filter(
			'query',
			function ( $query ) use ( &$queries ) {
				$queries[] = $query;
				return $query;
			}
		);

		$before_queries = get_num_queries();
		$this->assertSame( $value, get_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 1, $transient_queries, 'Expected a single database query to retrieve the transient.' );
		$this->assertContains( $expected_query, $queries, 'Expected query to prime both transient options in a single call.' );
		// Note: Some versions of PHPUnit and/or the test suite may report failures as asserting to contain rather than not to contain.
		$this->assertNotContains( $unexpected_query_transient, $queries, 'Unexpected query of transient option individually.' );
		$this->assertNotContains( $unexpected_query_timeout, $queries, 'Unexpected query of transient timeout option individually.' );
	}

	/**
	 * Ensure set_transient() primes the option cache checking for an existing transient.
	 *
	 * @ticket 61193
	 *
	 * @covers ::set_transient
	 */
	public function test_set_transient_primes_option_cache() {
		global $wpdb;
		$key                        = 'test_transient';
		$value                      = 'test_value';
		$timeout                    = 100;
		$expected_query             = "SELECT option_name, option_value FROM $wpdb->options WHERE option_name IN ('_transient_{$key}','_transient_timeout_{$key}')";
		$unexpected_query_transient = "SELECT option_value FROM $wpdb->options WHERE option_name = '_transient_{$key}' LIMIT 1";
		$unexpected_query_timeout   = "SELECT option_value FROM $wpdb->options WHERE option_name = '_transient_timeout_{$key}' LIMIT 1";
		$queries                    = array();

		add_filter(
			'query',
			function ( $query ) use ( &$queries ) {
				$queries[] = $query;
				return $query;
			}
		);

		$before_queries = get_num_queries();
		$this->assertTrue( set_transient( $key, $value, $timeout ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 3, $transient_queries, 'Expected three database queries setting the transient.' );
		$this->assertContains( $expected_query, $queries, 'Expected query to prime both transient options in a single call.' );
		// Note: Some versions of PHPUnit and/or the test suite may report failures as asserting to contain rather than not to contain.
		$this->assertNotContains( $unexpected_query_transient, $queries, 'Unexpected query of transient option individually.' );
		$this->assertNotContains( $unexpected_query_timeout, $queries, 'Unexpected query of transient timeout option individually.' );
	}

	/**
	 * @ticket 22807
	 *
	 * @covers ::set_transient
	 * @covers ::get_transient
	 * @covers ::get_option
	 * @covers ::update_option
	 */
	public function test_transient_add_timeout() {
		$key    = rand_str();
		$value  = rand_str();
		$value2 = rand_str();
		$this->assertTrue( set_transient( $key, $value ) );
		$this->assertSame( $value, get_transient( $key ) );

		$this->assertFalse( get_option( '_transient_timeout_' . $key ) );

		$now = time();
		// Add timeout to existing timeout-less transient.
		$this->assertTrue( set_transient( $key, $value2, 1 ) );
		$this->assertGreaterThanOrEqual( $now, get_option( '_transient_timeout_' . $key ) );

		update_option( '_transient_timeout_' . $key, $now - 1 );
		$this->assertFalse( get_transient( $key ) );
	}

	/**
	 * If get_option( $transient_timeout ) returns false, don't bother trying to delete the transient.
	 *
	 * @ticket 30380
	 *
	 * @covers ::set_transient
	 * @covers ::get_transient
	 */
	public function test_nonexistent_key_dont_delete_if_false() {
		// Create a bogus a transient.
		$key = 'test_transient';
		set_transient( $key, 'test', 60 * 10 );
		$this->assertSame( 'test', get_transient( $key ) );

		// Useful variables for tracking.
		$transient_timeout = '_transient_timeout_' . $key;

		// Mock an action for tracking action calls.
		$a = new MockAction();

		// Make sure the timeout option returns false.
		add_filter( 'option_' . $transient_timeout, '__return_false' );

		// Add some actions to make sure options are _not_ deleted.
		add_action( 'delete_option', array( $a, 'action' ) );

		// Act.
		get_transient( $key );

		// Make sure 'delete_option' was not called for both the transient and the timeout.
		$this->assertSame( 0, $a->get_call_count() );
	}

	/**
	 * @ticket 30380
	 *
	 * @covers ::set_transient
	 * @covers ::get_transient
	 */
	public function test_nonexistent_key_old_timeout() {
		// Create a transient.
		$key = 'test_transient';
		set_transient( $key, 'test', 60 * 10 );
		$this->assertSame( 'test', get_transient( $key ) );

		// Make sure the timeout option returns false.
		$timeout          = '_transient_timeout_' . $key;
		$transient_option = '_transient_' . $key;
		add_filter( 'option_' . $timeout, '__return_zero' );

		// Mock an action for tracking action calls.
		$a = new MockAction();

		// Add some actions to make sure options are deleted.
		add_action( 'delete_option', array( $a, 'action' ) );

		// Act.
		get_transient( $key );

		// Make sure 'delete_option' was called for both the transient and the timeout.
		$this->assertSame( 2, $a->get_call_count() );

		$expected = array(
			array(
				'action'    => 'action',
				'hook_name' => 'delete_option',
				'tag'       => 'delete_option', // Back compat.
				'args'      => array( $transient_option ),
			),
			array(
				'action'    => 'action',
				'hook_name' => 'delete_option',
				'tag'       => 'delete_option', // Back compat.
				'args'      => array( $timeout ),
			),
		);
		$this->assertSame( $expected, $a->get_events() );
	}

	/**
	 * Tests that a new transient is not stored when its timeout cannot be saved.
	 *
	 * @ticket 44977
	 * @ticket 52345
	 *
	 * @covers ::set_transient
	 */
	public function test_set_transient_should_not_store_value_when_timeout_cannot_be_added() {
		$key    = 'transient_timeout_fails';
		$action = new MockAction();
		add_action( "set_transient_{$key}", array( $action, 'action' ) );
		$this->fail_insert_of_option( "_transient_timeout_{$key}" );

		$this->assertFalse( set_transient( $key, 'value', 60 ), 'set_transient() should report the failure.' );
		$this->assertFalse( get_transient( $key ), 'The transient should not be readable without a timeout.' );
		$this->assertFalse( get_option( "_transient_{$key}" ), 'The value row should not be stored.' );
		$this->assertSame( 0, $action->get_call_count(), 'The set_transient_{$transient} action should not fire.' );
	}

	/**
	 * Tests that adding an expiry to a transient without a timeout does not leave a non-expiring value.
	 *
	 * @ticket 44977
	 * @ticket 52345
	 *
	 * @covers ::set_transient
	 */
	public function test_set_transient_should_not_store_value_when_timeout_cannot_be_added_to_existing_transient() {
		$key = 'transient_timeout_fails_existing';
		set_transient( $key, 'old' );
		$this->fail_insert_of_option( "_transient_timeout_{$key}" );

		$this->assertFalse( set_transient( $key, 'new', 60 ), 'set_transient() should report the failure.' );
		$this->assertFalse( get_transient( $key ), 'The transient should not be readable without a timeout.' );
		$this->assertFalse( get_option( "_transient_{$key}" ), 'The value row should not be stored.' );
	}

	/**
	 * Tests that setting the same transient twice with the same expiry keeps the latest value.
	 *
	 * @ticket 44977
	 * @ticket 52345
	 *
	 * @covers ::set_transient
	 */
	public function test_set_transient_should_keep_latest_value_when_set_twice_with_same_expiry() {
		$key = 'transient_set_twice';

		$this->assertTrue( set_transient( $key, 'first', 60 ), 'The first value should be stored.' );
		$this->assertTrue( set_transient( $key, 'second', 60 ), 'The second value should be stored.' );
		$this->assertSame( 'second', get_transient( $key ), 'The latest value should be returned.' );
	}

	/**
	 * Tests that a transient can still be set when an orphaned timeout row is left behind.
	 *
	 * @ticket 44977
	 * @ticket 52345
	 *
	 * @covers ::set_transient
	 */
	public function test_set_transient_should_store_value_when_orphaned_timeout_exists() {
		$key = 'transient_orphaned_timeout';
		set_transient( $key, 'old', 60 );
		delete_option( "_transient_{$key}" );

		$this->assertTrue( set_transient( $key, 'new', 60 ), 'set_transient() should succeed.' );
		$this->assertSame( 'new', get_transient( $key ), 'The new value should be returned.' );
	}

	/**
	 * Tests that a new transient is stored when another request adds the same timeout row first.
	 *
	 * @ticket 44977
	 * @ticket 52345
	 *
	 * @covers ::set_transient
	 */
	public function test_set_transient_should_store_value_when_timeout_was_added_concurrently() {
		$key = 'transient_timeout_race';
		$this->insert_option_concurrently( "_transient_timeout_{$key}" );

		$this->assertTrue( set_transient( $key, 'value', 60 ), 'set_transient() should succeed.' );
		$this->assertSame( 'value', get_transient( $key ), 'The value should be returned.' );
	}

	/**
	 * Tests that adding an expiry to an existing transient succeeds when another request adds the same timeout first.
	 *
	 * @ticket 44977
	 * @ticket 52345
	 *
	 * @covers ::set_transient
	 */
	public function test_set_transient_should_store_value_when_timeout_was_added_concurrently_to_existing_transient() {
		$key = 'transient_timeout_race_existing';
		set_transient( $key, 'old' );
		$this->insert_option_concurrently( "_transient_timeout_{$key}" );

		$this->assertTrue( set_transient( $key, 'new', 60 ), 'set_transient() should succeed.' );
		$this->assertSame( 'new', get_transient( $key ), 'The new value should be returned.' );
	}

	/**
	 * Tests that get_transient() does not delete a non-autoloaded transient without a timeout.
	 *
	 * When a transient value option exists without autoloading (e.g. manually added with
	 * autoload=false or migrated without a timeout), get_transient() must not treat
	 * the missing timeout row as expired.
	 *
	 * @ticket 44977
	 *
	 * @covers ::get_transient
	 */
	public function test_get_transient_should_not_delete_non_autoloaded_transient_without_timeout() {
		$key    = 'non_autoloaded_transient';
		$option = "_transient_{$key}";
		add_option( $option, 'custom_value', '', false );

		$alloptions = wp_load_alloptions();
		$this->assertArrayNotHasKey( $option, $alloptions, 'Option should not be autoloaded.' );
		$this->assertFalse( get_option( "_transient_timeout_{$key}" ), 'Timeout option should not exist.' );

		$this->assertSame( 'custom_value', get_transient( $key ), 'get_transient() should return the transient value.' );
		$this->assertSame( 'custom_value', get_option( $option ), 'Transient option should not be deleted.' );
	}

	/**
	 * Makes the INSERT query for the given option fail with a database error.
	 *
	 * @param string $option_name Option name whose INSERT should fail.
	 */
	private function fail_insert_of_option( $option_name ) {
		global $wpdb;

		$this->suppress_errors = $wpdb->suppress_errors();
		add_filter(
			'query',
			static function ( $query ) use ( $option_name, $wpdb ) {
				if ( 0 === stripos( ltrim( $query ), 'INSERT' ) && str_contains( $query, "'{$option_name}'" ) ) {
					return str_replace( "`{$wpdb->options}`", "`{$wpdb->options}_missing`", $query );
				}
				return $query;
			}
		);
	}

	/**
	 * Runs the INSERT query for the given option once before WordPress does, as a parallel request would.
	 *
	 * @param string $option_name Option name whose row is inserted concurrently.
	 */
	private function insert_option_concurrently( $option_name ) {
		global $wpdb;

		$filter = static function ( $query ) use ( $option_name, $wpdb, &$filter ) {
			if ( 0 === stripos( ltrim( $query ), 'INSERT' ) && str_contains( $query, "'{$option_name}'" ) ) {
				remove_filter( 'query', $filter );
				$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Replays core's already prepared INSERT.
			}
			return $query;
		};
		add_filter( 'query', $filter );
	}
}
