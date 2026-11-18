<?php

/**
 * Tests for the get_background_color() and background_color() functions.
 *
 * @group themes
 * @group theme-mods
 *
 * @covers ::get_background_color
 * @covers ::background_color
 */
class Tests_Theme_GetBackgroundColor extends WP_UnitTestCase {

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
	 * Tests that the saved background color is returned.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_the_saved_background_color() {
		$this->set_theme_support( 'custom-background', array( 'default-color' => 'abc123' ) );
		set_theme_mod( 'background_color', 'ff0000' );

		$this->assertSame( 'ff0000', get_background_color() );
	}

	/**
	 * Tests that the default color of the theme support is returned when no color is saved.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_the_default_color_of_the_theme_support() {
		$this->set_theme_support( 'custom-background', array( 'default-color' => 'abc123' ) );

		$this->assertSame( 'abc123', get_background_color() );
	}

	/**
	 * Tests that the default color is used again after the saved color is removed.
	 *
	 * @ticket 66503
	 */
	public function test_should_fall_back_to_the_default_color_after_the_color_is_removed() {
		$this->set_theme_support( 'custom-background', array( 'default-color' => 'abc123' ) );
		set_theme_mod( 'background_color', 'ff0000' );

		$this->assertSame( 'ff0000', get_background_color(), 'The saved color should be used.' );

		remove_theme_mod( 'background_color' );

		$this->assertSame( 'abc123', get_background_color(), 'The default color should be used after the saved color is removed.' );
	}

	/**
	 * Tests that false is returned when the theme has no default color and none is saved.
	 *
	 * @ticket 66503
	 */
	public function test_should_return_false_when_the_theme_has_no_default_color() {
		$this->set_theme_support( 'custom-background' );

		$this->assertFalse( get_background_color() );
	}

	/**
	 * Tests that the color is read through the filter of the modification.
	 *
	 * @ticket 66503
	 */
	public function test_should_apply_the_filter_of_the_modification() {
		$this->set_theme_support( 'custom-background', array( 'default-color' => 'abc123' ) );
		add_filter(
			'theme_mod_background_color',
			static function () {
				return '00ff00';
			}
		);

		$this->assertSame( '00ff00', get_background_color() );
	}

	/**
	 * Tests that the background color is printed.
	 *
	 * @ticket 66503
	 */
	public function test_background_color_should_print_the_color() {
		$this->set_theme_support( 'custom-background', array( 'default-color' => 'abc123' ) );
		set_theme_mod( 'background_color', 'ff0000' );

		$this->expectOutputString( 'ff0000' );

		background_color();
	}

	/**
	 * Tests that the default background color is printed when no color is saved.
	 *
	 * @ticket 66503
	 */
	public function test_background_color_should_print_the_default_color() {
		$this->set_theme_support( 'custom-background', array( 'default-color' => 'abc123' ) );

		$this->expectOutputString( 'abc123' );

		background_color();
	}
}
