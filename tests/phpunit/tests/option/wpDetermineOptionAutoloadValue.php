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

	public static function data_values() {
		return array(
			'yes'      => array(
				'autoload' => 'yes',
				'expected' => WP_Option_Autoload::ON,
			),
			'on'       => array(
				'autoload' => WP_Option_Autoload::ON,
				'expected' => WP_Option_Autoload::ON,
			),
			'true'     => array(
				'autoload' => true,
				'expected' => WP_Option_Autoload::ON,
			),
			'no'       => array(
				'autoload' => 'no',
				'expected' => WP_Option_Autoload::OFF,
			),
			'off'      => array(
				'autoload' => WP_Option_Autoload::OFF,
				'expected' => WP_Option_Autoload::OFF,
			),
			'false'    => array(
				'autoload' => false,
				'expected' => WP_Option_Autoload::OFF,
			),
			'invalid'  => array(
				'autoload' => 'foo',
				'expected' => WP_Option_Autoload::AUTO,
			),
			'null'     => array(
				'autoload' => null,
				'expected' => WP_Option_Autoload::AUTO,
			),
			'auto'     => array(
				'autoload' => WP_Option_Autoload::AUTO,
				'expected' => WP_Option_Autoload::AUTO,
			),
			'auto-on'  => array(
				'autoload' => WP_Option_Autoload::AUTO_ON,
				'expected' => WP_Option_Autoload::AUTO,
			),
			'auto-off' => array(
				'autoload' => WP_Option_Autoload::AUTO_OFF,
				'expected' => WP_Option_Autoload::AUTO,
			),
		);
	}

	/**
	 * @ticket 42441
	 */
	public function test_small_option() {
		$test = wp_determine_option_autoload_value( 'foo', 'bar', 'bar', null );
		$this->assertSame( WP_Option_Autoload::AUTO, $test );
	}

	/**
	 * @ticket 42441
	 */
	public function test_large_option() {
		$value            = file( DIR_TESTDATA . '/formatting/entities.txt' );
		$serialized_value = maybe_serialize( $value );
		$test             = wp_determine_option_autoload_value( 'foo', $value, $serialized_value, null );
		$this->assertSame( WP_Option_Autoload::AUTO_OFF, $test );
	}

	/**
	 * @ticket 42441
	 */
	public function test_large_option_json() {
		$value            = file( DIR_TESTDATA . '/themedir1/block-theme/theme.json' );
		$serialized_value = maybe_serialize( $value );
		$test             = wp_determine_option_autoload_value( 'foo', $value, $serialized_value, null );
		$this->assertSame( WP_Option_Autoload::AUTO_OFF, $test );
	}

	public function filter_max_option_size( $current ) {
		return 1000;
	}
}
