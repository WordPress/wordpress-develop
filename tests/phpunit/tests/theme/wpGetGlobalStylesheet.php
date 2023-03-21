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
	 * Flag to indicate whether to remove 'editor-font-sizes' theme support at tear_down().
	 *
	 * @var bool
	 */
	private $remove_theme_support_at_teardown = false;

	/**
	 * Flag to indicate whether to switch back to the default theme at tear down.
	 *
	 * @var bool
	 */
	private $switch_to_default_theme_at_teardown = false;

	public function tear_down() {
		// Reset the theme support.
		if ( $this->remove_theme_support_at_teardown ) {
			$this->remove_theme_support_at_teardown = false;
			remove_theme_support( 'editor-font-sizes' );
		}

		if ( $this->switch_to_default_theme_at_teardown ) {
			$this->switch_to_default_theme_at_teardown = false;
			switch_theme( WP_DEFAULT_THEME );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 54782
	 *
	 * @dataProvider data_should_conditionally_include_font_sizes
	 *
	 * @param array  $expected            Expected CSS for each font size.
	 * @param string $theme               The theme to switch to / use.
	 * @param array  $types               Optional. Types of styles to load. Default empty array.
	 * @param bool   $classic_has_presets Optional. Whether to apply presets for classic theme tests. Default false.
	 */
	public function test_should_conditionally_include_font_sizes( array $expected, $theme, array $types = array(), $classic_has_presets = false ) {
		$this->maybe_switch_theme( $theme );
		$this->add_custom_font_sizes( $classic_has_presets );

		$styles = wp_get_global_stylesheet( $types );

		$this->assertStringContainsString( $expected['small'], $styles, 'The small font size should be included.' );
		$this->assertStringContainsString( $expected['medium'], $styles, 'The medium font size should be included.' );
		$this->assertStringContainsString( $expected['large'], $styles, 'The large font size should be included.' );
		$this->assertStringContainsString( $expected['x-large'], $styles, 'The x-large font size should be included.' );

		if ( 'default' !== $theme ) {
			$this->assertStringContainsString( $expected['custom'], $styles, 'The custom font size should be included.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_conditionally_include_font_sizes() {
		return array(
			'block theme using defaults'                   => array(
				'expected' => array(
					'small'   => '--wp--preset--font-size--small: 13px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 36px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
					'custom'  => '--wp--preset--font-size--custom: 100px;',
				),
				'theme'    => 'block-theme',
			),
			'block theme using variables'                  => array(
				'expected' => array(
					'small'   => '--wp--preset--font-size--small: 13px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 36px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
					'custom'  => '--wp--preset--font-size--custom: 100px;',
				),
				'theme'    => 'block-theme',
				'types'    => array( 'variables' ),
			),
			'classic theme without presets using defaults' => array(
				'expected' => array(
					'small'   => '--wp--preset--font-size--small: 13px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 36px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
				),
				'theme'    => 'default',
			),
			'classic theme without presets using variables' => array(
				'expected' => array(
					'small'   => '--wp--preset--font-size--small: 13px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 36px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
				),
				'theme'    => 'default',
				'types'    => array( 'variables' ),
			),
			'classic theme with presets using defaults'    => array(
				'expected'            => array(
					'small'   => '--wp--preset--font-size--small: 18px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 26.25px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
				),
				'theme'               => 'default',
				'types'               => array(),
				'classic_has_presets' => true,
			),
			'classic theme with presets using variables'   => array(
				'expected'            => array(
					'small'   => '--wp--preset--font-size--small: 18px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 26.25px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
				),
				'theme'               => 'default',
				'types'               => array( 'variables' ),
				'classic_has_presets' => true,
			),
		);
	}

	/**
	 * @ticket 54782
	 *
	 * @dataProvider data_should_not_conditionally_include_font_sizes
	 *
	 * @param array  $expected            Expected CSS for each font size.
	 * @param string $theme               The theme to switch to / use.
	 * @param array  $types               Optional. Types of styles to load. Default empty array.
	 * @param bool   $classic_has_presets Optional. Whether to apply presets for classic theme tests. Default false.
	 */
	public function test_should_not_conditionally_include_font_sizes( array $expected, $theme, array $types = array(), $classic_has_presets = false ) {
		$this->maybe_switch_theme( $theme );
		$this->add_custom_font_sizes( $classic_has_presets );

		$styles = wp_get_global_stylesheet( $types );

		$this->assertStringNotContainsString( $expected['small'], $styles, 'The small font size should not be included.' );
		$this->assertStringNotContainsString( $expected['medium'], $styles, 'The medium font size should not be included.' );
		$this->assertStringNotContainsString( $expected['large'], $styles, 'The large font size should not be included.' );
		$this->assertStringNotContainsString( $expected['x-large'], $styles, 'The x-large font size should not be included.' );

		if ( 'default' !== $theme ) {
			$this->assertStringNotContainsString( $expected['custom'], $styles, 'The custom font size should not be included.' );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_not_conditionally_include_font_sizes() {
		return array(
			'block theme using presets'                   => array(
				'expected' => array(
					'small'   => '--wp--preset--font-size--small: 13px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 36px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
					'custom'  => '--wp--preset--font-size--custom: 100px;',
				),
				'theme'    => 'block-theme',
				'types'    => array( 'presets' ),
			),
			'classic theme without presets using presets' => array(
				'expected' => array(
					'small'   => '--wp--preset--font-size--small: 13px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 36px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
				),
				'theme'    => 'default',
				'types'    => array( 'presets' ),
			),
			'classic theme with presets using presets'    => array(
				'expected'            => array(
					'small'   => '--wp--preset--font-size--small: 18px',
					'medium'  => '--wp--preset--font-size--medium: 20px',
					'large'   => '--wp--preset--font-size--large: 26.25px',
					'x-large' => '--wp--preset--font-size--x-large: 42px',
				),
				'theme'               => 'default',
				'types'               => array( 'presets' ),
				'classic_has_presets' => true,
			),
		);
	}

	/**
	 * @ticket 56970
	 */
	public function test_switching_themes_should_recalculate_stylesheet() {
		$expected = '--wp--preset--font-size--custom: 100px;';

		$stylesheet_for_default_theme = wp_get_global_stylesheet();
		$this->assertStringNotContainsString( $expected, $stylesheet_for_default_theme, 'Custom font size (100px) should not present for default theme' );

		$this->maybe_switch_theme( 'block-theme' );
		$stylesheet_for_block_theme = wp_get_global_stylesheet();
		$this->assertStringContainsString( $expected, $stylesheet_for_block_theme, 'Custom font size (100px) should be present for block theme' );
	}

	/**
	 * Adds the 'editor-font-sizes' theme support with custom font sizes.
	 *
	 * @param bool $add_theme_support Whether to add the theme support.
	 * @param int  $small             Optional. Small font size in pixels. Default 18.
	 * @param int  $large             Optional. Large font size in pixels. Default 26.25.
	 */
	private function add_custom_font_sizes( $add_theme_support, $small = 18, $large = 26.25 ) {
		if ( ! $add_theme_support ) {
			return;
		}

		$args = array(
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
		);
		add_theme_support( 'editor-font-sizes', $args );
		$this->remove_theme_support_at_teardown = true;
	}

	/**
	 * Switches the theme when not the 'default' theme.
	 *
	 * @param string $theme Theme name to switch to.
	 */
	private function maybe_switch_theme( $theme ) {
		if ( 'default' === $theme ) {
			return;
		}

		switch_theme( $theme );
		$this->switch_to_default_theme_at_teardown = true;
	}
}
