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
	 * @ticket 37181
	 *
	 * @group ms-required
	 *
	 * @covers ::get_network_option
	 * @covers ::wp_cache_get
	 * @covers ::wp_cache_delete
	 */
	public function test_meta_api_use_values_in_network_option() {
		$network_id = self::factory()->network->create();
		$option     = __FUNCTION__;
		$value      = __FUNCTION__;

		add_metadata( 'site', $network_id, $option, $value, true );
		$this->assertEqualSets( get_metadata( 'site', $network_id, $option ), array( get_network_option( $network_id, $option, true ) ) );
	}

	/**
	 * @ticket 37181
	 *
	 * @group ms-required
	 */
	function test_funky_network_meta() {
		$network_id      = self::factory()->network->create();
		$option          = __FUNCTION__;
		$classy          = new StdClass();
		$classy->ID      = 1;
		$classy->stringy = 'I love slashes\\\\';
		$funky_meta[]    = $classy;

		$classy          = new StdClass();
		$classy->ID      = 2;
		$classy->stringy = 'I love slashes\\\\ more';
		$funky_meta[]    = $classy;

		// Add a network meta item.
		$this->assertIsInt( add_metadata( 'site', $network_id, $option, $funky_meta, true ) );

		// Check they exists.
		$this->assertEquals( $funky_meta, get_network_option( $network_id, $option ) );
	}

	/**
	 * @ticket 37181
	 *
	 * @group ms-required
	 */
	public function test_meta_api_multiple_values_in_network_option() {
		$network_id = self::factory()->network->create();
		$option     = __FUNCTION__;
		add_metadata( 'site', $network_id, $option, 'monday', true );
		add_metadata( 'site', $network_id, $option, 'tuesday', true );
		add_metadata( 'site', $network_id, $option, 'wednesday', true );
		$this->assertEquals( 'monday', get_network_option( $network_id, $option, true ) );
	}

	/**
	 * @ticket 37181
	 *
	 * @group ms-required
	 *
	 * @covers ::get_network_option
	 * @covers ::wp_cache_get
	 */
	public function test_network_option_count_queries_on_non_existing() {
		$network_id = self::factory()->network->create();
		$option     = __FUNCTION__;
		add_network_option( $network_id, $option, 'monday' );
		get_network_option( $network_id, $option );
		$num_queries_pre_get = get_num_queries();
		get_network_option( $network_id, 'do_not_exist' );
		$num_queries_after_get = get_num_queries();

		$this->assertSame( $num_queries_pre_get, $num_queries_after_get );
	}

	/**
	 * @ticket 37181
	 *
	 * @group ms-required
	 */
	public function test_register_meta_network_option_single_false() {
		$network_id = self::factory()->network->create();
		$option     = __FUNCTION__;
		$value      = __FUNCTION__;
		register_meta(
			'site',
			$option,
			array(
				'type'    => 'string',
				'default' => $value,
				'single'  => false,
			)
		);

		$this->assertSame( $value, get_network_option( $network_id, $option ) );
	}

	/**
	 * @ticket 37181
	 *
	 * @group ms-required
	 */
	public function test_register_meta_network_option_single_true() {
		$network_id = self::factory()->network->create();
		$option     = __FUNCTION__;
		$value      = __FUNCTION__;
		register_meta(
			'site',
			$option,
			array(
				'type'    => 'string',
				'default' => $value,
				'single'  => true,
			)
		);

		$this->assertSame( $value, get_network_option( $network_id, $option ) );
	}

	/**
	 * Ensure updating network options containing an object do not result in unneeded database calls.
	 *
	 * @ticket 44956
	 *
	 * @covers ::update_network_option
	 */
	public function test_update_network_option_array_with_object() {
		$network_id     = self::factory()->network->create();
		$option         = __FUNCTION__;
		$array_w_object = array(
			'url'       => 'http://src.wordpress-develop.dev/wp-content/uploads/2016/10/cropped-Blurry-Lights.jpg',
			'meta_data' => (object) array(
				'attachment_id' => 292,
				'height'        => 708,
				'width'         => 1260,
			),
		);

		add_metadata( 'site', $network_id, $option, $array_w_object, true );
		$this->assertSame( $array_w_object, get_network_option( $network_id, $option ) );
	}

	/**
	 * @ticket 37181
	 *
	 * @group ms-required
	 *
	 * @Covers ::add_network_option
	 *
	 * @dataProvider data_types_options
	 */
	public function test_type_add_network_option( $name, $value, $expected ) {
		$result = add_network_option( null, $name, $value );
		$this->assertTrue( $result, 'Network option was not added' );

		$test_value = get_network_option( null, $name );
		$this->assertSame( $expected, $test_value, 'Values do not match' );
	}

	/**
	 * @ticket 37181
	 *
	 * @Covers ::add_network_option
	 *
	 * @dataProvider data_slashed_options
	 */
	public function test_slash_add_network_option( $name, $value ) {
		$result = add_network_option( null, $name, $value );
		$this->assertTrue( $result, 'Network option was not added' );
		$this->assertSame( $value, get_network_option( null, $name ), 'Values do not match' );
	}

	/**
	 * @ticket 37181
	 *
	 * @Covers ::update_network_option
	 *
	 * @dataProvider data_slashed_options
	 */
	public function test_slash_update_network_option( $name, $value ) {
		$result = update_network_option( null, $name, $value );
		$this->assertTrue( $result, 'Network option was not updated' );
		$this->assertSame( $value, get_network_option( null, $name ), 'Values do not match' );
	}

	/**
	 * @dataProvider data_slashed_options
	 * @covers ::delete_network_option()
	 * @ticket 37181
	 */
	public function test_slash_delete_network_option( $name, $value ) {
		$result = add_network_option( null, $name, $value );
		$this->assertTrue( $result, 'Network option was not added' );
		$this->assertSame( $value, get_network_option( null, $name ) );
		$result = delete_network_option( null, $name );
		$this->assertTrue( $result, 'Network option was not deleted' );
		$this->assertFalse( get_network_option( null, $name ), 'Network option was not deleted' );
	}

	public function data_slashed_options() {
		return array(
			'slashed option name'                   => array(
				'option' => 'String with 1 slash \\',
				'value'  => 'foo',
			),
			'slashed in middle option name'         => array(
				'option' => 'String\\thing',
				'value'  => 'foo',
			),
			'slashed option value'                  => array(
				'option' => 'bar',
				'value'  => 'String with 1 slash \\',
			),
			'slashed option name and value'         => array(
				'option' => 'String with 1 slash \\',
				'value'  => 'String with 1 slash \\',
			),
			'slashed 4 times option name and value' => array(
				'option' => 'String with 4 slashes \\\\\\\\',
				'value'  => 'String with 4 slashes \\\\\\\\',
			),
			'slashed 7 times option name and value' => array(
				'option' => 'String with 7 slashes \\\\\\\\\\\\\\',
				'value'  => 'String with 7 slashes \\\\\\\\\\\\\\',
			),
		);
	}

	public function data_types_options() {
		return array(
			'array'       => array(
				'option'   => 'array',
				'value'    => array(),
				'expected' => array(),
			),
			'array_keys'  => array(
				'option'   => 'array',
				'value'    => array( 'key' => 'value' ),
				'expected' => array( 'key' => 'value' ),
			),
			'int'         => array(
				'option'   => 'int',
				'value'    => 33,
				'expected' => '33',
			),
			'string'      => array(
				'option'   => 'string',
				'value'    => 'foo',
				'expected' => 'foo',
			),
			'string_bool' => array(
				'option'   => 'string',
				'value'    => 'true',
				'expected' => 'true',
			),
			'float'       => array(
				'option'   => 'float',
				'value'    => 33.5555,
				'expected' => '33.5555',
			),
			'bool'        => array(
				'option'   => 'bool',
				'value'    => true,
				'expected' => '1',
			),
			'null'        => array(
				'option'   => 'null',
				'value'    => null,
				'expected' => null,
			),
		);
	}
}
