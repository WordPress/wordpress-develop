<?php
/**
 * Tests for enqueuing the stylesheets of registered block styles.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @since 7.2.0
 *
 * @group blocks
 *
 * @covers ::enqueue_block_styles_assets
 * @covers WP_Block::render
 */
class Tests_Blocks_EnqueueBlockStylesAssets extends WP_UnitTestCase {

	/**
	 * Style handle attached to the test block style.
	 *
	 * @var string
	 */
	const STYLE_HANDLE = 'test-block-style-handle';

	/**
	 * Sets up each test method.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_styles;
		$wp_styles = null;

		wp_register_style( self::STYLE_HANDLE, home_url( '/test-block-style.css' ) );
		register_block_style(
			'core/paragraph',
			array(
				'name'         => 'test-style',
				'label'        => 'Test style',
				'style_handle' => self::STYLE_HANDLE,
			)
		);
	}

	/**
	 * Tears down each test method.
	 */
	public function tear_down() {
		unregister_block_style( 'core/paragraph', 'test-style' );

		global $wp_styles;
		$wp_styles = null;

		parent::tear_down();
	}

	/**
	 * Block themes render the template before `enqueue_block_assets` fires,
	 * so the style must be enqueued while the block is rendered.
	 *
	 * @ticket 55184
	 */
	public function test_style_handle_is_enqueued_when_block_is_rendered_before_enqueue_block_assets() {
		add_filter( 'should_load_block_assets_on_demand', '__return_true' );

		do_blocks( '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->' );
		enqueue_block_styles_assets();

		$this->assertTrue( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );
	}

	/**
	 * @ticket 55184
	 */
	public function test_style_handle_is_enqueued_when_block_is_rendered_after_enqueue_block_assets() {
		add_filter( 'should_load_block_assets_on_demand', '__return_true' );

		enqueue_block_styles_assets();
		$this->assertFalse( wp_style_is( self::STYLE_HANDLE, 'enqueued' ), 'Style should not be enqueued before the block is rendered.' );

		do_blocks( '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->' );
		$this->assertTrue( wp_style_is( self::STYLE_HANDLE, 'enqueued' ), 'Style should be enqueued once the block is rendered.' );
	}

	/**
	 * @ticket 55184
	 */
	public function test_style_handle_is_not_enqueued_when_block_is_not_rendered() {
		add_filter( 'should_load_block_assets_on_demand', '__return_true' );

		enqueue_block_styles_assets();
		do_blocks( '<!-- wp:heading --><h2>Hello</h2><!-- /wp:heading -->' );

		$this->assertFalse( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );
	}

	/**
	 * @ticket 55184
	 */
	public function test_style_handle_is_not_enqueued_when_block_renders_empty_content() {
		add_filter( 'should_load_block_assets_on_demand', '__return_true' );
		add_filter( 'render_block_core/paragraph', '__return_empty_string' );

		do_blocks( '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->' );

		$this->assertFalse( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );
	}

	/**
	 * @ticket 55184
	 */
	public function test_style_handle_is_enqueued_when_block_assets_are_not_loaded_on_demand() {
		add_filter( 'should_load_block_assets_on_demand', '__return_false' );

		enqueue_block_styles_assets();

		$this->assertTrue( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) );
	}
}
