<?php

/**
 * Tests for wp_kses_check_attr_val().
 *
 * @group kses
 *
 * @covers ::wp_kses_check_attr_val
 */
class Tests_Kses_wpKsesCheckAttrVal extends WP_UnitTestCase {

	/**
	 * Tests each attribute value check.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_attribute_value_checks
	 *
	 * @param string $value      Attribute value.
	 * @param string $vless      Whether the attribute is valueless, 'y' or 'n'.
	 * @param string $checkname  Name of the check.
	 * @param mixed  $checkvalue Constraint the value should pass.
	 * @param bool   $expected   Whether the check should pass.
	 */
	public function test_wp_kses_check_attr_val( $value, $vless, $checkname, $checkvalue, $expected ) {
		$this->assertSame( $expected, wp_kses_check_attr_val( $value, $vless, $checkname, $checkvalue ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_attribute_value_checks() {
		return array(
			// maxlen.
			'maxlen: shorter value'                   => array( 'ab', 'n', 'maxlen', 3, true ),
			'maxlen: value at the limit'              => array( 'abc', 'n', 'maxlen', 3, true ),
			'maxlen: longer value'                    => array( 'abcd', 'n', 'maxlen', 3, false ),
			'maxlen: empty value'                     => array( '', 'n', 'maxlen', 0, true ),
			'maxlen: counts bytes'                    => array( 'é', 'n', 'maxlen', 1, false ),

			// minlen.
			'minlen: longer value'                    => array( 'abcd', 'n', 'minlen', 3, true ),
			'minlen: value at the limit'              => array( 'abc', 'n', 'minlen', 3, true ),
			'minlen: shorter value'                   => array( 'ab', 'n', 'minlen', 3, false ),
			'minlen: empty value'                     => array( '', 'n', 'minlen', 1, false ),

			// maxval.
			'maxval: smaller integer'                 => array( '5', 'n', 'maxval', 10, true ),
			'maxval: integer at the limit'            => array( '10', 'n', 'maxval', 10, true ),
			'maxval: zero'                            => array( '0', 'n', 'maxval', 10, true ),
			'maxval: larger integer'                  => array( '11', 'n', 'maxval', 10, false ),
			'maxval: surrounding whitespace'          => array( ' 5 ', 'n', 'maxval', 10, true ),
			'maxval: too much whitespace'             => array( '       5', 'n', 'maxval', 10, false ),
			'maxval: more than six digits'            => array( '1234567', 'n', 'maxval', 9999999, false ),
			'maxval: negative integer'                => array( '-1', 'n', 'maxval', 10, false ),
			'maxval: decimal'                         => array( '5.5', 'n', 'maxval', 10, false ),
			'maxval: not a number'                    => array( 'abc', 'n', 'maxval', 10, false ),
			'maxval: empty value'                     => array( '', 'n', 'maxval', 10, false ),

			// minval.
			'minval: larger integer'                  => array( '5', 'n', 'minval', 3, true ),
			'minval: integer at the limit'            => array( '3', 'n', 'minval', 3, true ),
			'minval: smaller integer'                 => array( '2', 'n', 'minval', 3, false ),
			'minval: more than six digits'            => array( '1234567', 'n', 'minval', 1, false ),
			'minval: negative integer'                => array( '-5', 'n', 'minval', 0, false ),
			'minval: not a number'                    => array( 'abc', 'n', 'minval', 0, false ),
			'minval: empty value'                     => array( '', 'n', 'minval', 0, false ),

			// valueless.
			'valueless: required and valueless'       => array( '', 'y', 'valueless', 'y', true ),
			'valueless: uppercase constraint'         => array( '', 'y', 'valueless', 'Y', true ),
			'valueless: required but has a value'     => array( 'selected', 'n', 'valueless', 'y', false ),
			'valueless: value required and present'   => array( 'blah', 'n', 'valueless', 'n', true ),
			'valueless: value required but valueless' => array( '', 'y', 'valueless', 'n', false ),

			// values.
			'values: allowed value'                   => array( 'left', 'n', 'values', array( 'left', 'right' ), true ),
			'values: allowed value in uppercase'      => array( 'LEFT', 'n', 'values', array( 'left', 'right' ), true ),
			'values: value not in the list'           => array( 'center', 'n', 'values', array( 'left', 'right' ), false ),
			'values: empty value'                     => array( '', 'n', 'values', array( 'left', 'right' ), false ),
			'values: zero string not in the list'     => array( '0', 'n', 'values', array( 'left', 'right' ), false ),
			'values: compared as strings'             => array( '1e1', 'n', 'values', array( '10' ), false ),

			// value_callback.
			'value_callback: callback accepts'        => array( 'anything', 'n', 'value_callback', '__return_true', true ),
			'value_callback: callback rejects'        => array( 'anything', 'n', 'value_callback', '__return_false', false ),
			'value_callback: falsy return value'      => array( 'anything', 'n', 'value_callback', '__return_empty_string', false ),

			// Check name handling.
			'check name is case-insensitive'          => array( 'abcd', 'n', 'MaxLen', 3, false ),
			'unknown check passes'                    => array( 'abcd', 'n', 'does_not_exist', 3, true ),
		);
	}

	/**
	 * Tests that the value callback receives the attribute value.
	 *
	 * @ticket 65819
	 */
	public function test_value_callback_receives_attribute_value() {
		$callback = new MockAction();

		wp_kses_check_attr_val( 'my value', 'n', 'value_callback', array( $callback, 'filter' ) );

		$this->assertSame( array( array( 'my value' ) ), $callback->get_args() );
	}

	/**
	 * Tests that wp_kses() removes an attribute that fails a value check.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_kses
	 *
	 * @dataProvider data_wp_kses_applies_attribute_value_checks
	 *
	 * @param string $content  Content to filter.
	 * @param string $expected Expected filtered content.
	 */
	public function test_wp_kses_applies_attribute_value_checks( $content, $expected ) {
		$allowed_html = array(
			'a' => array(
				'title' => array( 'maxlen' => 5 ),
				'rel'   => array( 'values' => array( 'nofollow' ) ),
			),
		);

		$this->assertSame( $expected, wp_kses( $content, $allowed_html ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_kses_applies_attribute_value_checks() {
		return array(
			'passing values are kept'    => array( '<a rel="nofollow" title="short">x</a>', '<a rel="nofollow" title="short">x</a>' ),
			'failing values are removed' => array( '<a rel="sponsored" title="too long">x</a>', '<a>x</a>' ),
		);
	}
}
