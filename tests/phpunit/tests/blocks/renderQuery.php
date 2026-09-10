<?php
/**
 * Tests for the Query block rendering.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 7.2.0
 *
 * @group blocks
 *
 * @covers ::render_block_core_query
 */
class Tests_Blocks_RenderQuery extends WP_UnitTestCase {

	/**
	 * @var WP_Styles|null
	 */
	private $original_wp_styles;

	public function set_up() {
		parent::set_up();

		global $wp_styles;
		$this->original_wp_styles = $wp_styles;
		$wp_styles                = null;
		wp_styles();
	}

	public function tear_down() {
		global $wp_styles;
		$wp_styles = $this->original_wp_styles;

		parent::tear_down();
	}

	/**
	 * Tests that rendering the block enqueues its style handle regardless of
	 * the enhanced pagination setting, so that the `theme.json` styles for the
	 * block are added by `wp_add_global_styles_for_blocks()`.
	 *
	 * @dataProvider data_rendering_query_enqueues_style_handle
	 *
	 * @param string $block_attributes JSON encoded block attributes.
	 */
	public function test_rendering_query_enqueues_style_handle( $block_attributes ) {
		/*
		 * The block ships no front end stylesheet, so its handle is registered
		 * without a source when the block type is registered.
		 */
		wp_register_style( 'wp-block-query', false );

		do_blocks( "<!-- wp:query $block_attributes --><div class=\"wp-block-query\"></div><!-- /wp:query -->" );

		$this->assertTrue( wp_style_is( 'wp-block-query' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_rendering_query_enqueues_style_handle() {
		return array(
			'enhanced pagination disabled' => array( '{"queryId":0,"query":{"inherit":true}}' ),
			'enhanced pagination enabled'  => array( '{"queryId":0,"query":{"inherit":true},"enhancedPagination":true}' ),
		);
	}
}
