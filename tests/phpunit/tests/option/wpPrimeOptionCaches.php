<?php
/**
 * Test wp_prime_option_caches().
 *
 * @group option
 *
 * @covers ::wp_prime_option_caches
 */
class Tests_Option_WpPrimeOptionCaches extends WP_UnitTestCase {

	/**
	 * Tests that wp_prime_option_caches() primes multiple options.
	 *
	 * @ticket 58962
	 */
	public function test_wp_prime_option_caches() {
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

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_option_caches( $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' cache group.
		foreach ( $options_to_prime as $option ) {
			$this->assertSame(
				wp_cache_get( $option, 'options' ),
				get_option( $option ),
				"$option was not loaded to the 'options' cache group."
			);

			$this->assertFalse(
				wp_cache_get( $option, 'notoptions' ),
				get_option( $option ),
				"$option was loaded to the 'notoptions' cache group."
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
	 * Tests wp_prime_option_caches() with options that do not exist in the database.
	 *
	 * @ticket 58962
	 */
	public function test_wp_prime_option_caches_with_nonexistent_options() {
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

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_option_caches( $options_to_prime );

		// Check that options are not in the cache or database.
		foreach ( $options_to_prime as $option ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), "$option was not deleted from the cache." );
		}

		// Check that options are present in the notoptions cache.
		$new_notoptions = wp_cache_get( 'notoptions', 'options' );
		$this->assertIsArray( $new_notoptions, 'The notoptions cache should be an array.' );
		foreach ( $options_to_prime as $option ) {
			$this->assertArrayHasKey( $option, $new_notoptions, "$option was not added to the notoptions cache." );
		}
	}

	/**
	 * Tests wp_prime_option_caches() with an empty array.
	 *
	 * @ticket 58962
	 */
	public function test_wp_prime_option_caches_with_empty_array() {
		$alloptions = wp_load_alloptions();
		$notoptions = wp_cache_get( 'notoptions', 'options' );

		wp_prime_option_caches( array() );

		$this->assertSame( $alloptions, wp_cache_get( 'alloptions', 'options' ), 'The alloptions cache was modified.' );
		$this->assertSame( $notoptions, wp_cache_get( 'notoptions', 'options' ), 'The notoptions cache was modified.' );
	}

	/**
	 * Tests that wp_prime_option_caches() handles an empty "notoptions" cache.
	 *
	 * @ticket 58962
	 */
	public function test_wp_prime_option_caches_handles_empty_notoptions_cache() {
		wp_cache_delete( 'notoptions', 'options' );

		wp_prime_option_caches( array( 'nonexistent_option' ) );

		$notoptions = wp_cache_get( 'notoptions', 'options' );
		$this->assertIsArray( $notoptions, 'The notoptions cache should be an array.' );
		$this->assertArrayHasKey( 'nonexistent_option', $notoptions, 'nonexistent_option was not added to notoptions.' );
	}
}
