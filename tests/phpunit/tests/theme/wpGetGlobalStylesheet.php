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
	 * Flag to indicate whether to remove 'border' theme support at tear_down().
	 *
	 * @var bool
	 */
	private $remove_border_support_at_teardown = false;

	/**
	 * Flag to indicate whether to switch back to the default theme at tear down.
	 *
	 * @var bool
	 */
	private $switch_to_default_theme_at_teardown = false;

	public function tear_down() {
		// Reset development mode after each test.
		unset( $GLOBALS['_wp_tests_development_mode'] );

		// Reset the theme support.
		if ( $this->remove_theme_support_at_teardown ) {
			$this->remove_theme_support_at_teardown = false;
			remove_theme_support( 'editor-font-sizes' );
		}

		if ( $this->switch_to_default_theme_at_teardown ) {
			$this->switch_to_default_theme_at_teardown = false;
			switch_theme( WP_DEFAULT_THEME );
		}

		if ( $this->remove_border_support_at_teardown ) {
			$this->remove_border_support_at_teardown = false;
			remove_theme_support( 'border' );
			remove_theme_support( 'editor-color-palette' );
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
	public static function data_should_conditionally_include_font_sizes() {
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
	public static function data_should_not_conditionally_include_font_sizes() {
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
	 * Tests that the function relies on the development mode for whether to use caching.
	 *
	 * @ticket 57487
	 */
	public function test_caching_is_used_when_developing_theme() {
		global $_wp_tests_development_mode;

		$this->maybe_switch_theme( 'block-theme' );

		// Store CSS in cache.
		$css = '.my-class { display: block; }';
		wp_cache_set( 'wp_get_global_stylesheet', $css, 'theme_json' );

		// By default, caching should be used, so the above value will be returned.
		$_wp_tests_development_mode = '';
		$this->assertSame( $css, wp_get_global_stylesheet(), 'Caching was not used despite development mode disabled' );

		// When the development mode is set to 'theme', caching should not be used.
		$_wp_tests_development_mode = 'theme';
		$this->assertNotSame( $css, wp_get_global_stylesheet(), 'Caching was used despite theme development mode' );
	}

	/**
	 * Tests that theme color palette presets are output when appearance tools are enabled via theme support.
	 *
	 * @ticket 60134
	 */
	public function test_theme_color_palette_presets_output_when_border_support_enabled() {

		$args = array(
			array(
				'name'  => 'Black',
				'slug'  => 'nice-black',
				'color' => '#000000',
			),
			array(
				'name'  => 'Dark Gray',
				'slug'  => 'dark-gray',
				'color' => '#28303D',
			),
			array(
				'name'  => 'Green',
				'slug'  => 'haunted-green',
				'color' => '#D1E4DD',
			),
			array(
				'name'  => 'Blue',
				'slug'  => 'soft-blue',
				'color' => '#D1DFE4',
			),
			array(
				'name'  => 'Purple',
				'slug'  => 'cool-purple',
				'color' => '#D1D1E4',
			),
		);

		// Add theme support for appearance tools.
		add_theme_support( 'border' );
		add_theme_support( 'editor-color-palette', $args );
		$this->remove_border_support_at_teardown = true;

		// Check for both the variable declaration and its use as a value.
		$variables = wp_get_global_stylesheet( array( 'variables' ) );

		$this->assertStringContainsString( '--wp--preset--color--nice-black: #000000', $variables );
		$this->assertStringContainsString( '--wp--preset--color--dark-gray: #28303D', $variables );
		$this->assertStringContainsString( '--wp--preset--color--haunted-green: #D1E4DD', $variables );
		$this->assertStringContainsString( '--wp--preset--color--soft-blue: #D1DFE4', $variables );
		$this->assertStringContainsString( '--wp--preset--color--cool-purple: #D1D1E4', $variables );

		$presets = wp_get_global_stylesheet( array( 'presets' ) );

		$this->assertStringContainsString( 'var(--wp--preset--color--nice-black)', $presets );
		$this->assertStringContainsString( 'var(--wp--preset--color--dark-gray)', $presets );
		$this->assertStringContainsString( 'var(--wp--preset--color--haunted-green)', $presets );
		$this->assertStringContainsString( 'var(--wp--preset--color--soft-blue)', $presets );
		$this->assertStringContainsString( 'var(--wp--preset--color--cool-purple)', $presets );
	}

	/**
	 * Tests global custom CSS and the Customizer cascade through both frontend hooks.
	 *
	 * @dataProvider data_theme_types_and_asset_loading
	 * @covers ::wp_enqueue_global_styles
	 *
	 * @param string $theme            Theme stylesheet.
	 * @param bool   $assets_on_demand Whether block assets load on demand.
	 */
	public function test_should_enqueue_theme_json_custom_css( $theme, $assets_on_demand ) {
		$this->maybe_switch_theme( $theme );
		$this->assertSame( 'block-theme' === $theme, wp_is_block_theme(), 'The fixture should match the expected theme type.' );
		$custom_css     = '.theme-json-custom-css{color:red;}';
		$customizer_css = '.customizer-custom-css{color:blue;}';
		$theme_data     = static function ( $theme_json ) use ( $custom_css ) {
			return $theme_json->update_with(
				array(
					'version' => 3,
					'styles'  => array( 'css' => $custom_css ),
				)
			);
		};
		add_filter( 'wp_theme_json_data_theme', $theme_data );
		add_filter( 'should_load_block_assets_on_demand', $assets_on_demand ? '__return_true' : '__return_false' );
		add_filter(
			'wp_get_custom_css',
			static function () use ( $customizer_css ) {
				return $customizer_css;
			}
		);
		add_action( 'wp_head', 'wp_custom_css_cb', 101 );

		ob_start();
		do_action( 'wp_enqueue_scripts' );
		$head_css = implode( '', (array) wp_styles()->get_data( 'global-styles', 'after' ) );
		do_action( 'wp_footer' );
		ob_end_clean();
		$stylesheet = implode( '', (array) wp_styles()->get_data( 'global-styles', 'after' ) );

		if ( 'default' === $theme && $assets_on_demand ) {
			$this->assertStringNotContainsString( $custom_css, $head_css, 'Classic on-demand styles should be deferred to the footer.' );
		} else {
			$this->assertStringContainsString( $custom_css, $head_css, 'Global custom CSS should be enqueued in the head.' );
		}
		$this->assertSame( 1, substr_count( $stylesheet, $custom_css ), 'The global custom CSS should appear exactly once.' );

		if ( 'default' === $theme ) {
			$this->assertSame( 101, has_action( 'wp_head', 'wp_custom_css_cb' ), 'Classic themes should retain the Customizer callback at priority 101.' );
			$this->assertStringNotContainsString( $customizer_css, $stylesheet, 'Classic themes should print Customizer CSS separately.' );
			ob_start();
			wp_custom_css_cb();
			$this->assertStringContainsString( $customizer_css, ob_get_clean() );
		} else {
			$this->assertFalse( has_action( 'wp_head', 'wp_custom_css_cb' ), 'Block themes should merge Customizer CSS into global styles.' );
			$this->assertSame( 1, substr_count( $stylesheet, $customizer_css ) );
			$this->assertLessThan( strpos( $stylesheet, $custom_css ), strpos( $stylesheet, $customizer_css ), 'Customizer CSS should precede global custom CSS.' );
		}
	}

	/**
	 * Provides theme types and asset-loading modes.
	 *
	 * @return array Test cases.
	 */
	public static function data_theme_types_and_asset_loading() {
		return array(
			'classic, all assets' => array( 'default', false ),
			'classic, on demand'  => array( 'default', true ),
			'block, all assets'   => array( 'block-theme', false ),
			'block, on demand'    => array( 'block-theme', true ),
		);
	}

	/**
	 * Tests that block-theme Customizer preview milestones remain intact.
	 *
	 * @dataProvider data_customizer_css
	 * @covers ::wp_enqueue_global_styles
	 *
	 * @param string $customizer_css Customizer CSS, including existing milestones.
	 */
	public function test_should_preserve_customizer_preview_milestones( $customizer_css ) {
		global $wp_customize;
		$original_customize = $wp_customize;
		$this->maybe_switch_theme( 'block-theme' );
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$wp_customize = new WP_Customize_Manager();
		$wp_customize->start_previewing_theme();
		add_filter(
			'wp_get_custom_css',
			static function () use ( $customizer_css ) {
				return $customizer_css;
			}
		);

		try {
			wp_enqueue_global_styles();
			$stylesheet = implode( '', (array) wp_styles()->get_data( 'global-styles', 'after' ) );
			$this->assertSame( 1, substr_count( $stylesheet, '/*BEGIN_CUSTOMIZER_CUSTOM_CSS*/' ) );
			$this->assertSame( 1, substr_count( $stylesheet, '/*END_CUSTOMIZER_CUSTOM_CSS*/' ) );
			$this->assertFalse( has_action( 'wp_head', 'wp_custom_css_cb' ) );
		} finally {
			$wp_customize->stop_previewing_theme();
			$wp_customize = $original_customize;
		}
	}

	/**
	 * Provides empty Customizer CSS and CSS containing preview milestones.
	 *
	 * @return array Test cases.
	 */
	public static function data_customizer_css() {
		return array(
			'empty'               => array( '' ),
			'existing milestones' => array( '/*BEGIN_CUSTOMIZER_CUSTOM_CSS*/.customizer-custom-css{color:blue;}/*END_CUSTOMIZER_CUSTOM_CSS*/' ),
		);
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
