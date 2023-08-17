<?php
/**
 * Test prime_options().
 *
 * @group option
 *
 * @covers ::prime_options
 *
 * @ticket 58962
 */
class Tests_Option_PrimeOptions extends WP_UnitTestCase {

	/**
	 * Tests that prime_options() primes multiple options.
	 */
	public function test_prime_options() {
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
			update_option( $option, "value_$option", false );
			wp_cache_delete( $option, 'options' );
			$this->assertFalse( wp_cache_get( $option, 'options' ), "$option was not deleted from the cache." );
		}

		// Call the prime_options function to prime the options.
		prime_options( $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' cache group.
		foreach ( $options_to_prime as $option ) {
			$this->assertSame(
				wp_cache_get( $option, 'options' ),
				get_option( $option ),
				"$option was not primed to the 'options' cache group."
			);

			$this->assertFalse(
				wp_cache_get( $option, 'notoptions' ),
				get_option( $option ),
				"$option was primed to the 'notoptions' cache group."
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
	 * Tests prime_options() with options that do not exist in the database.
	 */
	public function test_prime_options_with_nonexistent_options() {
		// Create some options to prime.
		$options_to_prime = array(
			'option1',
			'option2',
		);

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_prime as $option ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), "$option was not deleted from the cache." );
		}

		// Call the prime_options function to prime the options.
		prime_options( $options_to_prime );

		// Check that options are not in the cache or database.
		foreach ( $options_to_prime as $option ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), "$option was not deleted from the cache." );
		}

		// Check that options are present in the notoptions cache.
		$new_notoptions = wp_cache_get( 'notoptions', 'options' );
		foreach ( $options_to_prime as $option ) {
			$this->assertTrue( isset( $new_notoptions[ $option ] ), "$option was not added to the notoptions cache." );
		}
	}
}
