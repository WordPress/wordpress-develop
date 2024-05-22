<?php
/**
 * Test wp_prime_network_option_caches().
 *
 * @group option
 *
 * @covers ::wp_prime_network_option_caches
 */
class Tests_Option_WpPrimeNetworkOptionCaches extends WP_UnitTestCase {

	/**
	 * @var int|WP_Error
	 */
	private static $different_network_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		if ( is_multisite() ) {
			self::$different_network_id = $factory->network->create(
				array(
					'domain' => 'wordpress.org',
					'path'   => '/',
				)
			);
		}
	}

	/**
	 * Tests that wp_prime_network_option_caches() primes multiple options.
	 *
	 * @ticket 61053
	 */
	public function test_wp_prime_network_option_caches() {
		// Create some options to prime.
		$network_id = get_current_network_id();
		if ( is_multisite() ) {
			$cache_group = 'site-options';
		} else {
			$cache_group = 'options';
		}

		// Create some options to prime.
		$options_to_prime = array(
			'option1',
			'option2',
			'option3',
		);

		$cache_keys = array();
		foreach ( $options_to_prime as $option ) {
			if ( is_multisite() ) {
				$cache_key = "$network_id:$option";
			} else {
				$cache_key = $option;
			}
			$cache_keys[ $option ] = $cache_key;
		}

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $cache_keys as $option => $cache_key ) {
			update_network_option( $network_id, $option, "value_$option" );
			wp_cache_delete( $cache_key, $cache_group );
			$this->assertFalse( wp_cache_get( $cache_key, $cache_group ), "$option was not deleted from the cache." );
		}

		// Call the wp_prime_network_option_caches() function to prime the options.
		wp_prime_network_option_caches( $network_id, $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' or 'site-options' cache group.
		foreach ( $cache_keys as $option => $cache_key ) {
			$this->assertSame( "value_$option", wp_cache_get( $cache_key, $cache_group ), "$option cache is not primed" );
			$this->assertSame(
				"value_$option",
				get_network_option( $network_id, $option ),
				"$option has not been loaded"
			);
		}

		// Ensure no additional database queries were made.
		$this->assertSame(
			$initial_query_count,
			get_num_queries(),
			'Additional database queries were made.'
		);
	}

	/**
	 * Tests that running wp_prime_network_option_caches() twice does not requery options.
	 *
	 * @ticket 61053
	 */
	public function test_wp_prime_network_option_caches_run_twice() {
		// Create some options to prime.
		$network_id = get_current_network_id();
		if ( is_multisite() ) {
			$cache_group = 'site-options';
		} else {
			$cache_group = 'options';
		}

		// Create some options to prime.
		$options_to_prime = array(
			'option1',
			'option2',
			'option3',
		);

		$cache_keys = array();
		foreach ( $options_to_prime as $option ) {
			if ( is_multisite() ) {
				$cache_key = "$network_id:$option";
			} else {
				$cache_key = $option;
			}
			$cache_keys[ $option ] = $cache_key;
		}

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $cache_keys as $option => $cache_key ) {
			update_network_option( $network_id, $option, "value_$option" );
			wp_cache_delete( $cache_key, $cache_group );
			$this->assertFalse( wp_cache_get( $cache_key, $cache_group ), "$option was not deleted from the cache." );
		}

		// Call the wp_prime_network_option_caches() function to prime the options.
		wp_prime_network_option_caches( $network_id, $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Call the wp_prime_network_option_caches() function second time
		wp_prime_network_option_caches( $network_id, $options_to_prime );

		// Ensure no additional database queries were made.
		$this->assertSame(
			$initial_query_count,
			get_num_queries(),
			'Additional database queries were made.'
		);
	}

	/**
	 * Tests that wp_prime_network_option_caches() handles a mix of primed and unprimed options.
	 *
	 * @ticket 61053
	 */
	public function test_wp_prime_network_option_caches_handles_a_mix_of_primed_and_unprimed_options() {
		// Create some options to prime.
		$options_to_prime = array(
			'option1',
			'option2',
			'option3',
		);

		$network_id = get_current_network_id();
		if ( is_multisite() ) {
			$cache_group = 'site-options';
		} else {
			$cache_group = 'options';
		}

		$cache_keys = array();
		foreach ( $options_to_prime as $option ) {
			if ( is_multisite() ) {
				$cache_key = "$network_id:$option";
			} else {
				$cache_key = $option;
			}
			$cache_keys[ $option ] = $cache_key;
		}

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $cache_keys as $option => $cache_key ) {
			update_network_option( $network_id, $option, "value_$option" );
			wp_cache_delete( $cache_key, $cache_group );
			$this->assertFalse( wp_cache_get( $cache_key, $cache_group ), "$option was not deleted from the cache." );
		}

		// Add non-existent option to the options to prime.
		$options_to_prime[] = 'option404notfound';

		// Prime the first option with a non-existent option.
		wp_prime_network_option_caches( $network_id, $options_to_prime );

		array_pop( $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' or 'site-options' cache group.
		foreach ( $cache_keys as $option => $cache_key ) {
			$this->assertSame( "value_$option", wp_cache_get( $cache_key, $cache_group ), "$option cache is not primed" );
			$this->assertSame(
				"value_$option",
				get_network_option( $network_id, $option ),
				"$option has not been loaded"
			);
		}

		$this->assertFalse( get_network_option( $network_id, 'option404notfound' ), 'option404notfound should return false as option does not exist' );

		// Ensure no additional database queries were made.
		$this->assertSame(
			$initial_query_count,
			get_num_queries(),
			'Additional database queries were made.'
		);
	}

	/**
	 * Test prime options on a different network.
	 *
	 * @group ms-required
	 *
	 * @ticket 61053
	 */
	public function test_wp_prime_network_option_caches_no_exists_cache() {
		$options_to_prime = array(
			'option1',
			'option2',
			'option3',
		);

		// Call the wp_prime_network_option_caches() function to prime the options.
		wp_prime_network_option_caches( self::$different_network_id, $options_to_prime );

		$notoptions_key = self::$different_network_id . ':notoptions';
		$expected       = array_fill_keys( $options_to_prime, true );
		$this->assertSame( $expected, wp_cache_get( $notoptions_key, 'site-options' ) );
	}

	/**
	 * Test prime options on a different network.
	 *
	 * @group ms-required
	 *
	 * @ticket 61053
	 */
	public function test_wp_prime_network_option_caches_multiple_networks() {
		$network_id  = get_current_network_id();
		$cache_group = 'site-options';

		// Create some options to prime.
		$options_to_prime = array(
			'option1',
			'option2',
			'option3',
		);

		$cache_keys = array();
		foreach ( $options_to_prime as $option ) {
			$cache_key             = "$network_id:$option";
			$cache_keys[ $option ] = $cache_key;
		}

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $cache_keys as $option => $cache_key ) {
			update_network_option( $network_id, $option, "value_$option" );
			wp_cache_delete( $cache_key, $cache_group );
			$this->assertFalse( wp_cache_get( $cache_key, $cache_group ), "$option was not deleted from the cache." );
		}

		// Call the wp_prime_network_option_caches() function to prime the options.
		wp_prime_network_option_caches( self::$different_network_id, $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		foreach ( $cache_keys as $option => $cache_key ) {
			$this->assertFalse( wp_cache_get( $cache_key, $cache_group ), "$option cache should be false" );
			$this->assertFalse(
				get_network_option( self::$different_network_id, $option ),
				"$option has not been loaded"
			);
		}

		// Ensure no additional database queries were made.
		$this->assertSame(
			$initial_query_count,
			get_num_queries(),
			'Additional database queries were made.'
		);
	}
}
