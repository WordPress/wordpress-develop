<?php
/**
 * Test wp_load_options().
 *
 * @group option
 *
 * @covers ::wp_load_options
 */
class Tests_Option_PrimeOptions extends WP_UnitTestCase {

	/**
	 * Tests that wp_load_options() loads multiple options.
	 *
	 * @ticket 58962
	 */
	public function test_wp_load_options() {
		// Create some options to load.
		$options_to_load = array(
			'option1',
			'option2',
			'option3',
		);

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_load as $option ) {
			update_option( $option, "value_$option", false );
			wp_cache_delete( $option, 'options' );
			$this->assertFalse( wp_cache_get( $option, 'options' ), "$option was not deleted from the cache." );
		}

		// Call the wp_load_options function to load the options.
		wp_load_options( $options_to_load );

		// Store the initial database query count.
		$initial_query_count = get_num_queries();

		// Check that options are only in the 'options' cache group.
		foreach ( $options_to_load as $option ) {
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
	 * Tests wp_load_options() with options that do not exist in the database.
	 *
	 * @ticket 58962
	 */
	public function test_wp_load_options_with_nonexistent_options() {
		// Create some options to load.
		$options_to_load = array(
			'option1',
			'option2',
		);

		/*
		 * Set values for the options,
		 * clear the cache for the options,
		 * check options are not in cache initially.
		 */
		foreach ( $options_to_load as $option ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), "$option was not deleted from the cache." );
		}

		// Call the wp_load_options function to load the options.
		wp_load_options( $options_to_load );

		// Check that options are not in the cache or database.
		foreach ( $options_to_load as $option ) {
			$this->assertFalse( wp_cache_get( $option, 'options' ), "$option was not deleted from the cache." );
		}

		// Check that options are present in the notoptions cache.
		$new_notoptions = wp_cache_get( 'notoptions', 'options' );
		$this->assertIsArray( $new_notoptions, 'The notoptions cache should be an array.' );
		foreach ( $options_to_load as $option ) {
			$this->assertArrayHasKey( $option, $new_notoptions, "$option was not added to the notoptions cache." );
		}
	}

	/**
	 * Tests wp_load_options() with an empty array.
	 *
	 * @ticket 58962
	 */
	public function test_wp_load_options_with_empty_array() {
		$alloptions = wp_load_alloptions();
		$notoptions = wp_cache_get( 'notoptions', 'options' );

		wp_load_options( array() );

		$this->assertSame( $alloptions, wp_cache_get( 'alloptions', 'options' ), 'The alloptions cache was modified.' );
		$this->assertSame( $notoptions, wp_cache_get( 'notoptions', 'options' ), 'The notoptions cache was modified.' );
	}

	/**
	 * Tests that wp_load_options handles an empty "notoptions" cache.
	 *
	 * @ticket 58962
	 */
	public function test_wp_load_options_handles_empty_notoptions_cache() {
		wp_cache_delete( 'notoptions', 'options' );

		wp_load_options( array( 'nonexistent_option' ) );

		$notoptions = wp_cache_get( 'notoptions', 'options' );
		$this->assertIsArray( $notoptions, 'The notoptions cache should be an array.' );
		$this->assertArrayHasKey( 'nonexistent_option', $notoptions, 'nonexistent_option was not added to notoptions.' );
	}
}
