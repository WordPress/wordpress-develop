<?php

/**
 * Tests for the wp_validate_boolean() function.
 *
 * @group functions.php
 * @covers ::wp_validate_boolean
 */
class Tests_Functions_wpValidateBoolean extends WP_UnitTestCase {

	/**
	 * Tests wp_validate_boolean().
	 *
	 * @dataProvider data_wp_validate_boolean
	 *
	 * @ticket 30238
	 * @ticket 39868
	 *
	 * @param mixed $test_value Test value.
	 * @param bool  $expected   Expected return value.
	 */
	public function test_wp_validate_boolean( $test_value, $expected ) {
		$this->assertSame( $expected, wp_validate_boolean( $test_value ) );
	}

	/**
	 * Data provider for test_wp_validate_boolean().
	 *
	 * @return array[] Test parameters {
	 *     @type mixed $test_value Test value.
	 *     @type bool  $expected   Expected return value.
	 * }
	 */
	public function data_wp_validate_boolean() {
		$std = new \stdClass();

		return array(
			array( null, false ),
			array( true, true ),
			array( false, false ),
			array( 'true', true ),
			array( 'false', false ),
			array( 'FalSE', false ), // @ticket 30238
			array( 'FALSE', false ), // @ticket 30238
			array( 'TRUE', true ),
			array( ' FALSE ', true ),
			array( 'yes', true ),
			array( 'no', true ),
			array( 'string', true ),
			array( '', false ),
			array( array(), false ),
			array( 1, true ),
			array( 0, false ),
			array( -1, true ),
			array( 99, true ),
			array( 0.1, true ),
			array( 0.0, false ),
			array( '1', true ),
			array( '0', false ),
			array( $std, true ),
		);
	}
}
