<?php

require_once __DIR__ . '/base.php';

/**
 * Tests wp_get_global_stylesheet().
 *
 * @group themes
 *
 * @covers ::wp_get_global_stylesheet
 */
class Tests_Theme_WpGetGlobalStylesheet extends WP_Theme_UnitTestCase {

	/**
	 * Tests that wp_get_global_stylesheet() only includes font sizes
	 * when themes do not use the "presets" types.
	 *
	 * @ticket 54782
	 *
	 * @dataProvider data_wp_get_global_stylesheet_should_conditionally_include_font_sizes
	 *
	 * @param bool   $expected            Whether the font sizes should be included.
	 * @param string $theme               The theme to switch to. Use 'default' for the default theme.
	 * @param array  $types               Types of styles to load.
	 * @param bool   $classic_has_presets Optional. Whether to apply presets for classic theme tests.
	 *                                    Default false.
	 */
	public function test_wp_get_global_stylesheet_should_conditionally_include_font_sizes( $expected, $theme, $types, $classic_has_presets = false ) {
		if ( 'default' !== $theme ) {
			switch_theme( $theme );
		}

		$small = 13;
		$large = 36;

		if ( $classic_has_presets ) {
			$small = 18;
			$large = 26.25;

			$this->add_custom_font_sizes( $small, $large );
		}

		$styles = wp_get_global_stylesheet( $types );

		// Reset theme.
		if ( 'default' !== $theme ) {
			switch_theme( WP_DEFAULT_THEME );
		}

		// Reset theme support.
		if ( $classic_has_presets ) {
			remove_theme_support( 'editor-font-sizes' );
		}

		$expected_small   = '--wp--preset--font-size--small: ' . $small . 'px';
		$expected_medium  = '--wp--preset--font-size--medium: 20px';
		$expected_large   = '--wp--preset--font-size--large: ' . $large . 'px';
		$expected_x_large = '--wp--preset--font-size--x-large: 42px';
		$expected_custom  = '--wp--preset--font-size--custom: 100px;';

		if ( $expected ) {
			$this->assertStringContainsString( $expected_small, $styles, 'The small font size should be included.' );
			$this->assertStringContainsString( $expected_medium, $styles, 'The medium font size should be included.' );
			$this->assertStringContainsString( $expected_large, $styles, 'The large font size should be included.' );
			$this->assertStringContainsString( $expected_x_large, $styles, 'The x-large font size should be included.' );

			if ( 'default' !== $theme ) {
				$this->assertStringContainsString( $expected_custom, $styles, 'The custom font size should be included.' );
			}
		} else {
			$this->assertStringNotContainsString( $expected_small, $styles, 'The small font size should not be included.' );
			$this->assertStringNotContainsString( $expected_medium, $styles, 'The medium font size should not be included.' );
			$this->assertStringNotContainsString( $expected_large, $styles, 'The large font size should not be included.' );
			$this->assertStringNotContainsString( $expected_x_large, $styles, 'The x-large font size should not be included.' );

			if ( 'default' !== $theme ) {
				$this->assertStringNotContainsString( $expected_custom, $styles, 'The custom font size should not be included.' );
			}
		}
	}

	/**
	 * Data provider for test_wp_get_global_stylesheet_should_conditionally_include_font_sizes().
	 *
	 * @return array[]
	 */
	public function data_wp_get_global_stylesheet_should_conditionally_include_font_sizes() {
		return array(
			'true => a block theme and default types'    => array(
				'expected' => true,
				'theme'    => 'block-theme',
				'types'    => array(),
			),
			'true => a block theme and "variable" types' => array(
				'expected' => true,
				'theme'    => 'block-theme',
				'types'    => array( 'variables' ),
			),
			'false => a block theme and "presets" types' => array(
				'expected' => false,
				'theme'    => 'block-theme',
				'types'    => array( 'presets' ),
			),
			'true => a classic theme without presets and default types' => array(
				'expected' => true,
				'theme'    => 'default',
				'types'    => array(),
			),
			'true => a classic theme without presets and "variable" types' => array(
				'expected' => true,
				'theme'    => 'default',
				'types'    => array( 'variables' ),
			),
			'false => a classic theme without presets and "presets" types' => array(
				'expected' => false,
				'theme'    => 'default',
				'types'    => array( 'presets' ),
			),
			'true => a classic theme with presets and default types' => array(
				'expected'            => true,
				'theme'               => 'default',
				'types'               => array(),
				'classic_has_presets' => true,
			),
			'true => a classic theme with presets and "variable" types' => array(
				'expected'            => true,
				'theme'               => 'default',
				'types'               => array( 'variables' ),
				'classic_has_presets' => true,
			),
			'false => a classic theme with presets and "presets" types' => array(
				'expected'            => false,
				'theme'               => 'default',
				'types'               => array( 'presets' ),
				'classic_has_presets' => true,
			),
		);
	}

	/**
	 * Adds theme support and custom font sizes.
	 *
	 * @param int $small The small font size in pixels.
	 * @param int $large The large font size in pixels.
	 */
	private function add_custom_font_sizes( $small, $large ) {
		add_theme_support(
			'editor-font-sizes',
			array(
				array(
					'name' => 'Small',
					'size' => $small,
					'slug' => 'small',
				),
				array(
					'name' => 'Large',
					'size' => $large,
					'slug' => 'large',
				),
			)
		);
	}

	/**
	 * Tests that switching themes recalculates the stylesheet.
	 *
	 * @ticket 56970
	 */
	public function test_switching_themes_should_recalculate_stylesheet() {
		$stylesheet_for_default_theme = wp_get_global_stylesheet();
		switch_theme( 'block-theme' );
		$stylesheet_for_block_theme = wp_get_global_stylesheet();
		switch_theme( WP_DEFAULT_THEME );

		$this->assertStringNotContainsString( '--wp--preset--font-size--custom: 100px;', $stylesheet_for_default_theme, 'custom font size (100px) not present for default theme' );
		$this->assertStringContainsString( '--wp--preset--font-size--custom: 100px;', $stylesheet_for_block_theme, 'custom font size (100px) is present for block theme' );
	}
}
