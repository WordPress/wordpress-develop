<?php

/**
 * Tests for rest_sanitize_boolean() and rest_is_boolean().
 *
 * @group restapi
 */
class Tests_REST_API_RestSanitizeBoolean extends WP_UnitTestCase {

	/**
	 * Tests that boolean-like values are sanitized to bool.
	 *
	 * @ticket 65819
	 *
	 * @covers ::rest_sanitize_boolean
	 *
	 * @dataProvider data_sanitize_boolean
	 *
	 * @param mixed $value    Input value.
	 * @param bool  $expected Sanitized boolean.
	 */
	public function test_rest_sanitize_boolean( $value, $expected ) {
		$this->assertSame( $expected, rest_sanitize_boolean( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_sanitize_boolean() {
		return array(
			'string false' => array( 'false', false ),
			'string zero'  => array( '0', false ),
			'string true'  => array( 'true', true ),
			'string one'   => array( '1', true ),
			'int zero'     => array( 0, false ),
			'int one'      => array( 1, true ),
		);
	}

	/**
	 * Tests recognition of boolean-like values.
	 *
	 * @ticket 65819
	 *
	 * @covers ::rest_is_boolean
	 *
	 * @dataProvider data_is_boolean
	 *
	 * @param mixed $value    Input value.
	 * @param bool  $expected Whether the value is boolean-like.
	 */
	public function test_rest_is_boolean( $value, $expected ) {
		$this->assertSame( $expected, rest_is_boolean( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_is_boolean() {
		return array(
			'bool true'    => array( true, true ),
			'bool false'   => array( false, true ),
			'string true'  => array( 'true', true ),
			'string false' => array( 'false', true ),
			'int zero'     => array( 0, true ),
			'int one'      => array( 1, true ),
			'other string' => array( 'yes', false ),
			'other int'    => array( 2, false ),
		);
	}
}
