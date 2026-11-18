<?php

/**
 * Tests for the display_header_text() function.
 *
 * @group themes
 * @group theme-mods
 *
 * @covers ::display_header_text
 */
class Tests_Theme_DisplayHeaderText extends WP_UnitTestCase {

	/**
	 * Theme features, to restore after each test.
	 *
	 * @var array
	 */
	private $original_theme_features;

	public function set_up() {
		parent::set_up();

		global $_wp_theme_features;
		$this->original_theme_features = $_wp_theme_features;
	}

	public function tear_down() {
		global $_wp_theme_features;
		$_wp_theme_features = $this->original_theme_features;

		set_current_screen( 'front' );

		parent::tear_down();
	}

	/**
	 * Sets the support of a theme feature without running add_theme_support(), which defines constants that last for the whole run.
	 *
	 * @param string $feature Theme feature.
	 * @param array  $args    Arguments of the feature.
	 */
	private function set_theme_support( $feature, $args = array() ) {
		global $_wp_theme_features;
		$_wp_theme_features[ $feature ] = array( $args );
	}

	/**
	 * Tests that the header text is not displayed when the theme does not support the custom header.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_false_without_the_support_of_the_custom_header() {
		remove_theme_support( 'custom-header' );
		set_theme_mod( 'header_textcolor', 'ff0000' );

		$this->assertFalse( display_header_text() );
	}

	/**
	 * Tests that the header text is not displayed when the theme does not support the header text.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_false_without_the_support_of_the_header_text() {
		$this->set_theme_support( 'custom-header', array( 'header-text' => false ) );
		set_theme_mod( 'header_textcolor', 'ff0000' );

		$this->assertFalse( display_header_text() );
	}

	/**
	 * Tests that the header text is displayed when the theme supports it and the color is saved.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_true_with_a_saved_text_color() {
		$this->set_theme_support( 'custom-header', array( 'header-text' => true ) );
		set_theme_mod( 'header_textcolor', 'ff0000' );

		$this->assertTrue( display_header_text() );
	}

	/**
	 * Tests that the header text is hidden when the saved color is `blank`.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_false_when_the_saved_text_color_is_blank() {
		$this->set_theme_support( 'custom-header', array( 'header-text' => true ) );
		set_theme_mod( 'header_textcolor', 'blank' );

		$this->assertFalse( display_header_text() );
	}

	/**
	 * Tests that the default text color of the theme is used when no color is saved.
	 *
	 * @ticket 66503
	 *
	 * @dataProvider data_default_text_colors
	 *
	 * @param string $default_color Default text color of the theme.
	 * @param bool   $expected      Expected result.
	 */
	public function test_should_use_the_default_text_color_when_no_color_is_saved( $default_color, $expected ) {
		$this->set_theme_support(
			'custom-header',
			array(
				'header-text'        => true,
				'default-text-color' => $default_color,
			)
		);

		$this->assertSame( $expected, display_header_text() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_default_text_colors() {
		return array(
			'a color' => array( 'abc123', true ),
			'blank'   => array( 'blank', false ),
		);
	}

	/**
	 * Tests that the saved color is used instead of the default one.
	 *
	 * @ticket 66503
	 */
	public function test_should_use_the_saved_color_instead_of_the_default_one() {
		$this->set_theme_support(
			'custom-header',
			array(
				'header-text'        => true,
				'default-text-color' => 'blank',
			)
		);
		set_theme_mod( 'header_textcolor', 'ff0000' );

		$this->assertTrue( display_header_text() );
	}
}
