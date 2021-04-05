<?php

/**
 * @group option
 */
class Tests_Option_Option extends WP_UnitTestCase {

	function __return_foo() {
		return 'foo';
	}

	function test_the_basics() {
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

	function test_default_filter() {
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
	 */
	public function test_add_option_should_respect_default_option_filter() {
		add_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );
		$added = add_option( 'doesnotexist', 'bar' );
		remove_filter( 'default_option_doesnotexist', array( $this, '__return_foo' ) );

		$this->assertTrue( $added );
		$this->assertSame( 'bar', get_option( 'doesnotexist' ) );
	}

	function test_serialized_data() {
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
	 */
	function test_bad_option_names() {
		foreach ( array( '', '0', ' ', 0, false, null ) as $empty ) {
			$this->assertFalse( get_option( $empty ) );
			$this->assertFalse( add_option( $empty, '' ) );
			$this->assertFalse( update_option( $empty, '' ) );
			$this->assertFalse( delete_option( $empty ) );
		}
	}

	/**
	 * @ticket 23289
	 */
	function test_special_option_name_alloption() {
		$this->expectException( 'WPDieException' );
		delete_option( 'alloptions' );
	}

	/**
	 * @ticket 23289
	 */
	function test_special_option_name_notoptions() {
		$this->expectException( 'WPDieException' );
		delete_option( 'notoptions' );
	}

	function data_option_autoloading() {
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
	 * Options should be autoloaded unless they were added with "no" or `false`.
	 *
	 * @ticket 31119
	 * @dataProvider data_option_autoloading
	 */
	function test_option_autoloading( $name, $autoload_value, $expected ) {
		global $wpdb;
		$added = add_option( $name, 'Autoload test', '', $autoload_value );
		$this->assertTrue( $added );

		$actual = $wpdb->get_row( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s LIMIT 1", $name ) );
		$this->assertSame( $expected, $actual->autoload );
	}

	/**
	 * @ticket 52798
	 */
	function test_delete_option_with_cache_handling() {
		// Setup sample_option
		$this->assertFalse( get_option( 'sample_option', false ) );
		$this->assertTrue( update_option( 'sample_option', 'sample_value', false ) );
		$this->assertEquals( 'sample_value', get_option( 'sample_option', false ) );
		// Test the cache
		$this->assertEquals( 'sample_value', wp_cache_get( 'sample_option', 'options' ) );

		// Delete the option directly.
		global $wpdb;
		$delete = $wpdb->delete( $wpdb->options, array( 'option_name' => 'sample_option' ) );
		$this->assertEquals( 1, $delete );

		$this->assertFalse( delete_option( 'sample_option' ) );
		$this->assertFalse( get_option( 'sample_option', false ) );
		$this->assertFalse( wp_cache_get( 'sample_option', 'options' ) );
	}

	/**
	 * @ticket 52798
	 */
	function test_delete_option_autoload_with_cache_handling() {
		// Setup sample_option
		$this->assertFalse( get_option( 'sample_option', false ) );
		$this->assertTrue( update_option( 'sample_option', 'sample_value', true ) );
		$this->assertEquals( 'sample_value', get_option( 'sample_option', false ) );
		// Test the cache
		$alloptions = wp_load_alloptions( true );
		$this->assertTrue( array_key_exists( 'sample_option', $alloptions ) );
		$this->assertEquals( 'sample_value', $alloptions['sample_option'] );

		// Delete the option directly.
		global $wpdb;
		$delete = $wpdb->delete( $wpdb->options, array( 'option_name' => 'sample_option' ) );
		$this->assertEquals( 1, $delete );

		$this->assertFalse( delete_option( 'sample_option' ) );
		$new_alloptions = wp_load_alloptions( true );
		$this->assertFalse( array_key_exists( 'sample_option', $new_alloptions ) );
		$this->assertFalse( get_option( 'sample_option', false ) );
	}
}
