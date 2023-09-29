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
	 * @ticket 58277
	 *
	 * @covers ::get_option
	 */
	public function test_get_option_notoptions_cache() {
		$notoptions = array(
			'invalid' => true,
		);
		wp_cache_set( 'notoptions', $notoptions, 'options' );

		$before = get_num_queries();
		$value  = get_option( 'invalid' );
		$after  = get_num_queries();

		$this->assertSame( 0, $after - $before );
	}

	/**
	 * @ticket 58277
	 *
	 * @covers ::get_option
	 */
	public function test_get_option_notoptions_set_cache() {
		get_option( 'invalid' );

		$before = get_num_queries();
		$value  = get_option( 'invalid' );
		$after  = get_num_queries();

		$notoptions = wp_cache_get( 'notoptions', 'options' );

		$this->assertSame( 0, $after - $before, 'The notoptions cache was not hit on the second call to `get_option()`.' );
		$this->assertIsArray( $notoptions, 'The notoptions cache should be set.' );
		$this->assertArrayHasKey( 'invalid', $notoptions, 'The "invalid" option should be in the notoptions cache.' );
	}

	/**
	 * @ticket 58277
	 *
	 * @covers ::get_option
	 */
	public function test_get_option_notoptions_do_not_load_cache() {
		add_option( 'foo', 'bar', '', 'no' );
		wp_cache_delete( 'notoptions', 'options' );

		$before = get_num_queries();
		$value  = get_option( 'foo' );
		$after  = get_num_queries();

		$notoptions = wp_cache_get( 'notoptions', 'options' );

		$this->assertSame( 0, $after - $before, 'The options cache was not hit on the second call to `get_option()`.' );
		$this->assertFalse( $notoptions, 'The notoptions cache should not be set.' );
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
	 * Tests that update_option() triggers one additional query and returns true
	 * for some loosely equal old and new values when the old value is retrieved from the cache.
	 *
	 * The additional query is triggered to update the value in the database.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 *
	 * @dataProvider data_loosely_equal_values_that_should_update
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value to try to set.
	 */
	public function test_update_option_should_update_some_loosely_equal_values_from_cache( $old_value, $new_value ) {
		add_option( 'foo', $old_value );

		$num_queries = get_num_queries();

		// Comparison will happen against value cached during add_option() above.
		$updated = update_option( 'foo', $new_value );

		$this->assertSame( 1, get_num_queries() - $num_queries, 'One additional query should have run to update the value.' );
		$this->assertTrue( $updated, 'update_option() should have returned true.' );
	}

	/**
	 * Tests that update_option() triggers two additional queries and returns true
	 * for some loosely equal old and new values when the old value is retrieved from the database.
	 *
	 * The two additional queries are triggered to:
	 * 1. retrieve the old value from the database, as the option does not exist in the cache.
	 * 2. update the value in the database.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 *
	 * @dataProvider data_loosely_equal_values_that_should_update
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value to try to set.
	 */
	public function test_update_option_should_update_some_loosely_equal_values_from_db( $old_value, $new_value ) {
		add_option( 'foo', $old_value );

		$num_queries = get_num_queries();

		// Delete cache.
		wp_cache_delete( 'alloptions', 'options' );
		$updated = update_option( 'foo', $new_value );

		$this->assertSame( 2, get_num_queries() - $num_queries, 'Two additional queries should have run.' );
		$this->assertTrue( $updated, 'update_option() should have returned true.' );
	}

	/**
	 * Tests that update_option() triggers one additional query and returns true
	 * for some loosely equal old and new values when the old value is retrieved from a refreshed cache.
	 *
	 * The additional query is triggered to update the value in the database.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 *
	 * @dataProvider data_loosely_equal_values_that_should_update
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value to try to set.
	 */
	public function test_update_option_should_update_some_loosely_equal_values_from_refreshed_cache( $old_value, $new_value ) {
		add_option( 'foo', $old_value );

		// Delete and refresh cache from DB.
		wp_cache_delete( 'alloptions', 'options' );
		wp_load_alloptions();

		$num_queries = get_num_queries();
		$updated     = update_option( 'foo', $new_value );

		$this->assertSame( 1, get_num_queries() - $num_queries, 'One additional query should have run to update the value.' );
		$this->assertTrue( $updated, 'update_option() should have returned true.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_loosely_equal_values_that_should_update() {
		return array(
			// Falsey values.
			'(string) "0" to false'       => array( '0', false ),
			'empty string to (int) 0'     => array( '', 0 ),
			'empty string to (float) 0.0' => array( '', 0.0 ),
			'(int) 0 to empty string'     => array( 0, '' ),
			'(int) 0 to false'            => array( 0, false ),
			'(float) 0.0 to empty string' => array( 0.0, '' ),
			'(float) 0.0 to false'        => array( 0.0, false ),
			'false to (string) "0"'       => array( false, '0' ),
			'false to (int) 0'            => array( false, 0 ),
			'false to (float) 0.0'        => array( false, 0.0 ),

			// Non-scalar values.
			'false to array()'            => array( false, array() ),
			'(string) "false" to array()' => array( 'false', array() ),
			'empty string to array()'     => array( '', array() ),
			'(int 0) to array()'          => array( 0, array() ),
			'(string) "0" to array()'     => array( '0', array() ),
			'(string) "false" to null'    => array( 'false', null ),
			'(int) 0 to null'             => array( 0, null ),
			'(string) "0" to null'        => array( '0', null ),
			'array() to false'            => array( array(), false ),
			'array() to (string) "false"' => array( array(), 'false' ),
			'array() to empty string'     => array( array(), '' ),
			'array() to (int) 0'          => array( array(), 0 ),
			'array() to (string) "0"'     => array( array(), '0' ),
			'array() to null'             => array( array(), null ),
		);
	}

	/**
	 * Tests that update_option() triggers no additional queries and returns false
	 * for some values when the old value is retrieved from the cache.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 *
	 * @dataProvider data_loosely_equal_values_that_should_not_update
	 * @dataProvider data_strictly_equal_values
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value to try to set.
	 */
	public function test_update_option_should_not_update_some_values_from_cache( $old_value, $new_value ) {
		add_option( 'foo', $old_value );

		$num_queries = get_num_queries();

		// Comparison will happen against value cached during add_option() above.
		$updated = update_option( 'foo', $new_value );

		$this->assertSame( $num_queries, get_num_queries(), 'No additional queries should have run.' );
		$this->assertFalse( $updated, 'update_option() should have returned false.' );
	}

	/**
	 * Tests that update_option() triggers one additional query and returns false
	 * for some values when the old value is retrieved from the database.
	 *
	 * The additional query is triggered to retrieve the old value from the database.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 *
	 * @dataProvider data_loosely_equal_values_that_should_not_update
	 * @dataProvider data_strictly_equal_values
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value to try to set.
	 */
	public function test_update_option_should_not_update_some_values_from_db( $old_value, $new_value ) {
		add_option( 'foo', $old_value );

		$num_queries = get_num_queries();

		// Delete cache.
		wp_cache_delete( 'alloptions', 'options' );
		$updated = update_option( 'foo', $new_value );

		$this->assertSame( 1, get_num_queries() - $num_queries, 'One additional query should have run.' );
		$this->assertFalse( $updated, 'update_option() should have returned false.' );
	}

	/**
	 * Tests that update_option() triggers no additional queries and returns false
	 * for some values when the old value is retrieved from a refreshed cache.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 *
	 * @dataProvider data_loosely_equal_values_that_should_not_update
	 * @dataProvider data_strictly_equal_values
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value to try to set.
	 */
	public function test_update_option_should_not_update_some_values_from_refreshed_cache( $old_value, $new_value ) {
		add_option( 'foo', $old_value );

		// Delete and refresh cache from DB.
		wp_cache_delete( 'alloptions', 'options' );
		wp_load_alloptions();

		$num_queries = get_num_queries();
		$updated     = update_option( 'foo', $new_value );

		$this->assertSame( $num_queries, get_num_queries(), 'No additional queries should have run.' );
		$this->assertFalse( $updated, 'update_option() should have returned false.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_loosely_equal_values_that_should_not_update() {
		return array(
			// Truthy values.
			'(string) "1" to (int) 1'     => array( '1', 1 ),
			'(string) "1" to (float) 1.0' => array( '1', 1.0 ),
			'(string) "1" to true'        => array( '1', true ),
			'(int) 1 to (string) "1"'     => array( 1, '1' ),
			'1 to (float) 1.0'            => array( 1, 1.0 ),
			'(int) 1 to true'             => array( 1, true ),
			'(float) 1.0 to (string) "1"' => array( 1.0, '1' ),
			'(float) 1.0 to (int) 1'      => array( 1.0, 1 ),
			'1.0 to true'                 => array( 1.0, true ),
			'true to (string) "1"'        => array( true, '1' ),
			'true to 1'                   => array( true, 1 ),
			'true to (float) 1.0'         => array( true, 1.0 ),

			// Falsey values.
			'(string) "0" to (int) 0'     => array( '0', 0 ),
			'(string) "0" to (float) 0.0' => array( '0', 0.0 ),
			'(int) 0 to (string) "0"'     => array( 0, '0' ),
			'(int) 0 to (float) 0.0'      => array( 0, 0.0 ),
			'(float) 0.0 to (string) "0"' => array( 0.0, '0' ),
			'(float) 0.0 to (int) 0'      => array( 0.0, 0 ),
			'empty string to false'       => array( '', false ),
			'empty string to null'        => array( '', null ),

			/*
			 * null as an initial value behaves differently by triggering
			 * a query, so it is not included in these datasets.
			 *
			 * See data_stored_as_empty_string() and its related test.
			 */
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_strictly_equal_values() {
		$obj = new stdClass();

		return array(
			// Truthy values.
			'(string) "1"'       => array( '1', '1' ),
			'(int) 1'            => array( 1, 1 ),
			'(float) 1.0'        => array( 1.0, 1.0 ),
			'true'               => array( true, true ),
			'string with spaces' => array( ' ', ' ' ),
			'non-empty array'    => array( array( 'false' ), array( 'false' ) ),
			'object'             => array( $obj, $obj ),

			// Falsey values.
			'(string) "0"'       => array( '0', '0' ),
			'empty string'       => array( '', '' ),
			'(int) 0'            => array( 0, 0 ),
			'(float) 0.0'        => array( 0.0, 0.0 ),
			'empty array'        => array( array(), array() ),
			'false'              => array( false, false ),

			/*
			 * false and null are not included in these datasets
			 * because false is the default value, which triggers
			 * a call to add_option().
			 *
			 * See data_stored_as_empty_string() and its related test.
			 */
		);
	}

	/**
	 * Tests that update_option() adds a non-existent option when the new value
	 * is stored as an empty string and false is the default value for the option.
	 *
	 * @ticket 22192
	 *
	 * @dataProvider data_stored_as_empty_string
	 *
	 * @param mixed $new_value A value that casts to an empty string.
	 */
	public function test_update_option_should_add_option_when_the_new_value_is_stored_as_an_empty_string_and_matches_default_value_false( $new_value ) {
		global $wpdb;

		$this->assertTrue( update_option( 'foo', $new_value ), 'update_option() should have returned true.' );

		$actual = $wpdb->get_row( "SELECT option_value FROM $wpdb->options WHERE option_name = 'foo' LIMIT 1" );

		$this->assertIsObject( $actual, 'The option was not added to the database.' );
		$this->assertObjectHasProperty( 'option_value', $actual, 'The "option_value" property was not included.' );
		$this->assertSame( '', $actual->option_value, 'The value was not stored as an empty string.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_stored_as_empty_string() {
		return array(
			'empty string' => array( '' ),
			'null'         => array( null ),
		);
	}

	/**
	 * Tests that update_option() adds a non-existent option that uses a filtered default value.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_should_add_option_with_filtered_default_value() {
		global $wpdb;

		$option        = 'update_option_custom_default';
		$default_value = 'default-value';

		add_filter(
			"default_option_{$option}",
			static function () use ( $default_value ) {
				return $default_value;
			}
		);

		$this->assertTrue( update_option( $option, 'new value' ), 'update_option() should have returned true.' );

		$actual = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
				$option
			)
		);

		$this->assertIsObject( $actual, 'The option was not added to the database.' );
		$this->assertObjectHasProperty( 'option_value', $actual, 'The "option_value" property was not included.' );
		$this->assertSame( 'new value', $actual->option_value, 'The new value was not stored in the database.' );
	}

	/**
	 * Tests that a non-existent option is added even when its pre filter returns a value.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_with_pre_filter_adds_missing_option() {
		// Force a return value of integer 0.
		add_filter( 'pre_option_foo', '__return_zero' );

		/*
		 * This should succeed, since the 'foo' option does not exist in the database.
		 * The default value is false, so it differs from 0.
		 */
		$this->assertTrue( update_option( 'foo', 0 ) );
	}

	/**
	 * Tests that an existing option is updated even when its pre filter returns the same value.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_with_pre_filter_updates_option_with_different_value() {
		// Add the option with a value of 1 to the database.
		add_option( 'foo', 1 );

		// Force a return value of integer 0.
		add_filter( 'pre_option_foo', '__return_zero' );

		/*
		 * This should succeed, since the 'foo' option has a value of 1 in the database.
		 * Therefore it differs from 0 and should be updated.
		 */
		$this->assertTrue( update_option( 'foo', 0 ) );
	}

	/**
	 * Tests that calling update_option() does not permanently remove pre filters.
	 *
	 * @ticket 22192
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_maintains_pre_filters() {
		add_filter( 'pre_option_foo', '__return_zero' );
		update_option( 'foo', 0 );

		// Assert that the filter is still present.
		$this->assertSame( 10, has_filter( 'pre_option_foo', '__return_zero' ) );
	}
}
