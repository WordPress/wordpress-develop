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
		add_option( 'foo', 'bar', '', false );
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
			'integer -123' => array( - 123 ),
			'float 12.3'   => array( 12.3 ),
			'float -1.23'  => array( - 1.23 ),
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
			// Supported values.
			array( 'autoload_true', true, 'on' ),
			array( 'autoload_false', false, 'off' ),
			array( 'autoload_null', null, 'auto' ),

			// Values supported for backward compatibility.
			array( 'autoload_yes', 'yes', 'on' ),
			array( 'autoload_no', 'no', 'off' ),

			// Technically unsupported values.
			array( 'autoload_string', 'foo', 'auto' ),
			array( 'autoload_int', 123456, 'auto' ),
			array( 'autoload_array', array(), 'auto' ),
		);
	}

	/**
	 * @ticket 42441
	 *
	 * @covers ::update_option
	 *
	 * @dataProvider data_option_autoloading_large_option
	 */
	public function test_update_option_autoloading_large_option( $autoload, $expected ) {
		global $wpdb;
		$name = 'foo';
		add_option( $name, 'bar' );
		add_filter( 'wp_max_autoloaded_option_size', array( $this, 'filter_max_option_size' ) );
		$value   = file( DIR_TESTDATA . '/formatting/entities.txt' );
		$updated = update_option( $name, $value, $autoload );
		$this->assertTrue( $updated );

		$actual = $wpdb->get_row( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s LIMIT 1", $name ) );
		$this->assertSame( $expected, $actual->autoload );
	}

	public function data_option_autoloading_large_option() {
		return array(
			'on'    => array(
				'autoload' => 'on',
				'expected' => 'on',
			),
			'off'   => array(
				'autoload' => 'off',
				'expected' => 'off',
			),
			'yes'   => array(
				'autoload' => 'yes',
				'expected' => 'on',
			),
			'true'  => array(
				'autoload' => true,
				'expected' => 'on',
			),
			'no'    => array(
				'autoload' => 'no',
				'expected' => 'off',
			),
			'false' => array(
				'autoload' => false,
				'expected' => 'off',
			),
			'null'  => array(
				'autoload' => null,
				'expected' => 'auto-off',
			),
		);
	}

	public function filter_max_option_size( $current ) {
		return 1000;
	}

	/**
	 * @ticket 42441
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_autoloading_small_option_auto() {
		global $wpdb;

		$name = 'foo';
		add_option( $name, 'bar' );
		$updated = update_option( $name, 'small_option_data' );
		$this->assertTrue( $updated );

		$actual = $wpdb->get_row( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s LIMIT 1", $name ) );
		$this->assertSame( 'auto', $actual->autoload );
	}

	/**
	 * Tests that calling update_option() with changed autoload from 'no' to 'yes' updates the cache correctly.
	 *
	 * This ensures that no stale data is served in case the option is deleted after.
	 *
	 * @ticket 51352
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_with_autoload_change_no_to_yes() {
		add_option( 'foo', 'value1', '', false );
		update_option( 'foo', 'value2', true );
		delete_option( 'foo' );
		$this->assertFalse( get_option( 'foo' ) );
	}

	/**
	 * Tests that calling update_option() with changed autoload from 'yes' to 'no' updates the cache correctly.
	 *
	 * This ensures that no stale data is served in case the option is deleted after.
	 *
	 * @ticket 51352
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_with_autoload_change_yes_to_no() {
		add_option( 'foo', 'value1', '', true );
		update_option( 'foo', 'value2', false );
		delete_option( 'foo' );
		$this->assertFalse( get_option( 'foo' ) );
	}

	/**
	 * Tests that calling delete_option() updates notoptions when option deleted.
	 *
	 * @ticket 61484
	 *
	 * @covers ::delete_option
	 */
	public function test_check_delete_option_updates_notoptions() {
		add_option( 'foo', 'value1' );

		delete_option( 'foo' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		$this->assertIsArray( $notoptions, 'The notoptions cache is expected to be an array.' );
		$this->assertTrue( $notoptions['foo'], 'The deleted options is expected to be in notoptions.' );

		$before = get_num_queries();
		get_option( 'foo' );
		$queries = get_num_queries() - $before;

		$this->assertSame( 0, $queries, 'get_option should not make any database queries.' );
	}

	/**
	 * Tests that calling update_option() clears the notoptions cache.
	 *
	 * @ticket 61484
	 *
	 * @covers ::update_option
	 */
	public function test_update_option_clears_the_notoptions_cache() {
		$option_name = 'ticket_61484_option_to_be_created';
		$notoptions  = wp_cache_get( 'notoptions', 'options' );
		if ( ! is_array( $notoptions ) ) {
			$notoptions = array();
		}
		$notoptions[ $option_name ] = true;
		wp_cache_set( 'notoptions', $notoptions, 'options' );
		$this->assertArrayHasKey( $option_name, wp_cache_get( 'notoptions', 'options' ), 'The "foobar" option should be in the notoptions cache.' );

		update_option( $option_name, 'baz' );

		$updated_notoptions = wp_cache_get( 'notoptions', 'options' );
		$this->assertArrayNotHasKey( $option_name, $updated_notoptions, 'The "foobar" option should not be in the notoptions cache after updating it.' );
	}

	/**
	 * Tests that calling add_option() clears the notoptions cache.
	 *
	 * @ticket 61484
	 *
	 * @covers ::add_option
	 */
	public function test_add_option_clears_the_notoptions_cache() {
		$option_name = 'ticket_61484_option_to_be_created';
		$notoptions  = wp_cache_get( 'notoptions', 'options' );
		if ( ! is_array( $notoptions ) ) {
			$notoptions = array();
		}
		$notoptions[ $option_name ] = true;
		wp_cache_set( 'notoptions', $notoptions, 'options' );
		$this->assertArrayHasKey( $option_name, wp_cache_get( 'notoptions', 'options' ), 'The "foobar" option should be in the notoptions cache.' );

		add_option( $option_name, 'baz' );

		$updated_notoptions = wp_cache_get( 'notoptions', 'options' );
		$this->assertArrayNotHasKey( $option_name, $updated_notoptions, 'The "foobar" option should not be in the notoptions cache after adding it.' );
	}
}
