<?php

/**
 * Tests for the get_header_textcolor() and header_textcolor() functions.
 *
 * @group themes
 * @group theme-mods
 *
 * @covers ::get_header_textcolor
 * @covers ::header_textcolor
 */
class Tests_Theme_GetHeaderTextcolor extends WP_UnitTestCase {

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
	 * Tests that the saved text color is returned.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_the_saved_text_color() {
		$this->set_theme_support( 'custom-header', array( 'default-text-color' => 'abc123' ) );
		set_theme_mod( 'header_textcolor', 'ff0000' );

		$this->assertSame( 'ff0000', get_header_textcolor() );
	}

	/**
	 * Tests that the default text color of the theme support is returned when no color is saved.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_the_default_text_color_of_the_theme_support() {
		$this->set_theme_support( 'custom-header', array( 'default-text-color' => 'abc123' ) );

		$this->assertSame( 'abc123', get_header_textcolor() );
	}

	/**
	 * Tests that the saved text color is used instead of the default one, and a removed one falls back to it.
	 *
	 * @ticket 66503
	 */
	public function test_should_fall_back_to_the_default_text_color_after_the_color_is_removed() {
		$this->set_theme_support( 'custom-header', array( 'default-text-color' => 'abc123' ) );
		set_theme_mod( 'header_textcolor', 'ff0000' );

		$this->assertSame( 'ff0000', get_header_textcolor(), 'The saved color should be used.' );

		remove_theme_mod( 'header_textcolor' );

		$this->assertSame( 'abc123', get_header_textcolor(), 'The default color should be used after the saved color is removed.' );
	}

	/**
	 * Tests that false is returned when the theme has no default text color and none is saved.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_false_when_the_theme_has_no_default_text_color() {
		$this->set_theme_support( 'custom-header' );

		$this->assertFalse( get_header_textcolor() );
	}

	/**
	 * Tests that the text color is read through the filter of the modification.
	 *
	 * @ticket 66503
	 */
	public function test_should_apply_the_filter_of_the_modification() {
		$this->set_theme_support( 'custom-header', array( 'default-text-color' => 'abc123' ) );
		add_filter(
			'theme_mod_header_textcolor',
			static function () {
				return '00ff00';
			}
		);

		$this->assertSame( '00ff00', get_header_textcolor() );
	}

	/**
	 * Tests that the text color is printed, without being escaped or changed.
	 *
	 * @ticket 66503
	 */
	public function test_header_textcolor_should_print_the_text_color() {
		$this->set_theme_support( 'custom-header', array( 'default-text-color' => 'abc123' ) );
		set_theme_mod( 'header_textcolor', 'ff0000' );

		$this->expectOutputString( 'ff0000' );

		header_textcolor();
	}

	/**
	 * Tests that the default text color is printed when no color is saved.
	 *
	 * @ticket 66503
	 */
	public function test_header_textcolor_should_print_the_default_text_color() {
		$this->set_theme_support( 'custom-header', array( 'default-text-color' => 'abc123' ) );

		$this->expectOutputString( 'abc123' );

		header_textcolor();
	}
}
