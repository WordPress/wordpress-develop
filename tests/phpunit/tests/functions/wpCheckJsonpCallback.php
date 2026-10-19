<?php

/**
 * Tests for wp_check_jsonp_callback().
 *
 * @group functions
 *
 * @covers ::wp_check_jsonp_callback
 */
class Tests_Functions_WpCheckJsonpCallback extends WP_UnitTestCase {

	/**
	 * Tests valid JSONP callback names.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_callbacks
	 *
	 * @param string $callback Valid callback name.
	 */
	public function test_returns_true_for_valid_callbacks( $callback ) {
		$this->assertTrue( wp_check_jsonp_callback( $callback ) );
	}

	/**
	 * Data provider for valid callbacks.
	 *
	 * @return array[]
	 */
	public static function data_valid_callbacks() {
		return array(
			'empty string' => array( '' ),
		);
	}

	/**
	 * Tests invalid JSONP callback names.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_invalid_callbacks
	 *
	 * @param mixed $callback Invalid callback value.
	 */
	public function test_returns_false_for_invalid_callbacks( $callback ) {
		$this->assertFalse( wp_check_jsonp_callback( $callback ) );
	}

	/**
	 * Data provider for invalid callbacks.
	 *
	 * @return array[]
	 */
	public static function data_invalid_callbacks() {
		return array(
			'parentheses' => array( 'alert(1)' ),
			'array'       => array( array( 'callback' ) ),
			'integer'     => array( 123 ),
			'null'        => array( null ),
			'boolean'     => array( true ),
		);
	}
}
