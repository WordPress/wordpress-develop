<?php

/**
 * Tests for WP_UnitTestCase_Base::assertEqualStyleAttribute().
 *
 * @package WordPress
 *
 * @group testsuite
 */
class Tests_Assert_Equal_Style_Attribute extends WP_UnitTestCase {
	/**
	 * @ticket 65738
	 *
	 * @covers WP_UnitTestCase_Base::assertEqualStyleAttribute
	 *
	 * @dataProvider data_equal_style_attributes
	 *
	 * @param string $expected Expected style attribute value.
	 * @param string $actual   Actual style attribute value.
	 */
	public function test_equal_style_attributes( $expected, $actual ) {
		$this->assertEqualStyleAttribute( $expected, $actual );
	}

	/**
	 * Data provider for test_equal_style_attributes().
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_equal_style_attributes() {
		return array(
			'empty'                     => array( '', ' ; ' ),
			'same bytes'                => array( 'color: red;', 'color: red;' ),
			'whitespace and comments'   => array( 'color:red;margin:1px 2px', "color : red ; margin: 1px/* a */\t2px;" ),
			'whitespace inside a value' => array( 'color: rgb(1,2,3)', 'color: rgb(1, 2, 3)' ),
			'property name case'        => array( 'color: red', 'COLOR: red' ),
			'priority case'             => array( 'color: red !important', 'color: red ! IMPORTANT' ),
			'blocks left open'          => array( 'color: var(--x, red)', 'color: var(--x, red' ),
			'quotes and escapes'        => array( 'font-family: "abc"', "font-family: 'a\\62 c'" ),
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @covers WP_UnitTestCase_Base::assertEqualStyleAttribute
	 *
	 * @dataProvider data_different_style_attributes
	 *
	 * @param string $expected Expected style attribute value.
	 * @param string $actual   Actual style attribute value.
	 */
	public function test_different_style_attributes( $expected, $actual ) {
		$this->expectException( PHPUnit\Framework\AssertionFailedError::class );
		$this->assertEqualStyleAttribute( $expected, $actual );
	}

	/**
	 * Data provider for test_different_style_attributes().
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_different_style_attributes() {
		return array(
			'value'                                 => array( 'color: red', 'color: blue' ),
			'order'                                 => array( 'color: red; margin: 0', 'margin: 0; color: red' ),
			'duplicate'                             => array( 'color: red', 'color: red; color: red' ),
			'priority'                              => array( 'color: red', 'color: red !important' ),
			'custom property name case'             => array( '--x: red', '--X: red' ),
			'tokens that read as one when joined'   => array( 'margin: 1px 2px', 'margin: 1px2px' ),
			'non-declaration text in expected'      => array( 'color: red; }', 'color: red' ),
			'non-declaration text in actual'        => array( 'color: red', 'color: red; }' ),
			'same non-declaration text, both sides' => array( 'color red', 'color red' ),
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @covers WP_UnitTestCase_Base::assertEqualStyleAttribute
	 */
	public function test_failure_message_includes_the_given_message() {
		$this->expectException( PHPUnit\Framework\AssertionFailedError::class );
		$this->expectExceptionMessage( 'Custom message.' );
		$this->assertEqualStyleAttribute( 'color: red', 'color: blue', 'Custom message.' );
	}
}
