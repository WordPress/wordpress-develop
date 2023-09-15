<?php

/**
 * @group option
 */
class Tests_Check_Size extends WP_UnitTestCase {

	/**
	 * @covers ::check_option_size
	 *
	 * @ticket 42441
	 */
	public function test_small_option() {
		add_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$value = file( DIR_TESTDATA . '/formatting/entities.txt' );
		$test  = check_option_size( 'bar', 'foo' );
		remove_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$this->assertSame( 'default-yes', $test );
	}

	/**
	 * @covers ::check_option_size
	 *
	 * @ticket 42441
	 */
	public function test_large_option() {
		add_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$value = file( DIR_TESTDATA . '/formatting/entities.txt' );
		$test  = check_option_size( $value, 'foo' );
		remove_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$this->assertSame( 'default-no', $test );
	}


	/**
	 * @covers ::check_option_size
	 *
	 * @ticket 42441
	 */
	public function test_large_option_json() {
		add_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$value = file( DIR_TESTDATA . '/themedir1/block-theme/theme.json' );
		$test  = check_option_size( $value, 'foo' );
		remove_filter( 'max_option_size', array( $this, 'filter_max_option_size' ) );
		$this->assertSame( 'default-no', $test );
	}
	public function filter_max_option_size( $current ) {
		return 1000;
	}
}
