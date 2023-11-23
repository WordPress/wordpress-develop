<?php

/**
 * @group option
 *
 * @covers ::determine_option_autoload_value
 */
class Tests_Determine_Option_Autoload_Value extends WP_UnitTestCase {
	public function set_up() {
		add_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		parent::set_up();
		add_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
	}

	/**
	 * @ticket 42441
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

	/**
	 *
	 * @ticket 42441
	 */
	public function test_small_option() {
		$test = determine_option_autoload_value( 'foo', 'bar', null );
		$this->assertSame( 'default-yes', $test );
	}

	/**
	 * @ticket 42441
	 */
	public function test_large_option() {
		$value = file( DIR_TESTDATA . '/formatting/entities.txt' );
		$test  = determine_option_autoload_value( 'foo', $value, null );
		$this->assertSame( 'default-no', $test );
	}

	/**
	 * @ticket 42441
	 */
	public function test_large_option_json() {
		$value = file( DIR_TESTDATA . '/themedir1/block-theme/theme.json' );
		$test  = determine_option_autoload_value( 'foo', $value, null );
		$this->assertSame( 'default-no', $test );
	}

	public function filter_max_option_size( $current ) {
		return 1000;
	}
}
