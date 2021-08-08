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
			array( 'foo', 'foo' ),
			array( '1', 1 ),
			array( '1', true ),
			array( 1, 1 ),
			array( 1, true ),
			array( true, true ),
			array( '0', 0 ),
			array( 0, 0 ),
			array( '', false ),
			array( false, false ),
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
			array( '0', '' ),
			array( 0, '' ),
			array( 0, false ),
		);
	}
}
