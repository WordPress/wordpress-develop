<?php
/**
 * Test for sanitize_html_class function using dataProvider.
 *
 * @group formatting
 *
 * @covers ::sanitize_html_class
 */
class Test_Formatting_SanitizeHtmlClass extends WP_UnitTestCase {

	/**
	 * Data provider for sanitize_html_class.
	 *
	 * @return array[]
	 */
	public function data_should_sanitize_class_names_when_valid() {
		return array(
			'valid-class'            => array( 'valid-class', 'valid-class' ),
			'class_123'              => array( 'class_123', 'class_123' ),
			'class-name'             => array( 'class-name', 'class-name' ),
			'class_一二三'              => array( 'class一二三', 'class一二三' ),
			'class_点'                => array( 'class_点', 'class_点' ),
			'space removed'          => array( 'class 123', 'class123' ),
			'tab removed'            => array( "class\tname", 'classname' ),
			'newline removed'        => array( "class\nname", 'classname' ),
			'special chars removed'  => array( 'class$name', 'class$name' ),
			'%20 removed (space)'    => array( 'class%20name', 'classname' ),
			'%24 removed ($)'        => array( 'class%24name', 'classname' ),
			'multiple invalid chars' => array( 'valid*class&^%$#@!', 'valid*class&^%$#@!' ),
		);
	}

	/**
	 * @dataProvider data_should_sanitize_class_names_when_valid
	 * @ticket 63156
	 */
	public function test_should_sanitize_class_names_when_valid( $classname, $expected, $fallback = null ) {
		if ( is_null( $fallback ) ) {
			$this->assertSame( $expected, sanitize_html_class( $classname ) );
		} else {
			$this->assertSame( $expected, sanitize_html_class( $classname, $fallback ) );
		}
	}

	/**
	 * Data provider for sanitize_html_class_with_fallback.
	 *
	 * @return array[]
	 */
	public function data_should_sanitize_class_with_fallback_when_empty_result() {
		return array(
			'empty string'           => array( '', 'fallback-class', 'fallback-class' ),
			'only spaces'            => array( '  ', 'fallback-class', 'fallback-class' ),
			'percent-encoded spaces' => array( '%20%20', 'fallback-class', 'fallback-class' ),
			'spaces and percent'     => array( '  %20  ', 'fallback-class', 'fallback-class' ),
		);
	}

	/**
	 * @dataProvider data_should_sanitize_class_with_fallback_when_empty_result
	 * @ticket 63156
	 */
	public function test_should_sanitize_class_with_fallback_when_empty_result( $classname, $expected, $fallback ) {
		$this->assertSame( $expected, sanitize_html_class( $classname, $fallback ) );
	}

	/**
	 * Data provider for sanitize_html_class_empty_result.
	 *
	 * @return array[]
	 */
	public function data_should_return_empty_result_when_no_fallback() {
		return array(
			'percent-encoded space' => array( '  %20  ', '' ),
		);
	}

	/**
	 * @dataProvider data_should_return_empty_result_when_no_fallback
	 * @ticket 63156
	 */
	public function test_should_return_empty_result_when_no_fallback( $classname, $expected ) {
		$this->assertSame( $expected, sanitize_html_class( $classname ) );
	}
}
