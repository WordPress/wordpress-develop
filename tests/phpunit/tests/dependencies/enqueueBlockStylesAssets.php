<?php

/**
 * Test enqueue_block_styles_assets().
 *
 * @group dependencies
 * @group scripts
 * @group blocks
 *
 * @covers ::enqueue_block_styles_assets
 */
class Tests_Dependencies_EnqueueBlockStylesAssets extends WP_UnitTestCase {

	/**
	 * Original $wp_styles global, to restore after each test.
	 *
	 * @var WP_Styles|null
	 */
	private $old_wp_styles;

	public function set_up() {
		parent::set_up();

		$this->old_wp_styles = $GLOBALS['wp_styles'] ?? null;

		$GLOBALS['wp_styles']                  = new WP_Styles();
		$GLOBALS['wp_styles']->default_version = get_bloginfo( 'version' );
	}

	public function tear_down() {
		$GLOBALS['wp_styles'] = $this->old_wp_styles;

		$registry = WP_Block_Styles_Registry::get_instance();
		if ( $registry->is_registered( 'test/block', 'fancy' ) ) {
			$registry->unregister( 'test/block', 'fancy' );
		}

		parent::tear_down();
	}

	/**
	 * Registers a "fancy" style for the "test/block" block type, enqueued via the given stylesheet handle.
	 */
	private function register_test_block_style_handle( $handle ) {
		wp_register_style( $handle, false );
		register_block_style(
			'test/block',
			array(
				'name'         => 'fancy',
				'style_handle' => $handle,
			)
		);
	}

	/**
	 * Registers a "fancy" style for the "test/block" block type, carrying the given inline CSS.
	 */
	private function register_test_block_inline_style( $inline_style ) {
		register_block_style(
			'test/block',
			array(
				'name'         => 'fancy',
				'inline_style' => $inline_style,
			)
		);
	}

	/**
	 * @ticket 54323
	 */
	public function test_style_handle_is_enqueued_immediately_when_not_loading_on_demand() {
		add_filter( 'should_load_block_assets_on_demand', '__return_false' );
		$this->register_test_block_style_handle( 'test-block-style' );

		enqueue_block_styles_assets();

		$this->assertTrue( wp_style_is( 'test-block-style', 'enqueued' ) );
	}

	/**
	 * @ticket 54323
	 */
	public function test_style_handle_enqueue_is_deferred_until_matching_block_is_rendered_when_loading_on_demand() {
		add_filter( 'should_load_block_assets_on_demand', '__return_true' );
		$this->register_test_block_style_handle( 'test-block-style' );

		remove_all_filters( 'render_block' );
		enqueue_block_styles_assets();

		$this->assertFalse( wp_style_is( 'test-block-style', 'enqueued' ), 'Style should not be enqueued before the block renders.' );

		$html = apply_filters( 'render_block', '<p>Test</p>', array( 'blockName' => 'test/block' ) );

		$this->assertSame( '<p>Test</p>', $html, 'The filter must return the block HTML unchanged.' );
		$this->assertTrue( wp_style_is( 'test-block-style', 'enqueued' ), 'Style should be enqueued once the matching block renders.' );
	}

	/**
	 * @ticket 54323
	 */
	public function test_style_handle_is_not_enqueued_for_non_matching_block_when_loading_on_demand() {
		add_filter( 'should_load_block_assets_on_demand', '__return_true' );
		$this->register_test_block_style_handle( 'test-block-style' );

		remove_all_filters( 'render_block' );
		enqueue_block_styles_assets();
		apply_filters( 'render_block', '<p>Test</p>', array( 'blockName' => 'core/paragraph' ) );

		$this->assertFalse( wp_style_is( 'test-block-style', 'enqueued' ) );
	}

	/**
	 * @ticket 54323
	 */
	public function test_inline_style_is_added_to_wp_block_library_handle_by_default() {
		add_filter( 'should_load_block_assets_on_demand', '__return_false' );
		wp_register_style( 'wp-block-library', false );
		$this->register_test_block_inline_style( 'test/block.fancy { color: red; }' );

		enqueue_block_styles_assets();

		$this->assertContains( 'test/block.fancy { color: red; }', $GLOBALS['wp_styles']->get_data( 'wp-block-library', 'after' ) );
	}

	/**
	 * @ticket 54323
	 */
	public function test_inline_style_is_added_to_block_specific_handle_when_loading_on_demand() {
		add_filter( 'should_load_block_assets_on_demand', '__return_true' );
		wp_register_style( 'wp-block-library', false );
		wp_register_style( 'test-block-style', false );
		$this->register_test_block_inline_style( 'test/block.fancy { color: red; }' );

		enqueue_block_styles_assets();

		$this->assertContains( 'test/block.fancy { color: red; }', $GLOBALS['wp_styles']->get_data( 'test-block-style', 'after' ) );
		$this->assertFalse( $GLOBALS['wp_styles']->get_data( 'wp-block-library', 'after' ) );
	}
}
