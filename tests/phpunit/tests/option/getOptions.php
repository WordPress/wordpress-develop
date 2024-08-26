<?php
/**
 * Test get_options().
 *
 * @group option
 *
 * @covers ::get_options
 */
class Tests_Option_GetOptions extends WP_UnitTestCase {

	/**
	 * Tests that get_options() retrieves specified options.
	 *
	 * @ticket 58962
	 */
	public function test_get_options() {
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
			update_option( $option, "value_$option", false );
			wp_cache_delete( $option, 'options' );
			$this->assertFalse( wp_cache_get( $option, 'options' ), "$option was not deleted from the cache." );
		}

		// Call the get_options function to retrieve the options.
		$options = get_options( array( 'option1', 'option2' ) );

		// Check that options are now in the cache.
		foreach ( $options_to_prime as $option ) {
			$this->assertSame( wp_cache_get( $option, 'options' ), get_option( $option ), "$option was not primed." );
		}

		// Check that the retrieved options are correct.
		$this->assertSame( get_option( 'option1' ), $options['option1'], 'Retrieved option1 does not match expected value.' );
		$this->assertSame( get_option( 'option2' ), $options['option2'], 'Retrieved option2 does not match expected value.' );
	}

	/**
	 * Tests get_options() with an empty input array.
	 *
	 * @ticket 58962
	 */
	public function test_get_options_with_empty_array() {
		// Call the get_options function with an empty array.
		$options = get_options( array() );

		// Make sure the result is an empty array.
		$this->assertIsArray( $options, 'An array should have been returned.' );
		$this->assertEmpty( $options, 'No options should have been returned.' );
	}

	/**
	 * Tests get_options() with options that include some nonexistent options.
	 */
	public function test_get_options_with_nonexistent_options() {
		// Create some options to prime.
		$options_to_prime = array(
			'option1',
		);

		// Make sure options are not in cache or database initially.
		$this->assertFalse( wp_cache_get( 'option1', 'options' ), 'option1 was not deleted from the cache.' );
		$this->assertFalse( wp_cache_get( 'nonexistent_option', 'options' ), 'nonexistent_option was not deleted from the cache.' );

		// Call the get_options function with an array that includes a nonexistent option.
		$options = get_options( array( 'option1', 'nonexistent_option' ) );

		// Check that the retrieved options are correct.
		$this->assertSame( get_option( 'option1' ), $options['option1'], 'Retrieved option1 does not match expected value.' );

		// Check that options are present in the notoptions cache.
		$new_notoptions = wp_cache_get( 'notoptions', 'options' );
		foreach ( $options_to_prime as $option ) {
			$this->assertTrue( isset( $new_notoptions[ $option ] ), "$option was not added to the notoptions cache." );
		}

		// Check that the nonexistent option is in the result array.
		$this->assertArrayHasKey( 'nonexistent_option', $options, 'Result array should not contain nonexistent_option.' );

		$this->assertFalse( $options['nonexistent_option'], 'nonexistent_option is present in option.' );
	}

	/**
	 * Test get_option() returns the same type when cached and uncached.
	 *
	 * @ticket 32848
	 *
	 * @dataProvider data_get_option_return_type_cached_and_uncached
	 *
	 * @param mixed $option_vale The value to test.
	 */
	public function test_get_option_return_type_cached_and_uncached( $option_vale ) {
		$option_name = 'option_for_type_testing';

		// Set the option value.
		update_option( $option_name, $option_vale, false );

		// Get the option while cached.
		$option_cached = get_option( $option_name );

		// Clear the cache.
		wp_cache_delete( $option_name, 'options' );

		// Get the option while uncached.
		$option_uncached = get_option( $option_name );

		// Check that the return type is the same.
		$this->assertSame( gettype( $option_cached ), gettype( $option_uncached ), 'The return type is not the same.' );
		/*
		 * Check canonicalized value.
		 *
		 * This is done separately from the above check to avoid false negatives
		 * for objects as assertSame checks for the same instance.
		 */
		$this->assertEqualsCanonicalizing( $option_cached, $option_uncached, 'The option values are not the same.' );
	}

	/**
	 * Data provider for test_get_option_return_type_cached_and_uncached().
	 *
	 * @return array[]
	 */
	public function data_get_option_return_type_cached_and_uncached() {
		return array(
			'an empty string'                => array( '' ),
			'a string with spaces'           => array( '   ' ),
			'a string with tabs'             => array( "\t" ),
			'a string with new lines'        => array( "\n" ),
			'a string with carriage returns' => array( "\r" ),
			'int -1'                         => array( -1 ),
			'int 0'                          => array( 0 ),
			'int 1'                          => array( 1 ),
			'float -1.0'                     => array( -1.0 ),
			'float 0.0'                      => array( 0.0 ),
			'float 1.0'                      => array( 1.0 ),
			'false'                          => array( false ),
			'true'                           => array( true ),
			'null'                           => array( null ),
			'an empty array'                 => array( array() ),
			'a non-empty array'              => array( array( 'value' ) ),
			'an empty object'                => array( new stdClass() ),
			'a non-empty object'             => array( (object) array( 'value' ) ),
			'INF'                            => array( INF ),
			'NAN'                            => array( NAN ),
		);
	}
}
