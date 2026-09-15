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
	 * @var WP_Styles|null
	 */
	protected $original_wp_styles;

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

		global $wp_styles;
		$this->original_wp_styles = $wp_styles;
		$wp_styles                = null;
		wp_styles();

		$this->includes_url = includes_url();

		remove_action( 'wp_default_styles', 'wp_default_styles' );
	}

	public function tear_down() {
		global $wp_styles;
		$wp_styles = $this->original_wp_styles;

		add_action( 'wp_default_styles', 'wp_default_styles' );

		parent::tear_down();
	}

	/**
	 * @ticket 58528
	 *
	 * @covers ::register_core_block_style_handles
	 * @covers ::wp_should_load_separate_core_block_assets
	 */
	public function test_wp_should_load_separate_core_block_assets_false() {
		add_filter( 'should_load_separate_core_block_assets', '__return_false' );
		register_core_block_style_handles();

		foreach ( $this->get_block_data() as $name => $schema ) {
			$this->assertFalse( wp_should_load_separate_core_block_assets(), "Block {$name}: Core blocks are not expected to load separate assets" );

			foreach ( self::STYLE_FIELDS as $style_field => $filename ) {
				$style_handle = $schema[ $style_field ];
				if ( is_array( $style_handle ) ) {
					continue;
				}

				$this->assertArrayNotHasKey( $style_handle, $GLOBALS['wp_styles']->registered, "Block {$name}: The key should not exist, as this style should not be registered" );
			}
		}
	}


	/**
	 * @ticket 58528
	 *
	 * @covers ::register_core_block_style_handles
	 * @covers ::wp_should_load_separate_core_block_assets
	 */
	public function test_wp_should_load_separate_core_block_assets_true() {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		foreach ( $this->get_block_data() as $name => $schema ) {
			$this->assertTrue( wp_should_load_separate_core_block_assets(), "Block {$name}: Core assets are expected to load separately" );

			foreach ( self::STYLE_FIELDS as $style_field => $filename ) {
				$style_handle = $schema[ $style_field ];
				if ( is_array( $style_handle ) ) {
					continue;
				}

				$this->assertArrayHasKey( $style_handle, $wp_styles->registered, "Block {$name}: The key should exist, as this style should be registered" );
				if ( false === $wp_styles->registered[ $style_handle ]->src ) {
					$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: If source is false, style path should not be set" );
				} else {
					$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, "Block {$name}: Source of style should contain the includes url" );
					$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: The path of the style should exist" );
					$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: The path key of the style should exist in extra array" );
					$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], "Block {$name}: The path key of the style should not be empty" );
				}
			}
		}
	}

	/**
	 * @ticket 58560
	 */
	public function test_wp_should_load_separate_core_block_assets_current_theme_supports() {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		add_theme_support( 'wp-block-styles' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		foreach ( array_keys( $this->get_block_data() ) as $name ) {
			$style_handle = "wp-block-{$name}-theme";

			$this->assertArrayHasKey( $style_handle, $wp_styles->registered, "Block {$name}: The key should exist, as this style should be registered" );
			if ( false === $wp_styles->registered[ $style_handle ]->src ) {
				$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: If source is false, style path should not be set" );
			} else {
				$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, "Block {$name}: Source of style should contain the includes url" );
				$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: The path of the style should exist" );
				$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: The path key of the style should exist in extra array" );
				$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], "Block {$name}: The path key of the style should not be empty" );
			}
		}
	}

	/**
	 * @ticket 59715
	 */
	public function test_register_core_block_style_handles_should_load_rtl_stylesheets_for_rtl_text_direction() {
		global $wp_locale;

		$orig_text_dir             = $wp_locale->text_direction;
		$wp_locale->text_direction = 'rtl';

		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		register_core_block_style_handles();

		$wp_styles = $GLOBALS['wp_styles'];

		$wp_locale->text_direction = $orig_text_dir;

		foreach ( array_keys( $this->get_block_data() ) as $name ) {
			$style_handle = "wp-block-{$name}-theme";

			$this->assertArrayHasKey( $style_handle, $wp_styles->registered, "Block {$name}: The key should exist, as this style should be registered" );
			if ( false === $wp_styles->registered[ $style_handle ]->src ) {
				$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: If source is false, style path should not be set" );
			} else {
				$this->assertStringContainsString( $this->includes_url, $wp_styles->registered[ $style_handle ]->src, "Block {$name}: Source of style should contain the includes url" );
				$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: The path of the style should exist" );
				$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: The path key of the style should exist in extra array" );
				$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], "Block {$name}: The path key of the style should not be empty" );
				$this->assertArrayHasKey( 'rtl', $wp_styles->registered[ $style_handle ]->extra, "Block {$name}: The rtl key of the style should exist in extra array" );
			}
		}
	}

	/**
	 * Gets the blocks to check after registering styles once per scenario.
	 *
	 * @return array[] Block schemas keyed by block name.
	 */
	private function get_block_data() {
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

			$data[ $name ] = $schema;
		}

		return $data;
	}
}
