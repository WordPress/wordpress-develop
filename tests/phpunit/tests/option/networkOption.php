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
	 * Tests that a non-existent option is added even when its pre filter returns a value.
	 *
	 * @ticket 59360
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_with_pre_filter_adds_missing_option() {
		$hook_name = is_multisite() ? 'pre_site_option_foo' : 'pre_option_foo';

		// Force a return value of integer 0.
		add_filter( $hook_name, '__return_zero' );

		/*
		 * This should succeed, since the 'foo' option does not exist in the database.
		 * The default value is false, so it differs from 0.
		 */
		$this->assertTrue( update_network_option( null, 'foo', 0 ) );
	}

	/**
	 * Tests that an existing option is updated even when its pre filter returns the same value.
	 *
	 * @ticket 59360
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_with_pre_filter_updates_option_with_different_value() {
		$hook_name = is_multisite() ? 'pre_site_option_foo' : 'pre_option_foo';

		// Add the option with a value of 1 to the database.
		update_network_option( null, 'foo', 1 );

		// Force a return value of integer 0.
		add_filter( $hook_name, '__return_zero' );

		/*
		 * This should succeed, since the 'foo' option has a value of 1 in the database.
		 * Therefore it differs from 0 and should be updated.
		 */
		$this->assertTrue( update_network_option( null, 'foo', 0 ) );
	}

	/**
	 * Tests that calling update_network_option() does not permanently remove pre filters.
	 *
	 * @ticket 59360
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_maintains_pre_filters() {
		$hook_name = is_multisite() ? 'pre_site_option_foo' : 'pre_option_foo';

		add_filter( $hook_name, '__return_zero' );
		update_network_option( null, 'foo', 0 );

		// Assert that the filter is still present.
		$this->assertSame( 10, has_filter( $hook_name, '__return_zero' ) );
	}

	/**
	 * Tests that update_network_option() conditionally applies
	 * 'pre_site_option_{$option}' and 'pre_option_{$option}' filters.
	 *
	 * @ticket 59360
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_should_conditionally_apply_pre_site_option_and_pre_option_filters() {
		$option      = 'foo';
		$site_hook   = new MockAction();
		$option_hook = new MockAction();

		add_filter( "pre_site_option_{$option}", array( $site_hook, 'filter' ) );
		add_filter( "pre_option_{$option}", array( $option_hook, 'filter' ) );

		update_network_option( null, $option, 'false' );

		$this->assertSame( 1, $site_hook->get_call_count(), "'pre_site_option_{$option}' filters occurred an unexpected number of times." );
		$this->assertSame( is_multisite() ? 0 : 1, $option_hook->get_call_count(), "'pre_option_{$option}' filters occurred an unexpected number of times." );
	}

	/**
	 * Tests that update_network_option() conditionally applies
	 * 'default_site_{$option}' and 'default_option_{$option}' filters.
	 *
	 * @ticket 59360
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_should_conditionally_apply_site_and_option_default_value_filters() {
		$option      = 'foo';
		$site_hook   = new MockAction();
		$option_hook = new MockAction();

		add_filter( "default_site_option_{$option}", array( $site_hook, 'filter' ) );
		add_filter( "default_option_{$option}", array( $option_hook, 'filter' ) );

		update_network_option( null, $option, 'false' );

		$this->assertSame( 2, $site_hook->get_call_count(), "'default_site_option_{$option}' filters occurred an unexpected number of times." );
		$this->assertSame( is_multisite() ? 0 : 2, $option_hook->get_call_count(), "'default_option_{$option}' filters occurred an unexpected number of times." );
	}

	/**
	 * Tests that update_network_option() adds a non-existent option that uses a filtered default value.
	 *
	 * @ticket 59360
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_should_add_option_with_filtered_default_value() {
		global $wpdb;

		$option               = 'foo';
		$default_site_value   = 'default-site-value';
		$default_option_value = 'default-option-value';

		add_filter(
			"default_site_option_{$option}",
			static function () use ( $default_site_value ) {
				return $default_site_value;
			}
		);

		add_filter(
			"default_option_{$option}",
			static function () use ( $default_option_value ) {
				return $default_option_value;
			}
		);

		/*
		 * For a non existing option with the unfiltered default of false, passing false here wouldn't work.
		 * Because the default is different than false here though, passing false is expected to result in
		 * a database update.
		 */
		$this->assertTrue( update_network_option( null, $option, false ), 'update_network_option() should have returned true.' );

		if ( is_multisite() ) {
			$actual = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = %s LIMIT 1",
					$option
				)
			);
		} else {
			$actual = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
					$option
				)
			);
		}

		$value_field = is_multisite() ? 'meta_value' : 'option_value';

		$this->assertIsObject( $actual, 'The option was not added to the database.' );
		$this->assertObjectHasProperty( $value_field, $actual, "The '$value_field' property was not included." );
		$this->assertSame( '', $actual->$value_field, 'The new value was not stored in the database.' );
	}
}
