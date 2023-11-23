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
	}

	/**
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
