<?php

/**
 * Tests for the wp_is_serving_rest_request() function.
 *
 * @group functions
 * @group restapi
 *
 * @covers ::wp_is_serving_rest_request
 */
class Tests_Functions_WpIsServingRestRequest extends WP_UnitTestCase {

	/**
	 * Tests that the function returns false when REST_REQUEST is not defined.
	 *
	 * @ticket 65819
	 */
	public function test_returns_false_when_rest_request_constant_is_not_defined() {
		$this->assertFalse(
			defined( 'REST_REQUEST' ),
			'Precondition: REST_REQUEST must not be defined for this assertion.'
		);
		$this->assertFalse( wp_is_serving_rest_request() );
	}

	/**
	 * Tests that the function returns true when REST_REQUEST is true.
	 *
	 * Runs in a separate process because constants cannot be redefined.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_true_when_rest_request_constant_is_true() {
		define( 'REST_REQUEST', true );

		$this->assertTrue( wp_is_serving_rest_request() );
	}

	/**
	 * Tests that the function returns false when REST_REQUEST is false.
	 *
	 * Runs in a separate process because constants cannot be redefined.
	 *
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_returns_false_when_rest_request_constant_is_false() {
		define( 'REST_REQUEST', false );

		$this->assertFalse( wp_is_serving_rest_request() );
	}
}
