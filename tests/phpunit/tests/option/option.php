<?php

/**
 * @group option
 */
class Tests_Option_Option extends WP_UnitTestCase {

	public function __return_foo() {
		return 'foo';
	}

	/**
	 * @covers ::get_option
	 * @covers ::add_option
	 * @covers ::update_option
	 * @covers ::delete_option
	 */
	public function test_the_basics() {
		$key    = 'key1';
		$key2   = 'key2';
		$value  = 'value1';
		$value2 = 'value2';

		$this->assertFalse( get_option( 'doesnotexist' ) );
		$this->assertTrue( add_option( $key, $value ) );
		$this->assertSame( $value, get_option( $key ) );
		$this->assertFalse( add_option( $key, $value ) );    // Already exists.
		$this->assertFalse( update_option( $key, $value ) ); // Value is the same.
		$this->assertTrue( update_option( $key, $value2 ) );
		$this->assertSame( $value2, get_option( $key ) );
		$this->assertFalse( add_option( $key, $value ) );
		$this->assertSame( $value2, get_option( $key ) );
		$this->assertTrue( delete_option( $key ) );
		$this->assertFalse( get_option( $key ) );
		$this->assertFalse( delete_option( $key ) );

		$this->assertTrue( update_option( $key2, $value2 ) );
		$this->assertSame( $value2, get_option( $key2 ) );
		$this->assertTrue( delete_option( $key2 ) );
		$this->assertFalse( get_option( $key2 ) );
	}

	/**
	 * @covers ::get_option
	 * @covers ::add_option
	 * @covers ::delete_option
	 */
	public function test_default_option_filter() {
		$value = 'value';

		$this->assertFalse( get_option( 'doesnotexist' ) );

		// Default filter overrides $default arg.
		add_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$this->assertSame( 'foo', get_option( 'doesnotexist', 'bar' ) );

		// Remove the filter and the $default arg is honored.
		remove_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$this->assertSame( 'bar', get_option( 'doesnotexist', 'bar' ) );

		// Once the option exists, the $default arg and the default filter are ignored.
		add_option( 'doesnotexist', $value );
		$this->assertSame( $value, get_option( 'doesnotexist', 'foo' ) );
		add_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$this->assertSame( $value, get_option( 'doesnotexist', 'foo' ) );
		remove_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );

		// Cleanup.
		$this->assertTrue( delete_option( 'doesnotexist' ) );
		$this->assertFalse( get_option( 'doesnotexist' ) );
	}

	/**
	 * @ticket 31047
	 *
	 * @covers ::get_option
	 * @covers ::add_option
	 */
	public function test_add_option_should_respect_default_option_filter() {
		add_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$added = add_option( 'doesnotexist', 'bar' );
		remove_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );

		$this->assertTrue( $added );
		$this->assertSame( 'bar', get_option( 'doesnotexist' ) );
	}

	/**
	 * @ticket 37930
	 *
	 * @covers ::get_option
	 */
	public function test_get_option_should_call_pre_option_filter() {
		$filter = new MockAction();

		add_filter( 'pre_option', array( $filter, 'filter' ) );

		get_option( 'ignored' );

		$this->assertSame( 1, $filter->get_call_count() );
	}

	/**
	 * @covers ::get_option
	 * @covers ::add_option
	 * @covers ::delete_option
	 * @covers ::update_option
	 */
	public function test_serialized_data() {
		$key   = __FUNCTION__;
		$value = array(
			'foo' => true,
			'bar' => true,
		);

		$this->assertTrue( add_option( $key, $value ) );
		$this->assertSame( $value, get_option( $key ) );

		$value = (object) $value;
		$this->assertTrue( update_option( $key, $value ) );
		$this->assertEquals( $value, get_option( $key ) );
		$this->assertTrue( delete_option( $key ) );
	}

	/**
	 * @ticket 23289
	 *
	 * @dataProvider data_bad_option_names
	 *
	 * @param mixed $option_name Option name.
	 *
	 * @covers ::get_option
	 */
	public function test_get_option_bad_option_name( $option_name ) {
		$this->assertFalse( get_option( $option_name ) );
	}

	/**
	 * @ticket 23289
	 *
	 * @dataProvider data_bad_option_names
	 *
	 * @param mixed $option_name Option name.
	 *
	 * @covers ::add_option
	 */
	public function test_add_option_bad_option_name( $option_name ) {
		$this->assertFalse( add_option( $option_name, '' ) );
	}

	/**
	 * @ticket 23289
	 *
	 * @dataProvider data_bad_option_names
	 *
	 * @param mixed $option_name Option name.
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_bad_option_name( $option_name ) {
		$this->assertFalse( update_option( $option_name, '' ) );
	}

	/**
	 * @ticket 23289
	 *
	 * @dataProvider data_bad_option_names
	 *
	 * @param mixed $option_name Option name.
	 *
	 * @covers ::delete_option
	 */
	public function test_delete_option_bad_option_name( $option_name ) {
		$this->assertFalse( delete_option( $option_name ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_bad_option_names() {
		return array(
			'empty string'        => array( '' ),
			'string 0'            => array( '0' ),
			'string single space' => array( ' ' ),
			'integer 0'           => array( 0 ),
			'float 0.0'           => array( 0.0 ),
			'boolean false'       => array( false ),
			'null'                => array( null ),
		);
	}

	/**
	 * @ticket 53635
	 *
	 * @dataProvider data_valid_but_undesired_option_names
	 *
	 * @param mixed $option_name Option name.
	 *
	 * @covers ::get_option
	 */
	public function test_get_option_valid_but_undesired_option_names( $option_name ) {
		$this->assertFalse( get_option( $option_name ) );
	}

	/**
	 * @ticket 53635
	 *
	 * @dataProvider data_valid_but_undesired_option_names
	 *
	 * @param mixed $option_name Option name.
	 *
	 * @covers ::add_option
	 */
	public function test_add_option_valid_but_undesired_option_names( $option_name ) {
		$this->assertTrue( add_option( $option_name, '' ) );
	}

	/**
	 * @ticket 53635
	 *
	 * @dataProvider data_valid_but_undesired_option_names
	 *
	 * @param mixed $option_name Option name.
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_valid_but_undesired_option_names( $option_name ) {
		$this->assertTrue( update_option( $option_name, '' ) );
	}

	/**
	 * @ticket 53635
	 *
	 * @dataProvider data_valid_but_undesired_option_names
	 *
	 * @param mixed $option_name Option name.
	 *
	 * @covers ::delete_option
	 */
	public function test_delete_option_valid_but_undesired_option_names( $option_name ) {
		$this->assertFalse( delete_option( $option_name ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_valid_but_undesired_option_names() {
		return array(
			'string 123'   => array( '123' ),
			'integer 123'  => array( 123 ),
			'integer -123' => array( -123 ),
			'float 12.3'   => array( 12.3 ),
			'float -1.23'  => array( -1.23 ),
			'boolean true' => array( true ),
		);
	}

	/**
	 * @ticket 23289
	 *
	 * @covers ::delete_option
	 */
	public function test_special_option_name_alloption() {
		$this->expectException( 'WPDieException' );
		delete_option( 'alloptions' );
	}

	/**
	 * @ticket 23289
	 *
	 * @covers ::delete_option
	 */
	public function test_special_option_name_notoptions() {
		$this->expectException( 'WPDieException' );
		delete_option( 'notoptions' );
	}

	/**
	 * Options should be autoloaded unless they were added with "no" or `false`.
	 *
	 * @ticket 31119
	 * @dataProvider data_option_autoloading
	 *
	 * @covers ::add_option
	 */
	public function test_option_autoloading( $name, $autoload_value, $expected ) {
		global $wpdb;
		$added = add_option( $name, 'Autoload test', '', $autoload_value );
		$this->assertTrue( $added );

		$actual = $wpdb->get_row( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s LIMIT 1", $name ) );
		$this->assertSame( $expected, $actual->autoload );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_option_autoloading() {
		return array(
			array( 'autoload_yes', 'yes', 'yes' ),
			array( 'autoload_true', true, 'yes' ),
			array( 'autoload_string', 'foo', 'yes' ),
			array( 'autoload_int', 123456, 'yes' ),
			array( 'autoload_array', array(), 'yes' ),
			array( 'autoload_no', 'no', 'no' ),
			array( 'autoload_false', false, 'no' ),
		);
	}

	/**
	 * Test prime_options function.
	 *
	 * @ticket 58962
	 *
	 * @covers ::prime_options
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

		// Check that options are now in the cache.
		foreach ( $options_to_prime as $option ) {
			$this->assertSame(
				wp_cache_get( $option, 'options' ),
				get_option( $option ),
				"$option was not primed."
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
	 * Test prime_options_by_group function.
	 *
	 * @ticket 58962
	 *
	 * @covers ::prime_options_by_group
	 */
	public function test_prime_options_by_group() {
		// Create some options to prime.
		$new_allowed_options = array(
			'group1' => array(
				'option1',
				'option2',
			),
			'group2' => array(
				'option3',
			),
		);

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

		// Call the prime_options_by_group function to prime the options.
		prime_options_by_group( 'group1' );

		// Check that options are now in the cache.
		$this->assertSame( get_option( 'option1' ), wp_cache_get( 'option1', 'options' ), 'option1 was not primed.' );
		$this->assertSame( get_option( 'option2' ), wp_cache_get( 'option2', 'options' ), 'option2 was not primed.' );

		// Make sure option3 is still not in cache.
		$this->assertFalse( wp_cache_get( 'option3', 'options' ), 'option3 was not deleted from the cache.' );
	}

	/**
	 * Test get_options function.
	 *
	 * @ticket 58962
	 *
	 * @covers ::prime_options
	 * @covers ::get_options
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

		// Call the prime_options function to prime the options.
		prime_options( $options_to_prime );

		// Check that options are now in the cache.
		foreach ( $options_to_prime as $option ) {
			$this->assertSame( wp_cache_get( $option, 'options' ), get_option( $option ), "$option was not primed." );
		}

		// Call the get_options function to retrieve the options.
		$options = get_options( array( 'option1', 'option2' ) );

		// Check that the retrieved options are correct.
		$this->assertSame( get_option( 'option1' ), $options['option1'] );
		$this->assertSame( get_option( 'option2' ), $options['option2'] );
	}

	/**
	 * Test get_options with an empty input array.
	 *
	 * @ticket 58962
	 *
	 * @covers ::get_options
	 */
	public function test_get_options_with_empty_array() {
		// Call the get_options function with an empty array.
		$options = get_options( array() );

		// Make sure the result is an empty array.
		$this->assertEmpty( $options );
	}

	/**
	 * Test prime_options with options that do not exist in the database.
	 *
	 * @ticket 58962
	 *
	 * @covers ::prime_options
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

	/**
	 * Test get_options with options that include some nonexistent options.
	 *
	 * @ticket 58962
	 *
	 * @covers ::prime_options
	 * @covers ::get_options
	 */
	public function test_get_options_with_nonexistent_options() {
		// Create some options to prime.
		$options_to_prime = array(
			'option1',
		);

		// Make sure options are not in cache or database initially.
		$this->assertFalse( wp_cache_get( 'option1', 'options' ) );
		$this->assertFalse( wp_cache_get( 'nonexistent_option', 'options' ) );

		// Call the prime_options function to prime the options.
		prime_options( $options_to_prime );

		// Call the get_options function with an array that includes a nonexistent option.
		$options = get_options( array( 'option1', 'nonexistent_option' ) );

		// Check that the retrieved options are correct.
		$this->assertSame( get_option( 'option1' ), $options['option1'] );

		// Check that options are present in the notoptions cache.
		$new_notoptions = wp_cache_get( 'notoptions', 'options' );
		foreach ( $options_to_prime as $option ) {
			$this->assertTrue( isset( $new_notoptions[ $option ] ), "$option was not added to the notoptions cache." );
		}

		// Check that the nonexistent option is in the result array.
		$this->assertArrayHasKey( 'nonexistent_option', $options );

		$this->assertFalse( $options['nonexistent_option'] );
	}

	/**
	 * Test prime_options_by_group with a nonexistent option group.
	 *
	 * @ticket 58962
	 *
	 * @covers ::prime_options_by_group
	 */
	public function test_prime_options_by_group_with_nonexistent_group() {
		// Make sure options are not in cache or database initially.
		$this->assertFalse( wp_cache_get( 'option1', 'options' ) );
		$this->assertFalse( wp_cache_get( 'option2', 'options' ) );

		// Call the prime_options_by_group function with a nonexistent group.
		prime_options_by_group( 'nonexistent_group' );

		// Check that options are still not in the cache or database.
		$this->assertFalse( wp_cache_get( 'option1', 'options' ) );
		$this->assertFalse( wp_cache_get( 'option2', 'options' ) );
	}
}
