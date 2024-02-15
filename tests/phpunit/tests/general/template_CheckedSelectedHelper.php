<?php
/**
 * A set of unit tests for the __checked_selected_helper() and associated functions in wp-includes/general-template.php.
 *
 * @group general
 */

class Tests_General_Template_CheckedSelectedHelper extends WP_UnitTestCase {

	/**
	 * List of functions using the __checked_selected_helper() function.
	 *
	 * Doesn't list the conditionally available `readonly` function on purpose.
	 *
	 * @var array
	 */
	private $child_functions = array(
		'selected'    => true,
		'checked'     => true,
		'disabled'    => true,
		'wp_readonly' => true,
	);

	/**
	 * Tests that the return value for selected() is as expected with equal values.
	 *
	 * @ticket 53858
	 * @covers ::selected
	 */
	public function test_selected_with_equal_values() {
		$this->assertSame( " selected='selected'", selected( 'foo', 'foo', false ) );
	}

	/**
	 * Tests that the return value for checked() is as expected with equal values.
	 *
	 * @ticket 53858
	 * @covers ::checked
	 */
	public function test_checked_with_equal_values() {
		$this->assertSame( " checked='checked'", checked( 'foo', 'foo', false ) );
	}

	/**
	 * Tests that the return value for disabled() is as expected with equal values.
	 *
	 * @ticket 53858
	 * @covers ::disabled
	 */
	public function test_disabled_with_equal_values() {
		$this->assertSame( " disabled='disabled'", disabled( 'foo', 'foo', false ) );
	}

	/**
	 * Tests that the return value for readonly() is as expected with equal values.
	 *
	 * @ticket 53858
	 * @covers ::readonly
	 */
	public function test_readonly_with_equal_values() {
		if ( ! function_exists( 'readonly' ) ) {
			$this->markTestSkipped( 'readonly() function is not available on PHP 8.1' );
		}

		$this->setExpectedDeprecated( 'readonly' );

		// Call the function via a variable to prevent a parse error for this file on PHP 8.1.
		$fn = 'readonly';
		$this->assertSame( " readonly='readonly'", $fn( 'foo', 'foo', false ) );
	}

	/**
	 * Tests that the return value for wp_readonly() is as expected with equal values.
	 *
	 * @ticket 53858
	 * @covers ::wp_readonly
	 */
	public function test_wp_readonly_with_equal_values() {
		$this->assertSame( " readonly='readonly'", wp_readonly( 'foo', 'foo', false ) );
	}

	/**
	 * @dataProvider data_equal_values
	 *
	 * @ticket 9862
	 * @ticket 51166
	 * @ticket 53858
	 * @covers ::__checked_selected_helper
	 *
	 * @param mixed $helper  One of the values to compare.
	 * @param mixed $current The other value to compare.
	 */
	public function test_checked_selected_helper_with_equal_values( $helper, $current ) {
		$this->assertSame( " test='test'", __checked_selected_helper( $helper, $current, false, 'test' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_equal_values() {
		return array(
			'same value, "foo"; 1: string; 2: string'   => array( 'foo', 'foo' ),
			'same value, 1; 1: string; 2: int'          => array( '1', 1 ),
			'same value, 1; 1: string; 2: float'        => array( '1', 1.0 ),
			'same value, 1; 1: string; 2: bool true'    => array( '1', true ),
			'same value, 1; 1: int; 2: int'             => array( 1, 1 ),
			'same value, 1; 1: int; 2: float'           => array( 1, 1.0 ),
			'same value, 1; 1: int; 2: bool true'       => array( 1, true ),
			'same value, 1; 1: float; 2: bool true'     => array( 1.0, true ),
			'same value, 1; 1: bool true; 2: bool true' => array( true, true ),
			'same value, 1; 1: float 1.0; 2: float calculation 1.0' => array( 1.0, 3 / 3 ),
			'same value, 0; 1: string; 2: int'          => array( '0', 0 ),
			'same value, 0; 1: string; 2: float'        => array( '0', 0.0 ),
			'same value, 0; 1: int; 2: int'             => array( 0, 0 ),
			'same value, 0; 1: int; 2: float'           => array( 0, 0.0 ),
			'same value, empty string; 1: string; 2: string' => array( '', '' ),
			'same value, empty string; 1: empty string; 2: bool false' => array( '', false ),
			'same value, empty string; 1: bool false; 2: bool false' => array( false, false ),
			'same value, empty string; 1: empty string; 2: null' => array( '', null ),
			'same value, empty string; 1: bool false; 2: null' => array( false, null ),
			'same value, null; 1: null; 2: null'        => array( null, null ),
		);
	}

	/**
	 * @dataProvider data_non_equal_values
	 *
	 * @ticket 9862
	 * @ticket 51166
	 * @ticket 53858
	 * @covers ::__checked_selected_helper
	 *
	 * @param mixed $helper  One of the values to compare.
	 * @param mixed $current The other value to compare.
	 */
	public function test_checked_selected_helper_with_non_equal_values( $helper, $current ) {
		$this->assertSame( '', __checked_selected_helper( $helper, $current, false, 'test' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_non_equal_values() {
		return array(
			'1: string foo; 2: string bar' => array( 'foo', 'bar' ),
			'1: string 0; 2: empty string' => array( '0', '' ),
			'1: string 0; 2: null'         => array( '0', null ),
			'1: int 0; 2: empty string'    => array( 0, '' ),
			'1: int 0; 2: bool true'       => array( 0, true ),
			'1: int 0; 2: bool false'      => array( 0, false ),
			'1: int 0; 2: null'            => array( 0, null ),
			'1: float 0; 2: empty string'  => array( 0.0, '' ),
			'1: float 0; 2: bool true'     => array( 0.0, true ),
			'1: float 0; 2: bool false'    => array( 0.0, false ),
			'1: float 0; 2: null'          => array( 0.0, null ),
			'1: null; 2: bool true'        => array( null, true ),
			'1: null 0; 2: string "foo"'   => array( null, 'foo' ),
			'1: int 1; 2: float 1.5'       => array( 1, 1.5 ),
		);
	}

	/**
	 * Tests that the `$echo` parameter is handled correctly and that even when the output is echoed out,
	 * the text is also returned.
	 *
	 * @ticket 53858
	 * @covers ::__checked_selected_helper
	 */
	public function test_checked_selected_helper_echoes_result_by_default() {
		$expected = " disabled='disabled'";
		$this->expectOutputString( $expected );
		$this->assertSame( $expected, disabled( 'foo', 'foo' ) );
	}

	/**
	 * Tests that the function compares against `true` when the second parameter is not passed.
	 *
	 * @dataProvider data_checked_selected_helper_default_value_for_second_parameter
	 *
	 * @ticket 53858
	 * @covers ::__checked_selected_helper
	 * @covers ::selected
	 * @covers ::checked
	 * @covers ::disabled
	 * @covers ::wp_readonly
	 *
	 * @param mixed $input         Input value
	 * @param mixed $expect_output Optional. Whether output is expected. Defaults to false.
	 */
	public function test_checked_selected_helper_default_value_for_second_parameter( $input, $expect_output = false ) {
		$fn       = array_rand( $this->child_functions );
		$expected = '';

		if ( false !== $expect_output ) {
			$expected = " {$fn}='{$fn}'";
			if ( 'wp_readonly' === $fn ) {
				// Account for the function name not matching the expected output string.
				$expected = " readonly='readonly'";
			}

			// Only set output expectation when output is expected, so the test will fail on unexpected output.
			$this->expectOutputString( $expected );
		}

		// Function will always return the value, even when echoing it out.
		$this->assertSame( $expected, $fn( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_checked_selected_helper_default_value_for_second_parameter() {
		return array(
			'truthy; boolean true'          => array(
				'input'         => true,
				'expect_output' => true,
			),
			'truthy; int 1'                 => array(
				'input'         => 1,
				'expect_output' => true,
			),
			'truthy; string 1'              => array(
				'input'         => '1',
				'expect_output' => true,
			),
			'truthy, but not equal to true' => array(
				'input' => 'foo',
			),
			'falsy; null'                   => array(
				'input' => null,
			),
			'falsy; bool false'             => array(
				'input' => false,
			),
			'falsy; int 0'                  => array(
				'input' => 0,
			),
		);
	}
}
