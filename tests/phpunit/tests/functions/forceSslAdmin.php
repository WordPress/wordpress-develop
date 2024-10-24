<?php
/**
 * Test cases for the `force_ssl_admin()` function.
 *
 * @package WordPress\UnitTests
 *
 * @since 6.8.0
 *
 * @group functions
 *
 * @covers ::force_ssl_admin
 */
class Tests_Functions_ForceSslAdmin extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Reset the static variable before each test
		force_ssl_admin( false );
	}

	/**
	 * Data provider for testing force_ssl_admin.
	 *
	 * Provides various inputs and expected outcomes for the function.
	 *
	 * @return array[]
	 */
	public function data_should_return_expected_value_when_various_inputs_are_passed() {
		return array(
			'default'                     => array( null, false, false ),
			'first_call_true'             => array( true, false, true ),
			'first_call_false'            => array( false, false, false ),
			'first_call_non_empty_string' => array( 'some string', false, true ),
			'empty_string'                => array( '', false, false ),
			'first_call_integer_1'        => array( 1, false, true ),
			'integer_0'                   => array( 0, false, false ),
		);
	}

	/**
	 * Tests that force_ssl_admin returns expected values based on various inputs.
	 *
	 * @dataProvider data_should_return_expected_value_when_various_inputs_are_passed
	 *
	 * @param mixed $input                   The input value to test.
	 * @param bool $expected_first_call      The expected result for the first call.
	 * @param bool $expected_subsequent_call The expected result for subsequent calls.
	 */
	public function test_should_return_expected_value_when_various_inputs_are_passed( $input, $expected_first_call, $expected_subsequent_call ) {
		$this->assertSame( $expected_first_call, force_ssl_admin( $input ), 'First call did not return expected value' );

		// Call again to check subsequent behavior
		$this->assertSame( $expected_subsequent_call, force_ssl_admin( $input ), 'Subsequent call did not return expected value' );
	}
}
