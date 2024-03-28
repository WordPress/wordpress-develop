<?php

/**
 * @group option
 *
 * @covers ::wp_determine_option_autoload_value
 */
class Tests_WP_Determine_Option_Autoload_Value extends WP_UnitTestCase {
	public function set_up() {
		add_filter( 'wp_max_autoloaded_option_size', array( $this, 'filter_max_option_size' ) );
		parent::set_up();
	}

	/**
	 * @ticket 42441
	 *
	 * @dataProvider data_values
	 *
	 * @param $autoload
	 * @param $expected
	 */
	public function test_determine_option_autoload_value( $autoload, $expected ) {
		$test = wp_determine_option_autoload_value( null, '', '', $autoload );
		$this->assertSame( $expected, $test );
	}

	public function data_values() {
		return array(
			'yes'      => array(
				'autoload' => 'yes',
				'expected' => 'on',
			),
			'on'       => array(
				'autoload' => 'on',
				'expected' => 'on',
			),
			'true'     => array(
				'autoload' => true,
				'expected' => 'on',
			),
			'no'       => array(
				'autoload' => 'no',
				'expected' => 'off',
			),
			'off'      => array(
				'autoload' => 'off',
				'expected' => 'off',
			),
			'false'    => array(
				'autoload' => false,
				'expected' => 'off',
			),
			'invalid'  => array(
				'autoload' => 'foo',
				'expected' => 'auto',
			),
			'null'     => array(
				'autoload' => null,
				'expected' => 'auto',
			),
			'auto'     => array(
				'autoload' => 'auto',
				'expected' => 'auto',
			),
			'auto-on'  => array(
				'autoload' => 'auto-on',
				'expected' => 'auto',
			),
			'auto-off' => array(
				'autoload' => 'auto-off',
				'expected' => 'auto',
			),
		);
	}

	/**
	 * @ticket 42441
	 */
	public function test_small_option() {
		$test = wp_determine_option_autoload_value( 'foo', 'bar', 'bar', null );
		$this->assertSame( 'auto', $test );
	}

	/**
	 * @ticket 42441
	 */
	public function test_large_option() {
		$value            = file( DIR_TESTDATA . '/formatting/entities.txt' );
		$serialized_value = maybe_serialize( $value );
		$test             = wp_determine_option_autoload_value( 'foo', $value, $serialized_value, null );
		$this->assertSame( 'auto-off', $test );
	}

	/**
	 * @ticket 42441
	 */
	public function test_large_option_json() {
		$value            = file( DIR_TESTDATA . '/themedir1/block-theme/theme.json' );
		$serialized_value = maybe_serialize( $value );
		$test             = wp_determine_option_autoload_value( 'foo', $value, $serialized_value, null );
		$this->assertSame( 'auto-off', $test );
	}

	public function filter_max_option_size( $current ) {
		return 1000;
	}
}
