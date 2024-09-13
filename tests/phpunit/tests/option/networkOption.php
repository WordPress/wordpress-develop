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
	 * Tests that calling delete_network_option() updates nooptions when option deleted.
	 *
	 * @ticket 61484
	 * @ticket 61730
	 *
	 * @covers ::delete_network_option
	 */
	public function test_check_delete_network_option_updates_notoptions() {
		add_network_option( 1, 'foo', 'value1' );

		delete_network_option( 1, 'foo' );
		$cache_key   = is_multisite() ? '1:notoptions' : 'notoptions';
		$cache_group = is_multisite() ? 'site-options' : 'options';
		$notoptions  = wp_cache_get( $cache_key, $cache_group );
		$this->assertIsArray( $notoptions, 'The notoptions cache is expected to be an array.' );
		$this->assertTrue( $notoptions['foo'], 'The deleted options is expected to be in notoptions.' );

		if ( ! is_multisite() ) {
			$network_notoptions = wp_cache_get( '1:notoptions', 'site-options' );
			$this->assertTrue( empty( $network_notoptions['foo'] ), 'The deleted option is not expected to be in network notoptions on a non-multisite.' );
		}

		$before = get_num_queries();
		get_network_option( 1, 'foo' );
		$queries = get_num_queries() - $before;

		$this->assertSame( 0, $queries, 'get_network_option should not make any database queries.' );
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
	 * Tests that calling update_network_option() clears the notoptions cache.
	 *
	 * @ticket 61484
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_clears_the_notoptions_cache() {
		$option_name = 'ticket_61484_option_to_be_created';
		$cache_key   = is_multisite() ? '1:notoptions' : 'notoptions';
		$cache_group = is_multisite() ? 'site-options' : 'options';
		$notoptions  = wp_cache_get( $cache_key, $cache_group );
		if ( ! is_array( $notoptions ) ) {
			$notoptions = array();
		}
		$notoptions[ $option_name ] = true;
		wp_cache_set( $cache_key, $notoptions, $cache_group );
		$this->assertArrayHasKey( $option_name, wp_cache_get( $cache_key, $cache_group ), 'The "foobar" option should be in the notoptions cache.' );

		update_network_option( 1, $option_name, 'baz' );

		$updated_notoptions = wp_cache_get( $cache_key, $cache_group );
		$this->assertArrayNotHasKey( $option_name, $updated_notoptions, 'The "foobar" option should not be in the notoptions cache after updating it.' );
	}

	/**
	 * Tests that calling add_network_option() clears the notoptions cache.
	 *
	 * @ticket 61484
	 *
	 * @covers ::add_network_option
	 */
	public function test_add_network_option_clears_the_notoptions_cache() {
		$option_name = 'ticket_61484_option_to_be_created';
		$cache_key   = is_multisite() ? '1:notoptions' : 'notoptions';
		$cache_group = is_multisite() ? 'site-options' : 'options';
		$notoptions  = wp_cache_get( $cache_key, $cache_group );
		if ( ! is_array( $notoptions ) ) {
			$notoptions = array();
		}
		$notoptions[ $option_name ] = true;
		wp_cache_set( $cache_key, $notoptions, $cache_group );
		$this->assertArrayHasKey( $option_name, wp_cache_get( $cache_key, $cache_group ), 'The "foobar" option should be in the notoptions cache.' );

		add_network_option( 1, $option_name, 'baz' );

		$updated_notoptions = wp_cache_get( $cache_key, $cache_group );
		$this->assertArrayNotHasKey( $option_name, $updated_notoptions, 'The "foobar" option should not be in the notoptions cache after updating it.' );
	}

	/**
	 * Test adding a previously known notoption returns the correct value.
	 *
	 * @ticket 61730
	 *
	 * @covers ::add_network_option
	 * @covers ::delete_network_option
	 */
	public function test_adding_previous_notoption_returns_correct_value() {
		$option_name = 'ticket_61730_option_to_be_created';

		add_network_option( 1, $option_name, 'baz' );
		delete_network_option( 1, $option_name );

		$this->assertFalse( get_network_option( 1, $option_name ), 'The option should not be found.' );

		add_network_option( 1, $option_name, 'foo' );
		$this->assertSame( 'foo', get_network_option( 1, $option_name ), 'The option should return the newly set value.' );
	}

	/**
	 * Test `get_network_option()` does not use network notoptions cache for single sites.
	 *
	 * @ticket 61730
	 *
	 * @group ms-excluded
	 *
	 * @covers ::get_network_option
	 */
	public function test_get_network_option_does_not_use_network_notoptions_cache_for_single_sites() {
		get_network_option( 1, 'ticket_61730_notoption' );

		$network_notoptions_cache     = wp_cache_get( '1:notoptions', 'site-options' );
		$single_site_notoptions_cache = wp_cache_get( 'notoptions', 'options' );

		$this->assertEmpty( $network_notoptions_cache, 'Network notoptions cache should not be set for single site installs.' );
		$this->assertIsArray( $single_site_notoptions_cache, 'Single site notoptions cache should be set.' );
		$this->assertArrayHasKey( 'ticket_61730_notoption', $single_site_notoptions_cache, 'The option should be in the notoptions cache.' );
	}

	/**
	 * Test `delete_network_option()` does not use network notoptions cache for single sites.
	 *
	 * @ticket 61730
	 * @ticket 61484
	 *
	 * @group ms-excluded
	 *
	 * @covers ::delete_network_option
	 */
	public function test_delete_network_option_does_not_use_network_notoptions_cache_for_single_sites() {
		add_network_option( 1, 'ticket_61730_notoption', 'value' );
		delete_network_option( 1, 'ticket_61730_notoption' );

		$network_notoptions_cache     = wp_cache_get( '1:notoptions', 'site-options' );
		$single_site_notoptions_cache = wp_cache_get( 'notoptions', 'options' );

		$this->assertEmpty( $network_notoptions_cache, 'Network notoptions cache should not be set for single site installs.' );
		$this->assertIsArray( $single_site_notoptions_cache, 'Single site notoptions cache should be set.' );
		$this->assertArrayHasKey( 'ticket_61730_notoption', $single_site_notoptions_cache, 'The option should be in the notoptions cache.' );
	}

	/**
	 * Test `get_network_option()` does not use single site notoptions cache for networks.
	 *
	 * @ticket 61730
	 *
	 * @group ms-required
	 *
	 * @covers ::get_network_option
	 */
	public function test_get_network_option_does_not_use_single_site_notoptions_cache_for_networks() {
		get_network_option( 1, 'ticket_61730_notoption' );

		$network_notoptions_cache     = wp_cache_get( '1:notoptions', 'site-options' );
		$single_site_notoptions_cache = wp_cache_get( 'notoptions', 'options' );

		$this->assertEmpty( $single_site_notoptions_cache, 'Single site notoptions cache should not be set for multisite installs.' );
		$this->assertIsArray( $network_notoptions_cache, 'Multisite notoptions cache should be set.' );
		$this->assertArrayHasKey( 'ticket_61730_notoption', $network_notoptions_cache, 'The option should be in the notoptions cache.' );
	}

	/**
	 * Test `delete_network_option()` does not use single site notoptions cache for networks.
	 *
	 * @ticket 61730
	 * @ticket 61484
	 *
	 * @group ms-required
	 *
	 * @covers ::delete_network_option
	 */
	public function test_delete_network_option_does_not_use_single_site_notoptions_cache_for_networks() {
		add_network_option( 1, 'ticket_61730_notoption', 'value' );
		delete_network_option( 1, 'ticket_61730_notoption' );

		$network_notoptions_cache     = wp_cache_get( '1:notoptions', 'site-options' );
		$single_site_notoptions_cache = wp_cache_get( 'notoptions', 'options' );

		$this->assertEmpty( $single_site_notoptions_cache, 'Single site notoptions cache should not be set for multisite installs.' );
		$this->assertIsArray( $network_notoptions_cache, 'Multisite notoptions cache should be set.' );
		$this->assertArrayHasKey( 'ticket_61730_notoption', $network_notoptions_cache, 'The option should be in the notoptions cache.' );
	}
}
