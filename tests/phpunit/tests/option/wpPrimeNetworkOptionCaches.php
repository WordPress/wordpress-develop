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
	 * Tests that wp_prime_network_option_caches() primes multiple options.
	 *
	 * @ticket 61053
	 */
	public function test_wp_prime_network_option_caches() {
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

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_prime as $option ) {
			update_network_option( $network_id, $option, "value_$option" );
			if ( is_multisite() ) {
				wp_cache_delete( "$network_id:$option", $cache_group );
			} else {
				wp_cache_delete( $option, $cache_group );
			}
		}

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_network_option_caches( $network_id, $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' or 'site-options' cache group.
		foreach ( $options_to_prime as $option ) {
			if ( is_multisite() ) {
				$this->assertSame( "value_$option", wp_cache_get( "$network_id:$option", $cache_group ), "$option cache is not primed" );
			} else {
				$this->assertSame( "value_$option", wp_cache_get( $option, $cache_group ), "$option cache is not primed" );
			}
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
	 * Tests that wp_prime_network_option_caches() is run twice
	 *
	 * @ticket 61053
	 */
	public function test_wp_prime_network_option_caches_run_twice() {
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

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_prime as $option ) {
			update_network_option( $network_id, $option, "value_$option" );
			if ( is_multisite() ) {
				wp_cache_delete( "$network_id:$option", $cache_group );
			} else {
				wp_cache_delete( $option, $cache_group );
			}
		}

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_network_option_caches( $network_id, $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Call the wp_prime_option_caches function second time
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
		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_prime as $option ) {
			update_network_option( $network_id, $option, "value_$option" );
			if ( is_multisite() ) {
				wp_cache_delete( "$network_id:$option", $cache_group );
			} else {
				wp_cache_delete( $option, $cache_group );
			}
		}

		// Add non-existent option to the options to prime.
		$options_to_prime[] = 'option404notfound';

		// Prime the first option with a non-existent option.
		wp_prime_network_option_caches( $network_id, $options_to_prime );

		array_pop( $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' or 'site-options' cache group.
		foreach ( $options_to_prime as $option ) {
			if ( is_multisite() ) {
				$this->assertSame( "value_$option", wp_cache_get( "$network_id:$option", $cache_group ), "$option cache is not primed" );
			} else {
				$this->assertSame( "value_$option", wp_cache_get( $option, $cache_group ), "$option cache is not primed" );
			}
			$this->assertSame(
				"value_$option",
				get_network_option( $network_id, $option ),
				"$option has not been loaded"
			);
		}

		$this->assertFalse( get_network_option( $network_id, 'option404notfound' ), "$option should return false as option does not exist" );

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
		$different_network_id = self::factory()->network->create(
			array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			)
		);
		$options_to_prime     = array(
			'option1',
			'option2',
			'option3',
		);

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_network_option_caches( $different_network_id, $options_to_prime );

		$notoptions_key = "$different_network_id:notoptions";
		$this->assertSame( $options_to_prime, wp_cache_get( $notoptions_key, 'site-options' ) );
	}

	/**
	 * Test prime options on a different network.
	 *
	 * @group ms-required
	 *
	 * @ticket 61053
	 */
	public function test_wp_prime_network_option_caches_multiple_networks() {
		$different_network_id = self::factory()->network->create(
			array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			)
		);

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

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_prime as $option ) {
			update_network_option( $network_id, $option, "value_$option" );
			if ( is_multisite() ) {
				wp_cache_delete( "$network_id:$option", $cache_group );
			} else {
				wp_cache_delete( $option, $cache_group );
			}
		}

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_network_option_caches( $different_network_id, $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		foreach ( $options_to_prime as $option ) {
			$this->assertFalse(
				get_network_option( $different_network_id, $option ),
				"$option has not been loaded"
			);
			if ( is_multisite() ) {
				$this->assertFalse( wp_cache_get( "$network_id:$option", $cache_group ), "$option cache should be false" );
			} else {
				$this->assertFalse( wp_cache_get( $option, $cache_group ), "$option cache should be false" );
			}
		}

		// Ensure no additional database queries were made.
		$this->assertSame(
			$initial_query_count,
			get_num_queries(),
			'Additional database queries were made.'
		);
	}
}
