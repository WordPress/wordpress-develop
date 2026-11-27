<?php

/**
 * Tests loading block opinionated (theme) styles on demand in classic themes.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 */
class Tests_Blocks_ClassicThemeBlockStylesOnDemand extends WP_UnitTestCase {

	/**
	 * @var WP_Styles|null
	 */
	protected $original_wp_styles;

	public function set_up() {
		parent::set_up();

		global $wp_styles;
		$this->original_wp_styles = $wp_styles;
	}

	public function tear_down() {
		global $wp_styles;
		$wp_styles = $this->original_wp_styles;

		parent::tear_down();
	}

	/**
	 * @ticket 65272
	 *
	 * @covers ::wp_load_classic_theme_block_styles_on_demand
	 * @covers ::register_core_block_style_handles
	 */
	public function test_register_core_block_style_handles_without_prior_wp_styles() {
		global $wp_styles;
		$wp_styles = null;

		remove_all_filters( 'should_load_separate_core_block_assets' );
		remove_all_filters( 'should_load_block_assets_on_demand' );
		remove_all_filters( 'wp_should_output_buffer_template_for_enhancement' );

		remove_all_actions( 'init' );
		remove_all_actions( 'wp_default_styles' );

		add_action( 'init', 'wp_load_classic_theme_block_styles_on_demand', 8 );
		add_action( 'init', 'register_core_block_style_handles', 9 );
		add_action( 'wp_default_styles', 'wp_load_classic_theme_block_styles_on_demand', 0 );
		add_action( 'wp_default_styles', 'wp_default_styles' );

		add_theme_support( 'wp-block-styles' );

		$this->assertFalse( $wp_styles instanceof WP_Styles, 'Expected WP_Styles to not be constructed yet.' );
		$this->assertFalse( wp_should_load_separate_core_block_assets(), 'Expected separate core block assets to be disabled before init.' );

		do_action( 'init' );

		$this->assertTrue( wp_should_load_separate_core_block_assets(), 'Expected separate core block assets to be enabled after init.' );
		$this->assertTrue( wp_style_is( 'wp-block-quote-theme', 'registered' ), 'Expected the Quote block theme stylesheet to be registered.' );
	}

	/**
	 * @ticket 65272
	 *
	 * @covers ::wp_load_classic_theme_block_styles_on_demand
	 */
	public function test_wp_load_classic_theme_block_styles_on_demand_does_not_duplicate_hooks() {
		switch_theme( 'default' );

		remove_all_filters( 'should_load_separate_core_block_assets' );
		remove_all_filters( 'should_load_block_assets_on_demand' );
		remove_all_filters( 'wp_should_output_buffer_template_for_enhancement' );
		remove_all_actions( 'wp_template_enhancement_output_buffer_started' );

		wp_load_classic_theme_block_styles_on_demand();
		wp_load_classic_theme_block_styles_on_demand();

		global $wp_filter;

		$this->assertSame( 0, has_filter( 'should_load_separate_core_block_assets', '__return_true' ) );
		$this->assertCount( 1, $wp_filter['should_load_separate_core_block_assets']->callbacks[0] );
		$this->assertCount( 1, $wp_filter['should_load_block_assets_on_demand']->callbacks[0] );
		$this->assertCount( 1, $wp_filter['wp_should_output_buffer_template_for_enhancement']->callbacks[0] );
		$this->assertCount( 1, $wp_filter['wp_template_enhancement_output_buffer_started']->callbacks[10] );
	}

	/**
	 * @ticket 64846
	 * @ticket 65272
	 *
	 * @covers ::wp_load_classic_theme_block_styles_on_demand
	 * @covers ::wp_default_styles
	 */
	public function test_wp_block_library_uses_common_css_when_wp_styles_constructed_before_init() {
		global $wp_styles;
		$wp_styles = null;

		remove_all_filters( 'should_load_separate_core_block_assets' );
		remove_all_filters( 'should_load_block_assets_on_demand' );
		remove_all_filters( 'wp_should_output_buffer_template_for_enhancement' );

		remove_all_actions( 'init' );
		remove_all_actions( 'wp_default_styles' );

		add_action( 'init', 'wp_load_classic_theme_block_styles_on_demand', 8 );
		add_action( 'init', 'register_core_block_style_handles', 9 );
		add_action( 'wp_default_styles', 'wp_load_classic_theme_block_styles_on_demand', 0 );
		add_action( 'wp_default_styles', 'wp_default_styles' );

		add_theme_support( 'wp-block-styles' );

		wp_styles();

		$this->assertSame(
			'/' . WPINC . '/css/dist/block-library/common.css',
			wp_styles()->registered['wp-block-library']->src,
			'Expected wp-block-library to use common.css when separate core block assets are enabled.'
		);

		do_action( 'init' );

		$this->assertTrue( wp_should_load_separate_core_block_assets(), 'Expected separate core block assets to remain enabled after init.' );
		$this->assertTrue( wp_style_is( 'wp-block-quote-theme', 'registered' ), 'Expected the Quote block theme stylesheet to be registered after init.' );
	}
}
