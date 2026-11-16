<?php
/**
 * Tests for enqueue_block_styles_assets().
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 7.2.0
 *
 * @group blocks
 *
 * @covers ::enqueue_block_styles_assets
 */
class Tests_Blocks_EnqueueBlockStylesAssets extends WP_UnitTestCase {

	/**
	 * @var WP_Styles|null
	 */
	private $original_wp_styles;

	public function set_up() {
		parent::set_up();

		global $wp_styles;
		$this->original_wp_styles = $wp_styles;
		$wp_styles                = null;

		wp_register_style( 'example-fancy-style', 'https://example.com/fancy.css' );
		register_block_style(
			'core/example',
			array(
				'name'         => 'fancy',
				'label'        => 'Fancy',
				'style_handle' => 'example-fancy-style',
			)
		);
	}

	public function tear_down() {
		unregister_block_style( 'core/example', 'fancy' );

		global $wp_styles;
		$wp_styles = $this->original_wp_styles;

		parent::tear_down();
	}

	/**
	 * Tests that block style handles are enqueued when block assets are not loaded on demand.
	 *
	 * @ticket 55184
	 */
	public function test_enqueues_block_style_handle_when_not_loading_on_demand() {
		add_filter( 'should_load_block_assets_on_demand', '__return_false' );

		enqueue_block_styles_assets();

		$this->assertTrue( wp_style_is( 'example-fancy-style', 'enqueued' ) );
	}

	/**
	 * Tests that block style handles are left to WP_Block::render() when block assets are loaded on demand.
	 *
	 * @ticket 55184
	 */
	public function test_does_not_enqueue_block_style_handle_when_loading_on_demand() {
		add_filter( 'should_load_block_assets_on_demand', '__return_true' );

		enqueue_block_styles_assets();

		$this->assertFalse( wp_style_is( 'example-fancy-style', 'enqueued' ) );
	}
}
