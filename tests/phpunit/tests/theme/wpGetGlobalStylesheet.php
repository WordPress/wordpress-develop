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

	public function test_block_theme_using_variables() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertStringContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is 13px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is 36px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );
		$this->assertStringContainsString( '--wp--preset--font-size--custom: 100px;', $styles, 'custom font size is 100px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_block_theme_using_presets() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertStringNotContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--custom: 100px;', $styles, 'custom font size is not present' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_block_theme_using_defaults() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet();
		$this->assertStringContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is 13px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is 36px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );
		$this->assertStringContainsString( '--wp--preset--font-size--custom: 100px;', $styles, 'custom font size is 100px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_variables() {
		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertStringContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is 13px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is 36px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_presets() {
		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertStringNotContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is not present' );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_defaults() {
		$styles = wp_get_global_stylesheet();
		$this->assertStringContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is 13px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is 36px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );
	}

	public function test_variables_in_classic_theme_with_presets_using_variables() {
		add_theme_support(
			'editor-font-sizes',
			array(
				array(
					'name' => 'Small',
					'size' => 18,
					'slug' => 'small',
				),
				array(
					'name' => 'Large',
					'size' => 26.25,
					'slug' => 'large',
				),
			)
		);

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertStringContainsString( '--wp--preset--font-size--small: 18px', $styles, 'small font size is 18px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 26.25px', $styles, 'large font size is 26.25px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );

		remove_theme_support( 'editor-font-sizes' );
	}

	public function test_variables_in_classic_theme_with_presets_using_presets() {
		add_theme_support(
			'editor-font-sizes',
			array(
				array(
					'name' => 'Small',
					'size' => 18,
					'slug' => 'small',
				),
				array(
					'name' => 'Large',
					'size' => 26.25,
					'slug' => 'large',
				),
			)
		);

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertStringNotContainsString( '--wp--preset--font-size--small: 18px', $styles, 'small font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--large: 26.25px', $styles, 'large font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is not present' );

		remove_theme_support( 'editor-font-sizes' );
	}

	public function test_variables_in_classic_theme_with_presets_using_defaults() {
		add_theme_support(
			'editor-font-sizes',
			array(
				array(
					'name' => 'Small',
					'size' => 18,
					'slug' => 'small',
				),
				array(
					'name' => 'Large',
					'size' => 26.25,
					'slug' => 'large',
				),
			)
		);

		$styles = wp_get_global_stylesheet();
		$this->assertStringContainsString( '--wp--preset--font-size--small: 18px', $styles, 'small font size is 18px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 26.25px', $styles, 'large font size is 26.25px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'small font size is 42px' );

		remove_theme_support( 'editor-font-sizes' );
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
