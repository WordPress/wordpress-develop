<?php

/**
 * Tests specific to managing network options in multisite.
 *
 * Some tests will run in single site as the `_network_option()` functions
 * are available and internally use `_option()` functions as fallbacks.
 *
 * @group option
 * @group ms-option
 * @group multisite
 */
class Tests_Option_NetworkOption extends WP_UnitTestCase {

	/**
	 * @group ms-required
	 *
	 * @covers ::add_site_option
	 */
	public function test_add_network_option_not_available_on_other_network() {
		$id     = self::factory()->network->create();
		$option = __FUNCTION__;
		$value  = __FUNCTION__;

		add_site_option( $option, $value );
		$this->assertFalse( get_network_option( $id, $option, false ) );
	}

	/**
	 * @group ms-required
	 *
	 * @covers ::add_network_option
	 */
	public function test_add_network_option_available_on_same_network() {
		$id     = self::factory()->network->create();
		$option = __FUNCTION__;
		$value  = __FUNCTION__;

		add_network_option( $id, $option, $value );
		$this->assertSame( $value, get_network_option( $id, $option, false ) );
	}

	/**
	 * @group ms-required
	 *
	 * @covers ::delete_site_option
	 */
	public function test_delete_network_option_on_only_one_network() {
		$id     = self::factory()->network->create();
		$option = __FUNCTION__;
		$value  = __FUNCTION__;

		add_site_option( $option, $value );
		add_network_option( $id, $option, $value );
		delete_site_option( $option );
		$this->assertSame( $value, get_network_option( $id, $option, false ) );
	}

	/**
	 * @ticket 22846
	 * @group ms-excluded
	 *
	 * @covers ::add_network_option
	 */
	public function test_add_network_option_is_not_stored_as_autoload_option() {
		$key = __FUNCTION__;

		add_network_option( null, $key, 'Not an autoload option' );

		$options = wp_load_alloptions();

		$this->assertArrayNotHasKey( $key, $options );
	}

	/**
	 * @ticket 22846
	 * @group ms-excluded
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_is_not_stored_as_autoload_option() {
		$key = __FUNCTION__;

		update_network_option( null, $key, 'Not an autoload option' );

		$options = wp_load_alloptions();

		$this->assertArrayNotHasKey( $key, $options );
	}

	/**
	 * @dataProvider data_network_id_parameter
	 *
	 * @param $network_id
	 * @param $expected_response
	 *
	 * @covers ::add_network_option
	 */
	public function test_add_network_option_network_id_parameter( $network_id, $expected_response ) {
		$option = rand_str();
		$value  = rand_str();

		$this->assertSame( $expected_response, add_network_option( $network_id, $option, $value ) );
	}

	/**
	 * @dataProvider data_network_id_parameter
	 *
	 * @param $network_id
	 * @param $expected_response
	 *
	 * @covers ::get_network_option
	 */
	public function test_get_network_option_network_id_parameter( $network_id, $expected_response ) {
		$option = rand_str();

		$this->assertSame( $expected_response, get_network_option( $network_id, $option, true ) );
	}

	public function data_network_id_parameter() {
		return array(
			// Numeric values should always be accepted.
			array( 1, true ),
			array( '1', true ),
			array( 2, true ),

			// Null, false, and zero will be treated as the current network.
			array( null, true ),
			array( false, true ),
			array( 0, true ),
			array( '0', true ),

			// Other truthy or string values should be rejected.
			array( true, false ),
			array( 'string', false ),
		);
	}

	/**
	 * @ticket 43506
	 * @group ms-required
	 *
	 * @covers ::get_network_option
	 * @covers ::wp_cache_get
	 * @covers ::wp_cache_delete
	 */
	public function test_get_network_option_sets_notoptions_if_option_found() {
		$network_id     = get_current_network_id();
		$notoptions_key = "$network_id:notoptions";

		$original_cache = wp_cache_get( $notoptions_key, 'site-options' );
		if ( false !== $original_cache ) {
			wp_cache_delete( $notoptions_key, 'site-options' );
		}

		// Retrieve any existing option.
		get_network_option( $network_id, 'site_name' );

		$cache = wp_cache_get( $notoptions_key, 'site-options' );
		if ( false !== $original_cache ) {
			wp_cache_set( $notoptions_key, $original_cache, 'site-options' );
		}

		$this->assertSame( array(), $cache );
	}

	/**
	 * @ticket 43506
	 * @group ms-required
	 *
	 * @covers ::get_network_option
	 * @covers ::wp_cache_get
	 */
	public function test_get_network_option_sets_notoptions_if_option_not_found() {
		$network_id     = get_current_network_id();
		$notoptions_key = "$network_id:notoptions";

		$original_cache = wp_cache_get( $notoptions_key, 'site-options' );
		if ( false !== $original_cache ) {
			wp_cache_delete( $notoptions_key, 'site-options' );
		}

		// Retrieve any non-existing option.
		get_network_option( $network_id, 'this_does_not_exist' );

		$cache = wp_cache_get( $notoptions_key, 'site-options' );
		if ( false !== $original_cache ) {
			wp_cache_set( $notoptions_key, $original_cache, 'site-options' );
		}

		$this->assertSame( array( 'this_does_not_exist' => true ), $cache );
	}

	/**
	 * Ensure updating network options containing an object do not result in unneeded database calls.
	 *
	 * @ticket 44956
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_array_with_object() {
		$array_w_object = array(
			'url'       => 'http://src.wordpress-develop.dev/wp-content/uploads/2016/10/cropped-Blurry-Lights.jpg',
			'meta_data' => (object) array(
				'attachment_id' => 292,
				'height'        => 708,
				'width'         => 1260,
			),
		);

		$array_w_object_2 = array(
			'url'       => 'http://src.wordpress-develop.dev/wp-content/uploads/2016/10/cropped-Blurry-Lights.jpg',
			'meta_data' => (object) array(
				'attachment_id' => 292,
				'height'        => 708,
				'width'         => 1260,
			),
		);

		// Add the option, it did not exist before this.
		add_network_option( null, 'array_w_object', $array_w_object );

		$num_queries_pre_update = get_num_queries();

		// Update the option using the same array with an object for the value.
		$this->assertFalse( update_network_option( null, 'array_w_object', $array_w_object_2 ) );

		// Check that no new database queries were performed.
		$this->assertSame( $num_queries_pre_update, get_num_queries() );
	}

	/**
	 * Test cases for testing whether update_network_option() will add a non-existent option.
	 */
	public function data_option_values() {
		return array(
			array( '1' ),
			array( 1 ),
			array( 1.0 ),
			array( true ),
			array( 'true' ),
			array( '0' ),
			array( 0 ),
			array( 0.0 ),
			array( false ),
			array( '' ),
			array( null ),
			array( array() ),
		);
	}

	/**
	 * Tests that a non-existent option is added only when the pre-filter matches the default 'false'.
	 *
	 * @ticket 59360
	 * @dataProvider data_option_values
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_option_with_false_pre_filter_adds_missing_option( $option ) {
		// Filter the old option value to `false`.
		add_filter( 'pre_option_foo', '__return_false' );
		add_filter( 'pre_site_option_foo', '__return_false' );

		/*
		 * When the network option is equal to the filtered version, update option will bail early.
		 * Otherwise, The pre-filter will make the old option `false`, which is equal to the
		 * default value. This causes an add_network_option() to be triggered.
		 */
		if ( false === $option ) {
			$this->assertFalse( update_network_option( null, 'foo', $option ) );
		} else {
			$this->assertTrue( update_network_option( null, 'foo', $option ) );
		}
	}

	/**
	 * Tests that a non-existent option is never added when the pre-filter is not 'false'.
	 *
	 * @ticket 59360
	 * @dataProvider data_option_values
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_option_with_truthy_pre_filter_does_not_add_missing_option( $option ) {
		// Filter the old option value to `true`.
		add_filter( 'pre_option_foo', '__return_true' );
		add_filter( 'pre_site_option_foo', '__return_true' );

		$this->assertFalse( update_network_option( null, 'foo', $option ) );
	}

	/**
	 * Tests that an existing option is updated even when its pre filter returns the same value.
	 *
	 * @ticket 59360
	 * @dataProvider data_option_values
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_option_with_false_pre_filter_updates_option( $option ) {
		// Add the option with a value that is different than any updated.
		add_network_option( null, 'foo', 'bar' );

		// Force a return value of false.
		add_filter( 'pre_option_foo', '__return_false' );
		add_filter( 'pre_site_option_foo', '__return_false' );

		// This should succeed, since the pre-filtered option will be treated as the default.
		$this->assertTrue( update_network_option( null, 'foo', $option ) );
	}

	/**
	 * Tests that an existing option is updated even when its pre filter returns the same value.
	 *
	 * @ticket 59360
	 * @dataProvider data_option_values
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_option_with_true_pre_filter_updates_option( $option ) {
		// Add the option with a value that is different than any updated.
		update_network_option( null, 'foo', 'bar' );

		// Force a return value of true.
		add_filter( 'pre_option_foo', '__return_true' );
		add_filter( 'pre_site_option_foo', '__return_true' );

		/*
		 * If the option is the same as the pre-filtered value, the option should not
		 * be updated. Otherwise, the option should be updated regardless of the pre-filter.
		 */
		if ( true === $option ) {
			$this->assertFalse( update_network_option( null, 'foo', $option ) );
		} else {
			$this->assertTrue( update_network_option( null, 'foo', $option ) );
		}
	}
}
