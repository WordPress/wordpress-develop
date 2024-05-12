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

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_prime as $option ) {
			update_network_option( null, $option, "value_$option" );
		}

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_network_option_caches( null, $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' cache group.
		foreach ( $options_to_prime as $option ) {
			$this->assertSame(
				"value_$option",
				get_network_option( null, $option ),
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

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_prime as $option ) {
			update_network_option( null, $option, "value_$option" );
		}

		// Add non-existent option to the options to prime.
		$options_to_prime[] = 'option404notfound';

		// Prime the first option with a non-existent option.
		wp_prime_network_option_caches( null, $options_to_prime );

		array_pop( $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' cache group.
		foreach ( $options_to_prime as $option ) {
			$this->assertSame(
				"value_$option",
				get_network_option( null, $option ),
				"$option has not been loaded"
			);
		}

		$this->assertFalse( get_network_option( null, 'option404notfound' ), "$option should return false as option does not exist" );

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
	public function test_wp_prime_network_option_caches_multiple_networks() {
		$different_network_id = self::factory()->network->create(
			array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			)
		);

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
			update_network_option( null, $option, "value_$option" );
		}

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_network_option_caches( $different_network_id, $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' cache group.
		foreach ( $options_to_prime as $option ) {
			$this->assertFalse(
				get_network_option( $different_network_id, $option ),
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
