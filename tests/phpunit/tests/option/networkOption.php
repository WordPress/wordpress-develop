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
	 */
	function test_add_network_option_not_available_on_other_network() {
		$id     = self::factory()->network->create();
		$option = __FUNCTION__;
		$value  = __FUNCTION__;

		add_site_option( $option, $value );
		$this->assertFalse( get_network_option( $id, $option, false ) );
	}

	/**
	 * @group ms-required
	 */
	function test_add_network_option_available_on_same_network() {
		$id     = self::factory()->network->create();
		$option = __FUNCTION__;
		$value  = __FUNCTION__;

		add_network_option( $id, $option, $value );
		$this->assertEquals( $value, get_network_option( $id, $option, false ) );
	}

	/**
	 * @group ms-required
	 */
	function test_delete_network_option_on_only_one_network() {
		$id     = self::factory()->network->create();
		$option = __FUNCTION__;
		$value  = __FUNCTION__;

		add_site_option( $option, $value );
		add_network_option( $id, $option, $value );
		delete_site_option( $option );
		$this->assertEquals( $value, get_network_option( $id, $option, false ) );
	}

	/**
	 * @ticket 22846
	 * @group ms-excluded
	 */
	public function test_add_network_option_is_not_stored_as_autoload_option() {
		$key = __FUNCTION__;

		add_network_option( null, $key, 'Not an autoload option' );

		$options = wp_load_alloptions();

		$this->assertFalse( isset( $options[ $key ] ) );
	}

	/**
	 * @ticket 22846
	 * @group ms-excluded
	 */
	public function test_update_network_option_is_not_stored_as_autoload_option() {
		$key = __FUNCTION__;

		update_network_option( null, $key, 'Not an autoload option' );

		$options = wp_load_alloptions();

		$this->assertFalse( isset( $options[ $key ] ) );
	}

	/**
	 * @dataProvider data_network_id_parameter
	 *
	 * @param $network_id
	 * @param $expected_response
	 */
	function test_add_network_option_network_id_parameter( $network_id, $expected_response ) {
		$option = rand_str();
		$value  = rand_str();

		$this->assertEquals( $expected_response, add_network_option( $network_id, $option, $value ) );
	}

	/**
	 * @dataProvider data_network_id_parameter
	 *
	 * @param $network_id
	 * @param $expected_response
	 */
	function test_get_network_option_network_id_parameter( $network_id, $expected_response ) {
		$option = rand_str();

		$this->assertEquals( $expected_response, get_network_option( $network_id, $option, true ) );
	}

	function data_network_id_parameter() {
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
		$this->assertEquals( $num_queries_pre_update, get_num_queries() );
	}
}
