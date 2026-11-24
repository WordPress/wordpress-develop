<?php
/**
 * Test wp_theme_get_element_class_name().
 *
 * @group themes
 *
 * @covers ::wp_theme_get_element_class_name
 */
class Tests_Theme_WpThemeGetElementClassName extends WP_UnitTestCase {

	/**
	 * Tests that wp_theme_get_element_class_name() returns correct class name for known and unknown elements.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_elements_and_class_names
	 *
	 * @param string $element  Element name.
	 * @param string $expected Expected class name.
	 */
	public function test_wp_theme_get_element_class_name( $element, $expected ) {
		$actual = wp_theme_get_element_class_name( $element );

		$this->assertSame( $expected, $actual, "Class name for element '{$element}' should match expected value." );
	}

	/**
	 * Data provider for test_wp_theme_get_element_class_name.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_elements_and_class_names() {
		return array(
			'button element'         => array( 'button', 'wp-element-button' ),
			'caption element'        => array( 'caption', 'wp-element-caption' ),
			'non-registered element' => array( 'heading', '' ),
			'empty element string'   => array( '', '' ),
			'unknown custom element' => array( 'unknown_element', '' ),
		);
	}
}
