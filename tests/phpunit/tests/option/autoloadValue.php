<?php

/**
 * @group option
 */
class Tests_Option_Autoload_Value extends WP_UnitTestCase {

	/**
	 * @ticket 42441
	 *
	 * @covers ::get_autoload_value
	 *
	 * @dataProvider data_values
	 *
	 * @param $autoload
	 * @param $expected
	 */
	public function test_get_autoload_value_values( $autoload, $expected ) {
		$test = get_autoload_value( $autoload, 'foo', 'bar' );
		$this->assertSame( $expected, $test );
	}
	/**
	 * @covers ::get_autoload_value
	 * @ticket 42441
	 *
	 * @dataProvider data_values
	 *
	 * @param $autoload
	 * @param $expected
	 * @return void
	 */
	public function test_existing_option( $autoload, $expected ) {
		add_option( 'foo', 'bar', '', $autoload );
		$test = get_autoload_value( null, 'foo', 'bar' );
		$this->assertSame( $expected, $test );
	}

	/**
	 * @covers ::get_autoload_value
	 * @ticket 42441
	 *
	 * @dataProvider data_values
	 *
	 * @param $autoload
	 * @param $expected
	 * @return void
	 */
	public function test_existing_option_with_filter( $autoload, $expected ) {
		add_option( 'foo', 'bar', '', $autoload );
		add_filter( 'pre_wp_load_alloptions', '__return_empty_array' );
		$test = get_autoload_value( null, 'foo', 'bar' );
		$this->assertSame( $expected, $test );
	}


	/**
	 * @covers ::get_autoload_value
	 *
	 * @ticket 42441
	 */
	public function test_large_option() {
		add_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$value = file( DIR_TESTDATA . '/formatting/entities.txt' );
		$test  = get_autoload_value( null, 'foo', $value );
		remove_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$this->assertSame( 'no', $test );
	}

	/**
	 * @covers ::get_autoload_value
	 *
	 * @ticket 42441
	 */
	public function test_large_option_json() {
		add_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$value = file( DIR_TESTDATA . '/themedir1/block-theme/theme.json' );
		$test  = get_autoload_value( null, 'foo', $value );
		remove_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$this->assertSame( 'no', $test );
	}

	public function filter_max_option_size( $current ) {
		return 1000;
	}


	public function data_values() {
		return array(
			'yes'     => array(
				'autoload' => 'yes',
				'expected' => 'yes',
			),
			'true'    => array(
				'autoload' => true,
				'expected' => 'yes',
			),
			'no'      => array(
				'autoload' => 'no',
				'expected' => 'no',
			),
			'false'   => array(
				'autoload' => false,
				'expected' => 'no',
			),
			'default' => array(
				'autoload' => 'default',
				'expected' => 'default',
			),
			'null'    => array(
				'autoload' => null,
				'expected' => 'default',
			),
		);
	}
}
