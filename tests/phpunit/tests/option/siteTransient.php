<?php

/**
 * @group option
 */
class Tests_Option_SiteTransient extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}
	}

	/**
	 * @covers ::get_site_transient
	 * @covers ::set_site_transient
	 * @covers ::delete_site_transient
	 */
	public function test_the_basics() {
		$key    = 'key1';
		$value  = 'value1';
		$value2 = 'value2';

		$this->assertFalse( get_site_transient( 'doesnotexist' ) );
		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertSame( $value, get_site_transient( $key ) );
		$this->assertFalse( set_site_transient( $key, $value ) );
		$this->assertTrue( set_site_transient( $key, $value2 ) );
		$this->assertSame( $value2, get_site_transient( $key ) );
		$this->assertTrue( delete_site_transient( $key ) );
		$this->assertFalse( get_site_transient( $key ) );
		$this->assertFalse( delete_site_transient( $key ) );
	}

	/**
	 * @covers ::get_site_transient
	 * @covers ::set_site_transient
	 * @covers ::delete_site_transient
	 */
	public function test_serialized_data() {
		$key   = __FUNCTION__;
		$value = array(
			'foo' => true,
			'bar' => true,
		);

		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertSame( $value, get_site_transient( $key ) );

		$value = (object) $value;
		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertEquals( $value, get_site_transient( $key ) );
		$this->assertTrue( delete_site_transient( $key ) );
	}

	/**
	 * @ticket 22846
	 * @group ms-excluded
	 *
	 * @covers ::set_site_transient
	 * @covers ::wp_load_alloptions
	 */
	public function test_set_site_transient_is_not_stored_as_autoload_option() {
		$key = 'not_autoloaded';

		set_site_transient( $key, 'Not an autoload option' );

		$options = wp_load_alloptions();

		$this->assertArrayNotHasKey( '_site_transient_' . $key, $options );
	}

	/**
	 * Ensure autoloaded transient does not query database (single site).
	 *
	 * group @ms-excluded
	 * @ticket 61193
	 * @ticket 61053
	 */
	public function test_autoloaded_site_transient_does_not_query_database_single_site() {
		$key         = 'test_transient';
		$option_name = '_site_transient_' . $key;
		$value       = 'test_value';

		// Set the transient.
		set_site_transient( $key, $value );

		// Clear the options caches.
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		// Prime the alloptions cache.
		$alloptions = wp_load_alloptions();

		// Ensure the transient is autoloaded.
		$this->assertArrayHasKey( $option_name, $alloptions, 'Expected the transient to be autoloaded.' );

		$before_queries = get_num_queries();
		$this->assertSame( $value, get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 0, $transient_queries, 'Expected no database queries to retrieve the transient.' );
	}

	/**
	 * Ensure known non-existent transient does not query database (single site).
	 *
	 * group @ms-excluded
	 * @ticket 61193
	 * @ticket 61053
	 */
	public function test_non_existent_transient_does_not_make_a_database_call_single_site() {
		$key         = 'non_existent_test_transient';
		$option_name = '_transient_' . $key;

		// Ensure the transient doesn't exist.
		delete_transient( $key );

		// Clear the options caches.
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		// Prime the alloptions & transient cache.
		$alloptions = wp_load_alloptions();

		// Ensure the transient is not autoloaded.
		$this->assertArrayNotHasKey( $option_name, $alloptions, 'Expected the transient to not be autoloaded.' );

		$before_queries = get_num_queries();
		$this->assertFalse( get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 1, $transient_queries, 'Expected one database query to retrieve the transient the first time.' );

		$before_queries = get_num_queries();
		$this->assertFalse( get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 0, $transient_queries, 'Expected no database queries to retrieve the transient a second time.' );
	}

	/**
	 * Ensure known transient does not query database (single site).
	 *
	 * group @ms-excluded
	 * @ticket 61193
	 * @ticket 61053
	 */
	public function test_known_transient_does_not_make_a_database_call_single_site() {
		$key         = 'test_transient';
		$option_name = '_transient_' . $key;
		$value       = 'test_value';
		$timeout     = YEAR_IN_SECONDS;

		// Set the transient.
		set_transient( $key, $value, $timeout );

		// Clear the options caches.
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		// Prime the alloptions & transient cache.
		$alloptions = wp_load_alloptions();

		// Ensure the transient is not autoloaded.
		$this->assertArrayNotHasKey( $option_name, $alloptions, 'Expected the transient to not be autoloaded.' );

		$before_queries = get_num_queries();
		$this->assertSame( $value, get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 1, $transient_queries, 'Expected one database query to retrieve the transient the first time.' );

		$before_queries = get_num_queries();
		$this->assertSame( $value, get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 0, $transient_queries, 'Expected no database queries to retrieve the transient a second time.' );
	}

	/**
	 * Ensure primed transient does not query database (multi site).
	 *
	 * group @ms-required
	 * @ticket 61193
	 * @ticket 61053
	 */
	public function test_primed_site_transient_does_not_query_database_multi_site() {
		$key         = 'test_transient';
		$option_name = '_site_transient_' . $key;
		$value       = 'test_value';
		$network_id  = get_current_network_id();

		// Set the transient.
		set_site_transient( $key, $value );

		// Clear the options caches.
		wp_cache_delete( "{$network_id}:{$option_name}", 'site-options' );
		wp_cache_delete( "{$network_id}:notoptions", 'site-options' );

		$before_queries = get_num_queries();
		$this->assertSame( $value, get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 1, $transient_queries, 'Expected one database query to retrieve the transient the first time.' );

		$before_queries = get_num_queries();
		$this->assertSame( $value, get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 0, $transient_queries, 'Expected no database queries to retrieve the transient a second time.' );
	}

	/**
	 * Ensure primed transient does not query database (multi site).
	 *
	 * group @ms-required
	 * @ticket 61193
	 * @ticket 61053
	 */
	public function test_site_transient_with_timeout_makes_single_database_call_multi_site() {
		$key         = 'test_transient';
		$option_name = '_site_transient_' . $key;
		$value       = 'test_value';
		$timeout     = YEAR_IN_SECONDS;
		$network_id  = get_current_network_id();

		// Set the transient.
		set_site_transient( $key, $value, $timeout );

		// Clear the options caches.
		wp_cache_delete( "{$network_id}:{$option_name}", 'site-options' );
		wp_cache_delete( "{$network_id}:notoptions", 'site-options' );

		$before_queries = get_num_queries();
		$this->assertSame( $value, get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 1, $transient_queries, 'Expected one database query to retrieve the transient the first time.' );

		$before_queries = get_num_queries();
		$this->assertSame( $value, get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 0, $transient_queries, 'Expected no database queries to retrieve the transient a second time.' );
	}

	/**
	 * Ensure primed unknown transient does not query database (multi site).
	 *
	 * group @ms-required
	 * @ticket 61193
	 * @ticket 61053
	 */
	public function test_primed_non_existent_site_transient_does_not_query_database_multi_site() {
		$key         = 'non_existent_transient';
		$option_name = '_site_transient_' . $key;
		$network_id  = get_current_network_id();

		// Delete the transient.
		delete_site_transient( $key );

		// Clear the options caches.
		wp_cache_delete( "{$network_id}:{$option_name}", 'site-options' );
		wp_cache_delete( "{$network_id}:notoptions", 'site-options' );

		$before_queries = get_num_queries();
		$this->assertFalse( get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 1, $transient_queries, 'Expected one database query to retrieve the transient the first time.' );

		$before_queries = get_num_queries();
		$this->assertFalse( get_site_transient( $key ) );
		$transient_queries = get_num_queries() - $before_queries;
		$this->assertSame( 0, $transient_queries, 'Expected no database queries to retrieve the transient a second time.' );
	}
}
