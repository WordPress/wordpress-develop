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
	 * @ticket 22192
	 *
	 * @covers ::add_option
	 */
	public function test_add_option_with_value_of_false_should_store_false_in_the_cache() {
		add_option( 'foo', false );
		$a = wp_cache_get( 'alloptions', 'options' );
		$this->assertSame( false, $a['foo'] );
	}

	/**
	 * @ticket 22192
	 *
	 * @covers ::add_option
	 */
	public function test_add_option_with_value_of_false_should_store_empty_string_in_the_database() {
		add_option( 'foo', false );

		// Delete cache to ensure we pull from the database.
		wp_cache_delete( 'alloptions', 'options' );

		$this->assertSame( '', get_option( 'foo' ) );
	}

	/**
	 * @ticket 22192
	 *
	 * @covers ::add_option
	 * @covers ::update_option
	 *
	 * @dataProvider data_update_option_type_juggling
	 *
	 * @param mixed $old_value One of the values to compare.
	 * @param mixed $new_value The other value to compare.
	 */
	public function test_update_option_should_hit_cache_when_loosely_equal_to_existing_value_and_cached_values_are_faithful_to_original_type( $old_value, $new_value ) {
		add_option( 'foo', $old_value );
		$num_queries = get_num_queries();

		$updated = update_option( 'foo', $new_value );

		$this->assertFalse( $updated, 'update_option should not return true when values are loosely equal.' );
		$this->assertSame( $num_queries, get_num_queries(), 'The number of database queries should not change.' );
	}

	/**
	 * @ticket 22192
	 *
	 * @covers ::add_option
	 * @covers ::update_option
	 *
	 * @dataProvider data_update_option_type_juggling
	 *
	 * @param mixed $old_value One of the values to compare.
	 * @param mixed $new_value The other value to compare.
	 */
	public function test_update_option_should_hit_cache_when_loosely_equal_to_existing_value_and_cached_values_are_pulled_from_the_database( $old_value, $new_value ) {
		add_option( 'foo', $old_value );
		wp_cache_delete( 'alloptions', 'options' );
		wp_load_alloptions();

		$num_queries = get_num_queries();

		$updated = update_option( 'foo', $new_value );

		$this->assertFalse( $updated, 'update_option should not return true when values are loosely equal.' );
		$this->assertSame( $num_queries, get_num_queries(), 'The number of database queries should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_update_option_type_juggling() {
		return array(
			// Truthy.
			array( '1', '1' ),
			array( '1', intval( 1 ) ),
			array( '1', floatval( 1 ) ),
			array( '1', true ),
			array( 1, '1' ),
			array( 1, intval( 1 ) ),
			array( 1, floatval( 1 ) ),
			array( 1, true ),
			array( floatval( 1 ), '1' ),
			array( floatval( 1 ), intval( 1 ) ),
			array( floatval( 1 ), floatval( 1 ) ),
			array( floatval( 1 ), true ),
			array( true, '1' ),
			array( true, intval( 1 ) ),
			array( true, floatval( 1 ) ),
			array( true, true ),

			// Falsey.
			array( '0', '0' ),
			array( '0', intval( 0 ) ),
			array( '0', floatval( 0 ) ),
			array( '0', false ),
			array( 0, '0' ),
			array( 0, intval( 0 ) ),
			array( 0, floatval( 0 ) ),
			array( 0, false ),
			array( floatval( 0 ), '0' ),
			array( floatval( 0 ), intval( 0 ) ),
			array( floatval( 0 ), floatval( 0 ) ),
			array( floatval( 0 ), false ),
			array( false, '0' ),
			array( false, intval( 0 ) ),
			array( false, floatval( 0 ) ),
			array( false, false ),
		);
	}
}
