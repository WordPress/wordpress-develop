<?php

require_once __DIR__ . '/base.php';

/**
 * Tests wp_add_global_styles_for_blocks().
 *
 * @group themes
 *
 * @covers ::wp_add_global_styles_for_blocks
 */
class Tests_Theme_WpAddGlobalStylesForBlocks extends WP_Theme_UnitTestCase {

	/**
	 * Test blocks to unregister at cleanup.
	 *
	 * @var array
	 */
	private $test_blocks = array();

	public function tear_down() {
		// Unregister test blocks.
		if ( ! empty( $this->test_blocks ) ) {
			foreach ( $this->test_blocks as $test_block ) {
				unregister_block_type( $test_block );
			}
			$this->test_blocks = array();
		}

		parent::tear_down();
	}

	/**
	 * @ticket 56915
	 */
	public function test_third_party_blocks_inline_styles_not_register_to_global_styles() {
		switch_theme( 'block-theme' );

		wp_register_style( 'global-styles', false, array(), true, true );
		wp_add_global_styles_for_blocks();

		$this->assertNotContains(
			'.wp-block-my-third-party-block{background-color: hotpink;}',
			$this->get_global_styles()
		);
	}

	/**
	 * @ticket 56915
	 */
	public function test_third_party_blocks_inline_styles_get_registered_to_global_styles() {
		$this->set_up_third_party_block();

		wp_register_style( 'global-styles', false, array(), true, true );

		$this->assertNotContains(
			'.wp-block-my-third-party-block{background-color: hotpink;}',
			$this->get_global_styles(),
			'Third party block inline style should not be registered before running wp_add_global_styles_for_blocks()'
		);

		wp_add_global_styles_for_blocks();

		$this->assertContains(
			'.wp-block-my-third-party-block{background-color: hotpink;}',
			$this->get_global_styles(),
			'Third party block inline style should be registered after running wp_add_global_styles_for_blocks()'
		);
	}

	/**
	 * @ticket 56915
	 */
	public function test_third_party_blocks_inline_styles_get_registered_to_global_styles_when_per_block() {
		$this->set_up_third_party_block();
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		wp_register_style( 'global-styles', false, array(), true, true );

		$this->assertNotContains(
			'.wp-block-my-third-party-block{background-color: hotpink;}',
			$this->get_global_styles(),
			'Third party block inline style should not be registered before running wp_add_global_styles_for_blocks()'
		);

		wp_add_global_styles_for_blocks();

		$this->assertContains(
			'.wp-block-my-third-party-block{background-color: hotpink;}',
			$this->get_global_styles(),
			'Third party block inline style should be registered after running wp_add_global_styles_for_blocks()'
		);
	}

	/**
	 * @ticket 56915
	 */
	public function test_third_party_blocks_inline_styles_get_rendered_when_per_block() {
		$this->set_up_third_party_block();
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		wp_register_style( 'global-styles', false, array(), true, true );
		wp_enqueue_style( 'global-styles' );
		wp_add_global_styles_for_blocks();

		$actual = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString(
			'.wp-block-my-third-party-block{background-color: hotpink;}',
			$actual,
			'Third party block inline style should render'
		);
		$this->assertStringNotContainsString(
			'.wp-block-post-featured-image',
			$actual,
			'Core block should not render'
		);
	}

	/**
	 * @ticket 56915
	 */
	public function test_blocks_inline_styles_get_rendered() {
		wp_register_style( 'global-styles', false, array(), true, true );
		wp_enqueue_style( 'global-styles' );
		wp_add_global_styles_for_blocks();

		$actual = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString(
			'.wp-block-my-third-party-block{background-color: hotpink;}',
			$actual,
			'Third party block inline style should render'
		);
		$this->assertStringContainsString(
			'.wp-block-post-featured-image',
			$actual,
			'Core block should render'
		);
	}

	private function set_up_third_party_block() {
		switch_theme( 'block-theme' );

		$name     = 'my/third-party-block';
		$settings = array(
			'icon'            => 'text',
			'category'        => 'common',
			'render_callback' => 'foo',
		);
		register_block_type( $name, $settings );

		$this->test_blocks[] = $name;
	}

	private function get_global_styles() {
		$actual = wp_styles()->get_data( 'global-styles', 'after' );
		return is_array( $actual ) ? $actual : array();
	}
}
