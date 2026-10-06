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
}
