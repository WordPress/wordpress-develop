<?php

/**
 * Test the `_get_cron_array()` function.
 *
 * @group cron
 * @covers ::_get_cron_array
 */
class Tests_Cron_getCronArray extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Make sure the schedule is clear.
		_set_cron_array( array() );
	}

	public function tear_down() {
		// Make sure the schedule is clear.
		_set_cron_array( array() );
		parent::tear_down();
	}

	/**
	 * Tests the output validation for the `_get_cron_array()` function when the option is unset.
	 *
	 * @ticket 53940
	 */
	public function test_get_cron_array_output_validation_with_no_option() {
		delete_option( 'cron' );

		$crons = _get_cron_array();
		$this->assertIsArray( $crons, 'Cron jobs is not an array.' );
		$this->assertCount( 0, $crons, 'Cron job does not contain the expected number of entries.' );
	}

	/**
	 * Tests the output validation for the `_get_cron_array()` function.
	 *
	 * @ticket 53940
	 *
	 * @dataProvider data_get_cron_array_output_validation
	 *
	 * @param mixed $input    Cron "array".
	 * @param int   $expected Expected array entry count of the cron option after update.
	 */
	public function test_get_cron_array_output_validation( $input, $expected ) {
		update_option( 'cron', $input );

		$crons = _get_cron_array();
		$this->assertIsArray( $crons, 'Cron jobs is not an array.' );
		$this->assertCount( $expected, $crons, 'Cron job does not contain the expected number of entries.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_get_cron_array_output_validation() {
		return array(
			'stdClass'    => array(
				'input'    => new stdClass(),
				'expected' => 0,
			),
			'null'        => array(
				'input'    => null,
				'expected' => 0,
			),
			'false'       => array(
				'input'    => false,
				'expected' => 0,
			),
			'true'        => array(
				'input'    => true,
				'expected' => 0,
			),
			'integer'     => array(
				'input'    => 53940,
				'expected' => 0,
			),
			'float'       => array(
				'input'    => 539.40,
				'expected' => 0,
			),
			'string'      => array(
				'input'    => 'ticket 53940',
				'expected' => 0,
			),
			'empty array' => array(
				'input'    => array(),
				'expected' => 0,
			),
			'cron array'  => array(
				'input'    => array(
					'version' => 2,
					time()    => array(
						'hookname' => array(
							'event key' => array(
								'schedule' => 'schedule',
								'args'     => 'args',
								'interval' => 'interval',
							),
						),
					),
				),
				'expected' => 1,
			),
			'cron v1'     => array(
				'input'    => array(
					time() => array(
						'hookname' => array(
							'args' => 'args',
						),
					),
				),
				'expected' => 1,
			),
		);
	}
}
