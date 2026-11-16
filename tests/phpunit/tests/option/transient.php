<?php

/**
 * @group option
 */
class Tests_Option_Transient extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}
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
	 * Tests that set_transient() rejects names that are not non-empty strings.
	 *
	 * @ticket 58903
	 *
	 * @dataProvider data_invalid_transient_names
	 * @expectedIncorrectUsage set_transient
	 *
	 * @covers ::set_transient
	 * @covers ::_wp_is_valid_transient_name
	 *
	 * @param mixed $transient Transient name.
	 */
	public function test_set_transient_should_reject_invalid_name( $transient ) {
		$this->assertFalse( set_transient( $transient, 'value' ), 'set_transient() should return false.' );
		$this->assertFalse( get_option( '_transient_' ), 'No option should be added for an empty transient name.' );
	}

	/**
	 * Tests that get_transient() rejects names that are not non-empty strings.
	 *
	 * @ticket 58903
	 *
	 * @dataProvider data_invalid_transient_names
	 * @expectedIncorrectUsage get_transient
	 *
	 * @covers ::get_transient
	 * @covers ::_wp_is_valid_transient_name
	 *
	 * @param mixed $transient Transient name.
	 */
	public function test_get_transient_should_reject_invalid_name( $transient ) {
		$this->assertFalse( get_transient( $transient ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_invalid_transient_names() {
		return array(
			'empty string'    => array( '' ),
			'whitespace only' => array( '   ' ),
			'null'            => array( null ),
			'boolean false'   => array( false ),
			'boolean true'    => array( true ),
			'integer zero'    => array( 0 ),
			'integer one'     => array( 1 ),
			'empty array'     => array( array() ),
			'object'          => array( new stdClass() ),
		);
	}

	/**
	 * Tests that set_transient() accepts a name at the 172 character limit.
	 *
	 * @ticket 58903
	 *
	 * @dataProvider data_transient_names_at_length_limit
	 *
	 * @covers ::set_transient
	 * @covers ::get_transient
	 * @covers ::delete_transient
	 * @covers ::_wp_is_valid_transient_name_length
	 *
	 * @param string $transient Transient name.
	 */
	public function test_set_transient_should_accept_name_at_length_limit( $transient ) {
		$this->assertTrue( set_transient( $transient, 'value', HOUR_IN_SECONDS ), 'set_transient() should return true.' );
		$this->assertNotFalse( get_option( '_transient_timeout_' . $transient ), 'The timeout option should be stored.' );
		$this->assertSame( 'value', get_transient( $transient ), 'get_transient() should return the stored value.' );
		$this->assertTrue( delete_transient( $transient ), 'delete_transient() should return true.' );
		$this->assertFalse( get_transient( $transient ), 'get_transient() should return false after deletion.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_transient_names_at_length_limit() {
		return array(
			'172 single-byte characters' => array( str_repeat( 'a', 172 ) ),
			'172 multibyte characters'   => array( str_repeat( 'é', 172 ) ),
		);
	}

	/**
	 * Tests that set_transient() rejects a name over the 172 character limit.
	 *
	 * @ticket 58903
	 *
	 * @dataProvider data_transient_names_over_length_limit
	 * @expectedIncorrectUsage set_transient
	 *
	 * @covers ::set_transient
	 * @covers ::_wp_is_valid_transient_name_length
	 *
	 * @param string $transient Transient name.
	 */
	public function test_set_transient_should_reject_name_over_length_limit( $transient ) {
		$this->assertFalse( set_transient( $transient, 'value' ), 'set_transient() should return false.' );
		$this->assertFalse( get_option( '_transient_' . $transient ), 'No option should be added.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_transient_names_over_length_limit() {
		return array(
			'173 single-byte characters' => array( str_repeat( 'a', 173 ) ),
			'173 multibyte characters'   => array( str_repeat( 'é', 173 ) ),
		);
	}

	/**
	 * Tests that delete_transient() can still remove a transient stored with an empty name.
	 *
	 * @ticket 58903
	 *
	 * @covers ::delete_transient
	 */
	public function test_delete_transient_should_delete_transient_with_empty_name() {
		add_option( '_transient_', 'value' );

		$this->assertTrue( delete_transient( '' ), 'delete_transient() should return true.' );
		$this->assertFalse( get_option( '_transient_' ), 'The option should be deleted.' );
	}
}
