<?php
/**
 * A set of unit tests for the __checked_selected_helper() and associated functions in wp-includes/general-template.php.
 *
 * @group general
 */

class Tests_General_Template_CheckedSelectedHelper extends WP_UnitTestCase {

	/**
	 * @ticket 9862
	 * @ticket 51166
	 * @dataProvider data_selected_and_checked_with_equal_values
	 *
	 * @covers ::selected
	 * @covers ::checked
	 */
	function test_selected_and_checked_with_equal_values( $selected, $current ) {
		$this->assertSame( " selected='selected'", selected( $selected, $current, false ) );
		$this->assertSame( " checked='checked'", checked( $selected, $current, false ) );
	}

	function data_selected_and_checked_with_equal_values() {
		return array(
			'same value, "foo"; 1: string; 2: string'     => array( 'foo', 'foo' ),
			'same value, 1; 1: string; 2: int'            => array( '1', 1 ),
			'same value, 1; 1: string; 2: bool true'      => array( '1', true ),
			'same value, 1; 1: int; 2: int'               => array( 1, 1 ),
			'same value, 1; 1: int; 2: bool true'         => array( 1, true ),
			'same value, 1; 1: bool true; 2: bool true'   => array( true, true ),
			'same value, 0; 1: string; 2: int'            => array( '0', 0 ),
			'same value, 0; 1: int; 2: int'               => array( 0, 0 ),
			'same value, 0; 1: empty string; 2: bool false' => array( '', false ),
			'same value, 0; 1: bool false; 2: bool false' => array( false, false ),
		);
	}

	/**
	 * @ticket 9862
	 * @ticket 51166
	 * @dataProvider data_selected_and_checked_with_non_equal_values
	 *
	 * @covers ::selected
	 * @covers ::checked
	 */
	function test_selected_and_checked_with_non_equal_values( $selected, $current ) {
		$this->assertSame( '', selected( $selected, $current, false ) );
		$this->assertSame( '', checked( $selected, $current, false ) );
	}

	function data_selected_and_checked_with_non_equal_values() {
		return array(
			'1: string 0; 2: empty string' => array( '0', '' ),
			'1: int 0; 2: empty string'    => array( 0, '' ),
			'1: int 0; 2: bool false'      => array( 0, false ),
		);
	}
}
