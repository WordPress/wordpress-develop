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
	private $old_wp_styles;

	public function set_up() {
		parent::set_up();

		$this->old_wp_styles = $GLOBALS['wp_styles'];

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
	 */
	public function test_wp_should_load_separate_core_block_assets_false() {
		register_core_block_style_handles();

		$style_fields = array(
			'style'       => 'style',
			'editorStyle' => 'editor',
		);

		$core_blocks_meta = $this->get_block_data();

		foreach ( $core_blocks_meta as $name => $schema ) {

			if ( ! isset( $schema['style'] ) ) {
				$schema['style'] = "wp-block-$name";
			}
			if ( ! isset( $schema['editorStyle'] ) ) {
				$schema['editorStyle'] = "wp-block-{$name}-editor";
			}
			foreach ( $style_fields as $style_field => $filename ) {
				$style_handle = $schema[ $style_field ];
				if ( is_array( $style_handle ) ) {
					continue;
				}

				$this->assertArrayNotHasKey( $style_handle, $GLOBALS['wp_styles']->registered, 'The key should not exist, as this style should not be registered' );
			}
		}
	}


	/**
	 * @ticket 58528
	 */
	public function test_wp_should_load_separate_core_block_assets_true() {
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );
		register_core_block_style_handles();

		$style_fields = array(
			'style'       => 'style',
			'editorStyle' => 'editor',
		);

		$core_blocks_meta = $this->get_block_data();
		$wp_styles        = $GLOBALS['wp_styles'];
		$includes_url     = includes_url();

		foreach ( $core_blocks_meta as $name => $schema ) {

			if ( ! isset( $schema['style'] ) ) {
				$schema['style'] = "wp-block-$name";
			}
			if ( ! isset( $schema['editorStyle'] ) ) {
				$schema['editorStyle'] = "wp-block-{$name}-editor";
			}
			foreach ( $style_fields as $style_field => $filename ) {
				$style_handle = $schema[ $style_field ];
				if ( is_array( $style_handle ) ) {
					continue;
				}

				$this->assertArrayHasKey( $style_handle, $wp_styles->registered, 'The key should  exist, as this style should be registered' );
				if ( false === $wp_styles->registered[ $style_handle ]->src ) {
					$this->assertEmpty( $wp_styles->registered[ $style_handle ]->extra, 'If source is false, not style path should be set' );
				} else {
					$this->assertStringContainsString( $includes_url, $wp_styles->registered[ $style_handle ]->src, 'Source of style should contain the includes url' );
					$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra, 'The path of the style should exist' );
					$this->assertArrayHasKey( 'path', $wp_styles->registered[ $style_handle ]->extra, 'The path key of the style should exist in extra array' );
					$this->assertNotEmpty( $wp_styles->registered[ $style_handle ]->extra['path'], 'The path key of the style should not be empty' );
				}
			}
		}
	}


	protected function get_block_data() {
		$core_blocks_meta = require ABSPATH . WPINC . '/blocks/blocks-json.php';
		// Remove this blocks for now, as they are registered elsewhere. 
		unset( $core_blocks_meta['archives'] );
		unset( $core_blocks_meta['widget-group'] );
		return $core_blocks_meta;
	}
}
