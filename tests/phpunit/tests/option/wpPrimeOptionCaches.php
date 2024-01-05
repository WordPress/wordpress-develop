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
				"value_$option",
				wp_cache_get( $option, 'options' ),
				"$option was not primed in the 'options' cache group."
			);

			$new_notoptions = wp_cache_get( $option, 'notoptions' );
			if ( ! is_array( $new_notoptions ) ) {
				$new_notoptions = array();
			}
			$this->assertArrayNotHasKey(
				$option,
				$new_notoptions,
				"$option was primed in the 'notoptions' cache."
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
	 * Tests that wp_prime_option_caches() handles a mix of primed and unprimed options.
	 *
	 * @ticket 58962
	 */
	public function test_wp_prime_option_caches_handles_a_mix_of_primed_and_unprimed_options() {
		global $wpdb;
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

		// Add non-existent option to the options to prime.
		$options_to_prime[] = 'option404notfound';

		// Prime the first option with a non-existent option.
		wp_prime_option_caches( array( 'option1', 'option404notfound' ) );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Prime all the options, including the pre-primed option.
		wp_prime_option_caches( $options_to_prime );

		// Ensure an additional database query was made.
		$this->assertSame(
			1,
			get_num_queries() - $initial_query_count,
			'Additional database queries were not made.'
		);

		// Ensure the last query does not contain the pre-primed option.
		$this->assertStringNotContainsString(
			"\'option1\'",
			$wpdb->last_query,
			'The last query should not contain the pre-primed option.'
		);

		// Ensure the last query does not contain the pre-primed notoption.
		$this->assertStringNotContainsString(
			"\'option404notfound\'",
			$wpdb->last_query,
			'The last query should not contain the pre-primed non-existent option.'
		);
	}

	/**
	 * Tests wp_prime_option_caches() with options that do not exist in the database.
	 *
	 * @ticket 58962
	 * @ticket 59738
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

		// Check getting and re-priming the options does not result in additional database queries.
		$initial_query_count = get_num_queries();
		foreach ( $options_to_prime as $option ) {
			get_option( $option );
			$this->assertSame(
				0,
				get_num_queries() - $initial_query_count,
				"Additional database queries were made getting option $option."
			);
		}

		wp_prime_option_caches( $options_to_prime );
		$this->assertSame(
			0,
			get_num_queries() - $initial_query_count,
			'Additional database queries were made re-priming the options.'
		);
	}

	/**
	 * Tests wp_prime_option_caches() with an empty array.
	 *
	 * @ticket 58962
	 * @ticket 59738
	 */
	public function test_wp_prime_option_caches_with_empty_array() {
		$alloptions = wp_load_alloptions();
		$notoptions = wp_cache_get( 'notoptions', 'options' );

		$initial_query_count = get_num_queries();
		wp_prime_option_caches( array() );

		$this->assertSame( $alloptions, wp_cache_get( 'alloptions', 'options' ), 'The alloptions cache was modified.' );
		$this->assertSame( $notoptions, wp_cache_get( 'notoptions', 'options' ), 'The notoptions cache was modified.' );

		// Check priming an empty array does not result in additional database queries.
		$this->assertSame(
			0,
			get_num_queries() - $initial_query_count,
			'Additional database queries were made.'
		);
	}

	/**
	 * Tests that wp_prime_option_caches() handles an empty "notoptions" cache.
	 *
	 * @ticket 58962
	 * @ticket 59738
	 */
	public function test_wp_prime_option_caches_handles_empty_notoptions_cache() {
		wp_cache_delete( 'notoptions', 'options' );

		wp_prime_option_caches( array( 'nonexistent_option' ) );

		$notoptions = wp_cache_get( 'notoptions', 'options' );
		$this->assertIsArray( $notoptions, 'The notoptions cache should be an array.' );
		$this->assertArrayHasKey( 'nonexistent_option', $notoptions, 'nonexistent_option was not added to notoptions.' );

		// Check getting and re-priming the options does not result in additional database queries.
		$initial_query_count = get_num_queries();

		get_option( 'nonexistent_option' );
		$this->assertSame(
			0,
			get_num_queries() - $initial_query_count,
			'Additional database queries were made getting nonexistent_option.'
		);

		wp_prime_option_caches( array( 'nonexistent_option' ) );
		$this->assertSame(
			0,
			get_num_queries() - $initial_query_count,
			'Additional database queries were made.'
		);
	}

	/**
	 * Test options primed by the wp_prime_option_caches() function are identical to those primed by get_option().
	 *
	 * @ticket 59738
	 *
	 * @dataProvider data_option_types
	 *
	 * @param mixed $option_value An option value.
	 */
	public function test_get_option_should_return_identical_value_when_pre_primed_by_wp_prime_option_caches( $option_value ) {
		// As this includes a test setting the value to `(bool) false`, update_option() can not be used so add_option() is used instead.
		add_option( 'type_of_option', $option_value, '', false );
		wp_cache_delete( 'type_of_option', 'options' );

		$this->assertFalse( wp_cache_get( 'type_of_option', 'options' ), 'type_of_option was not deleted from the cache for priming.' );

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_option_caches( array( 'type_of_option' ) );
		$value_after_pre_priming = get_option( 'type_of_option' );

		// Clear the cache and call get_option directly.
		wp_cache_delete( 'type_of_option', 'options' );
		$this->assertFalse( wp_cache_get( 'type_of_option', 'options' ), 'type_of_option was not deleted from the cache for get_option.' );
		$value_after_get_option = get_option( 'type_of_option' );

		/*
		 * If the option value is an object, use assertEquals() to compare the values.
		 *
		 * This is to compare the shape of the object rather than the identity of the object.
		 */
		if ( is_object( $option_value ) ) {
			$this->assertEquals( $value_after_get_option, $value_after_pre_priming, 'The values should be equal.' );
		} else {
			$this->assertSame( $value_after_get_option, $value_after_pre_priming, 'The values should be identical.' );
		}
	}

	/**
	 * Tests that wp_prime_option_caches() shapes the cache in the same fashion as get_option()
	 *
	 * @ticket 59738
	 *
	 * @dataProvider data_option_types
	 *
	 * @param mixed $option_value An option value.
	 */
	public function test_wp_prime_option_caches_cache_should_be_identical_to_get_option_cache( $option_value ) {
		// As this includes a test setting the value to `(bool) false`, update_option() can not be used so add_option() is used instead.
		add_option( 'type_of_option', $option_value, '', false );
		wp_cache_delete( 'type_of_option', 'options' );

		$this->assertFalse( wp_cache_get( 'type_of_option', 'options' ), 'type_of_option was not deleted from the cache for wp_prime_option_caches().' );

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_option_caches( array( 'type_of_option' ) );
		$value_from_priming = wp_cache_get( 'type_of_option', 'options' );

		wp_cache_delete( 'type_of_option', 'options' );
		$this->assertFalse( wp_cache_get( 'type_of_option', 'options' ), 'type_of_option was not deleted from the cache for get_option().' );

		// Call get_option() to prime the options.
		get_option( 'type_of_option' );
		$value_from_get_option = wp_cache_get( 'type_of_option', 'options' );

		$this->assertIsString( $value_from_get_option, 'Cache from get_option() should always be a string' );
		$this->assertIsString( $value_from_priming, 'Cache from wp_prime_option_caches() should always be a string' );
		$this->assertSame( $value_from_get_option, $value_from_priming, 'The values should be identical.' );
	}

	/**
	 * Tests that wp_prime_option_caches() doesn't trigger DB queries on already primed options.
	 *
	 * @ticket 59738
	 *
	 * @dataProvider data_option_types
	 *
	 * @param mixed $option_value An option value.
	 */
	public function test_wp_prime_option_caches_does_not_trigger_db_queries_repriming_options( $option_value ) {
		// As this includes a test setting the value to `(bool) false`, update_option() can not be used so add_option() is used instead.
		add_option( 'double_primed_option', $option_value, '', false );
		wp_cache_delete( 'double_primed_option', 'options' );
		$options_to_prime = array( 'double_primed_option' );

		$this->assertFalse( wp_cache_get( 'double_primed_option', 'options' ), 'double_primed_option was not deleted from the cache.' );

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_option_caches( $options_to_prime );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' cache group.
		foreach ( $options_to_prime as $option ) {
			$this->assertNotFalse(
				wp_cache_get( $option, 'options' ),
				"$option was not primed in the 'options' cache group."
			);

			$new_notoptions = wp_cache_get( $option, 'notoptions' );
			if ( ! is_array( $new_notoptions ) ) {
				$new_notoptions = array();
			}
			$this->assertArrayNotHasKey(
				$option,
				$new_notoptions,
				"$option was primed in the 'notoptions' cache."
			);
		}

		// Call the wp_prime_option_caches function to prime the options.
		wp_prime_option_caches( $options_to_prime );

		// Ensure no additional database queries were made.
		$this->assertSame(
			$initial_query_count,
			get_num_queries(),
			'Additional database queries were made.'
		);
	}

	/**
	 * Tests that wp_prime_option_caches() doesn't trigger DB queries for items primed in alloptions.
	 *
	 * @ticket 59738
	 *
	 * @dataProvider data_option_types
	 *
	 * @param mixed $option_value An option value.
	 */
	public function test_wp_prime_option_caches_does_not_trigger_db_queries_for_alloptions( $option_value ) {
		// As this includes a test setting the value to `(bool) false`, update_option() can not be used so add_option() is used instead.
		add_option( 'option_in_alloptions', $option_value, '', true );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'option_in_alloptions', 'options' );
		$options_to_prime = array( 'option_in_alloptions' );

		$this->assertFalse( wp_cache_get( 'option_in_alloptions', 'options' ), 'option_in_alloptions was not deleted from the cache.' );
		$this->assertFalse( wp_cache_get( 'alloptions', 'options' ), 'alloptions was not deleted from the cache.' );

		// Prime the alloptions cache.
		wp_load_alloptions();

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Call the wp_prime_option_caches function to reprime the option.
		wp_prime_option_caches( $options_to_prime );

		// Check that options are in the 'alloptions' cache only.
		foreach ( $options_to_prime as $option ) {
			$this->assertFalse(
				wp_cache_get( $option, 'options' ),
				"$option was primed in the 'options' cache group."
			);

			$new_notoptions = wp_cache_get( $option, 'notoptions' );
			if ( ! is_array( $new_notoptions ) ) {
				$new_notoptions = array();
			}
			$this->assertArrayNotHasKey(
				$option,
				$new_notoptions,
				"$option was primed in the 'notoptions' cache."
			);

			$new_alloptions = wp_cache_get( 'alloptions', 'options' );
			if ( ! is_array( $new_alloptions ) ) {
				$new_alloptions = array();
			}
			$this->assertArrayHasKey(
				$option,
				$new_alloptions,
				"$option was not primed in the 'alloptions' cache."
			);
		}

		// Ensure no additional database queries were made.
		$this->assertSame(
			0,
			get_num_queries() - $initial_query_count,
			'Additional database queries were made.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_option_types() {
		return array(
			'null'                              => array( null ),
			'(bool) false'                      => array( false ),
			'(bool) true'                       => array( true ),
			'(int) 0'                           => array( 0 ),
			'(int) -0'                          => array( -0 ),
			'(int) 1'                           => array( 1 ),
			'(int) -1'                          => array( -1 ),
			'(float) 0.0'                       => array( 0.0 ),
			'(float) -0.0'                      => array( -0.0 ),
			'(float) 1.0'                       => array( 1.0 ),
			'empty string'                      => array( '' ),
			'string with only tabs'             => array( "\t\t" ),
			'string with only newlines'         => array( "\n\n" ),
			'string with only carriage returns' => array( "\r\r" ),
			'string with only spaces'           => array( '   ' ),
			'populated string'                  => array( 'string' ),
			'string (1)'                        => array( '1' ),
			'string (0)'                        => array( '0' ),
			'string (0.0)'                      => array( '0.0' ),
			'string (-0)'                       => array( '-0' ),
			'string (-0.0)'                     => array( '-0.0' ),
			'empty array'                       => array( array() ),
			'populated array'                   => array( array( 'string' ) ),
			'empty object'                      => array( new stdClass() ),
			'populated object'                  => array( (object) array( 'string' ) ),
			'INF'                               => array( INF ),
			'NAN'                               => array( NAN ),
		);
	}
}
