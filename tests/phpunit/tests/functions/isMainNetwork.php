<?php

/**
 * Tests the is_main_network() function.
 *
 * @group functions
 *
 * @covers ::is_main_network
 */
class Tests_Functions_IsMainNetwork extends WP_UnitTestCase {

	/**
	 * Tests is_main_network() for valid network IDs.
	 *
	 * @dataProvider data_should_return_true_for_single_site
	 *
	 *
	 * @param int|null $network_id The network ID to test.
	 * @param bool     $expected   The expected result.
	 */
	public function test_should_return_true_for_single_site( $network_id ) {
		// Call the function being tested.
		$this->assertTrue( is_main_network( $network_id ) );
	}

	/**
	 * Data provider for valid network IDs.
	 *
	 * @return array[]
	 */
	public function data_should_return_true_for_single_site() {
		return array(
			// Not in multisite context.
			'not_in_multisite' => array(
				'network_id' => null,
			),
			// In multisite scenarios with main network.
			'main_network'     => array(
				'network_id' => 1,
			),
			// Handling valid values.
			'zero_value'       => array(
				'network_id' => 0,
			),
			'empty_string'     => array(
				'network_id' => '',
			),
			'non_main_network' => array(
				'network_id' => 2,
			),
		);
	}
}
