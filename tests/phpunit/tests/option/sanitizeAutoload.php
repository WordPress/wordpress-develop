<?php

/**
 * @group option
 */
class Tests_Aanitize_Autoload extends WP_UnitTestCase {

	/**
	 * @ticket 42441
	 *
	 * @covers ::sanitize_autoload
	 *
	 * @dataProvider data_values
	 *
	 * @param $autoload
	 * @param $expected
	 */
	public function test_get_autoload_value_values( $autoload, $expected ) {
		$test = determine_option_autoload_value( null, '', $autoload );
		$this->assertSame( $expected, $test );
	}

	public function data_values() {
		return array(
			'yes'         => array(
				'autoload' => 'yes',
				'expected' => 'yes',
			),
			'true'        => array(
				'autoload' => true,
				'expected' => 'yes',
			),
			'no'          => array(
				'autoload' => 'no',
				'expected' => 'no',
			),
			'false'       => array(
				'autoload' => false,
				'expected' => 'no',
			),
			'default-yes' => array(
				'autoload' => 'default-yes',
				'expected' => 'default-yes',
			),
			'default-no'  => array(
				'autoload' => 'default-no',
				'expected' => 'default-no',
			),
			'null'        => array(
				'autoload' => null,
				'expected' => 'default-yes',
			),
		);
	}
}
