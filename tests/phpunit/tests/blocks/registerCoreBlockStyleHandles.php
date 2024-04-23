<?php

/**
 * Tests for block style handles.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 6.3.0
 *
 * @group blocks
 *
 * @covers ::register_core_block_style_handles
 */
class Tests_Blocks_registerCoreBlockStyleHandles extends WP_UnitTestCase {

	/**
	 * @var WP_Styles
	 */
	private $old_wp_styles;

	/**
	 * @var string
	 */
	private $includes_url;

	const STYLE_FIELDS = array(
		'style'       => 'style',
		'editorStyle' => 'editor',
	);

	public function set_up() {
		parent::set_up();

		$this->old_wp_styles = $GLOBALS['wp_styles'];

		$this->includes_url = includes_url();

		remove_action( 'wp_default_styles', 'wp_default_styles' );

		if ( empty( $GLOBALS['wp_styles'] ) ) {
			$GLOBALS['wp_styles'] = null;
		}
	}

	public function tear_down() {
		$GLOBALS['wp_styles'] = $this->old_wp_styles;

		add_action( 'wp_default_styles', 'wp_default_styles' );

		parent::tear_down();
	}

	/**
	 * @ticket 58528
	 *
	 * @dataProvider data_block_data
	 *
	 * @param string $name   The block name.
	 * @param array  $schema The block's schema.
	 */
	public function test_wp_should_load_separate_core_block_assets_false( $name, $schema ) {
		register_core_block_style_handles();

		foreach ( self::STYLE_FIELDS as $style_field => $filename ) {
			$style_handle = $schema[ $style_field ];
			if ( is_array( $style_handle ) ) {
				continue;
			}

			$this->assertArrayNotHasKey( $style_handle, $GLOBALS['wp_styles']->registered, 'The key should not exist, as this style should not be registered' );
		}
	}


	/**
	 * @ticket 58528
	 *
	 * @dataProvider data_block_data
	 *
	 * @param string $name   The block name.
	 * @param array  $schema The block's schema.
	 */
	public function test_wp_should_load_separate_core_block_assets_true( $name, $schema ) {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		foreach ( self::STYLE_FIELDS as $style_field => $filename ) {
			$style_handle = $schema[ $style_field ];
			if ( is_array( $style_handle ) ) {
				continue;
			}

			$this->assertArrayHasKey( $style_handle, $wp_styles->registered, 'The key should exist, as this style should be registered' );
			if ( false === $wp_styles->registered[ $style_handle ]->src ) {
				$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, 'If source is false, style path should not be set' );
			} else {
				$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, 'Source of style should contain the includes url' );
				$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, 'The path of the style should exist' );
				$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, 'The path key of the style should exist in extra array' );
				$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], 'The path key of the style should not be empty' );
			}
		}
	}

	/**
	 * @ticket 58560
	 *
	 * @dataProvider data_block_data
	 *
	 * @param string $name The block name.
	 */
	public function test_wp_should_load_separate_core_block_assets_current_theme_supports( $name ) {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		add_theme_support( 'wp-block-styles' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		$style_handle = "wp-block-{$name}-theme";

		$this->assertArrayHasKey( $style_handle, $wp_styles->registered, 'The key should exist, as this style should be registered' );
		if ( false === $wp_styles->registered[ $style_handle ]->src ) {
			$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, 'If source is false, style path should not be set' );
		} else {
			$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, 'Source of style should contain the includes url' );
			$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, 'The path of the style should exist' );
			$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, 'The path key of the style should exist in extra array' );
			$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], 'The path key of the style should not be empty' );
		}
	}

	/**
	 * @ticket 59715
	 *
	 * @dataProvider data_block_data
	 *
	 * @param string $name The block name.
	 */
	public function test_register_core_block_style_handles_should_load_rtl_stylesheets_for_rtl_text_direction( $name ) {
		global $wp_locale;

		$orig_text_dir             = $wp_locale->text_direction;
		$wp_locale->text_direction = 'rtl';

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		$style_handle = "wp-block-{$name}-theme";

		$wp_locale->text_direction = $orig_text_dir;

		$this->assertArrayHasKey( $style_handle, $wp_styles->registered, 'The key should exist, as this style should be registered' );
		if ( false === $wp_styles->registered[ $style_handle ]->src ) {
			$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, 'If source is false, style path should not be set' );
		} else {
			$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, 'Source of style should contain the includes url' );
			$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, 'The path of the style should exist' );
			$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, 'The path key of the style should exist in extra array' );
			$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], 'The path key of the style should not be empty' );
			$this->assertArrayHasKey( 'rtl', $wp_styles->registered[ $style_handle ]->extra, 'The rtl key of the style should exist in extra array' );
		}
	}

	public function data_block_data() {
		$core_blocks_meta = require ABSPATH . WPINC . '/blocks/blocks-json.php';

		// Remove this blocks for now, as they are registered elsewhere.
		unset( $core_blocks_meta['archives'] );
		unset( $core_blocks_meta['widget-group'] );

		$data = array();
		foreach ( $core_blocks_meta as $name => $schema ) {
			if ( ! isset( $schema['style'] ) ) {
				$schema['style'] = "wp-block-$name";
			}
			if ( ! isset( $schema['editorStyle'] ) ) {
				$schema['editorStyle'] = "wp-block-{$name}-editor";
			}

			$data[ $name ] = array( $name, $schema );
		}

		return $data;
	}
}
