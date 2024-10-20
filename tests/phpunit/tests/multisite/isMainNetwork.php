<?php

if ( is_multisite() ) :

	/**
	 * Tests the is_main_network() function.
	 *
	 * @group ms-site
	 *
	 * @covers ::is_main_network
	 */
	class Tests_Multisite_IsMainNetwork extends WP_UnitTestCase {

		/**
		 * Tests is_main_network() for valid network IDs.
		 *
		 * @dataProvider data_should_return_true_for_valid_network_id
		 *
		 *
		 * @param int|null $network_id The network ID to test.
		 * @param bool     $expected   The expected result.
		 */
		public function test_should_return_true_for_valid_network_id( $network_id ) {
			// Call the function being tested.
			$this->assertTrue( is_main_network( $network_id ) );
		}

		/**
		 * Data provider for valid network IDs.
		 *
		 * @return array[]
		 */
		public function data_should_return_true_for_valid_network_id() {
			return array(
				// Null value.
				'null_value'   => array(
					'network_id' => null,
				),
				// In multisite scenarios with main network.
				'main_network' => array(
					'network_id' => 1,
				),
			);
		}

		/**
		 * Tests is_main_network() for invalid network IDs.
		 *
		 * @dataProvider data_should_return_false_for_invalid_network_id
		 *
		 *
		 * @param int|null $network_id The network ID to test.
		 * @param bool     $expected   The expected result.
		 */
		public function test_should_return_false_for_invalid_network_id( $network_id ) {
			// Call the function being tested.
			$this->assertFalse( is_main_network( $network_id ) );
		}

		/**
		 * Data provider for invalid network IDs.
		 *
		 * @return array[]
		 */
		public function data_should_return_false_for_invalid_network_id() {
			return array(
				// In multisite scenarios with non-main network.
				'non_main_network' => array(
					'network_id' => 2,
				),
				// Handling valid values.
				'zero_value'       => array(
					'network_id' => 0,
				),
				'empty_string'     => array(
					'network_id' => '',
				),
			);
		}
	}

endif;
