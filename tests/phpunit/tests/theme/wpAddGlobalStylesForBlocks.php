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

	/**
	 * Administrator ID.
	 *
	 * @var int
	 */
	private static $administrator_id;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$administrator_id = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'administrator@example.com',
			)
		);
	}

	public function set_up() {
		parent::set_up();
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

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
	 * @ticket 61165
	 */
	public function test_third_party_blocks_inline_styles_not_register_to_global_styles() {
		switch_theme( 'block-theme' );

		wp_register_style( 'global-styles', false, array(), true, true );
		wp_add_global_styles_for_blocks();

		$this->assertNotContains(
			':root :where(.wp-block-my-third-party-block){background-color: hotpink;}',
			$this->get_global_styles()
		);
	}

	/**
	 * @ticket 56915
	 * @ticket 61165
	 */
	public function test_third_party_blocks_inline_styles_get_registered_to_global_styles() {
		$this->set_up_third_party_block();

		wp_register_style( 'global-styles', false, array(), true, true );

		$this->assertNotContains(
			':root :where(.wp-block-my-third-party-block){background-color: hotpink;}',
			$this->get_global_styles(),
			'Third party block inline style should not be registered before running wp_add_global_styles_for_blocks()'
		);

		wp_add_global_styles_for_blocks();

		$this->assertContains(
			':root :where(.wp-block-my-third-party-block){background-color: hotpink;}',
			$this->get_global_styles(),
			'Third party block inline style should be registered after running wp_add_global_styles_for_blocks()'
		);
	}

	/**
	 * Tests that the block cache is set for global styles.
	 *
	 * @ticket 61679
	 */
	public function test_styles_for_blocks_cache_is_set() {
		$this->set_up_third_party_block();

		wp_register_style( 'global-styles', false, array(), true, true );

		$cache_key                = 'wp_styles_for_blocks';
		$styles_for_blocks_before = get_transient( $cache_key );
		$this->assertFalse( $styles_for_blocks_before, 'No block styles should be cached yet.' );

		wp_add_global_styles_for_blocks();

		$styles_for_blocks_after = get_transient( $cache_key );
		$this->assertNotEmpty( $styles_for_blocks_after, 'No block styles were cached.' );
	}

	/**
	 * Tests that the block cache is skipped when in dev mode for themes.
	 *
	 * @ticket 61679
	 */
	public function test_styles_for_blocks_skips_cache_in_dev_mode() {
		global $_wp_tests_development_mode;

		$orig_dev_mode = $_wp_tests_development_mode;

		// Setting development mode to theme should skip the cache.
		$_wp_tests_development_mode = 'theme';

		wp_register_style( 'global-styles', false, array(), true, true );

		// Initial register of global styles.
		wp_add_global_styles_for_blocks();

		$styles_for_blocks_initial = get_transient( 'wp_styles_for_blocks' );

		// Cleanup.
		$_wp_tests_development_mode = $orig_dev_mode;

		$this->assertFalse( $styles_for_blocks_initial );
	}

	/**
	 * Tests that the block cache is updated if the block meta has changed.
	 *
	 * @ticket 61679
	 */
	public function test_styles_for_blocks_cache_is_skipped() {
		wp_register_style( 'global-styles', false, array(), true, true );

		// Initial register of global styles.
		wp_add_global_styles_for_blocks();

		$styles_for_blocks_initial = get_transient( 'wp_styles_for_blocks' );
		$this->assertNotEmpty( $styles_for_blocks_initial, 'Initial cache was not set.' );

		$this->set_up_third_party_block();

		/*
		 * Call register of global styles again to ensure the cache is updated.
		 * In normal conditions, this function is only called once per request.
		 */
		wp_add_global_styles_for_blocks();

		$styles_for_blocks_updated = get_transient( 'wp_styles_for_blocks' );
		$this->assertNotEmpty( $styles_for_blocks_updated, 'Updated cache was not set.' );

		$this->assertNotSame(
			$styles_for_blocks_initial,
			$styles_for_blocks_updated,
			'Block style cache was not updated.'
		);
	}

	/**
	 * Confirms that `wp_styles_for_blocks` cache is cleared when a user modifies global styles.
	 * @ticket 61679
	 */
	public function test_styles_for_blocks_cache_is_reset_when_user_styles_change() {
		// Only administrators can update the global styles post.
		wp_set_current_user( self::$administrator_id );

		$this->set_up_third_party_block();

		wp_register_style( 'global-styles', false, array(), true, true );
		wp_add_global_styles_for_blocks();

		$cache_key                = 'wp_styles_for_blocks';
		$styles_for_blocks_before = get_transient( $cache_key );

		// Update the global styles post.
		$post_id     = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
		$before      = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles( wp_get_theme() );
		$old_content = json_decode( $before['post_content'], true );

		// Mock a change in the global styles.
		$new_content = array_merge(
			$old_content,
			array(
				'styles' => array(
					'elements' => array(
						'button' => array(
							'color' => array(
								'background' => 'orange',
							),
						),
					),
				),
			)
		);

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_json_encode( $new_content ),
			)
		);

		// Reset the static cache, since this would be reset between requests.
		WP_Theme_JSON_Resolver::clean_cached_data();

		/*
		 * Call register of global styles again to ensure the cache is updated.
		 * In normal conditions, this function is only called once per request.
		 */
		wp_add_global_styles_for_blocks();

		$cache_key               = 'wp_styles_for_blocks';
		$styles_for_blocks_after = get_transient( $cache_key );

		$this->assertNotSame(
			$styles_for_blocks_before,
			$styles_for_blocks_after,
			'Block style cache was not updated.'
		);
	}

	/**
	 * @ticket 56915
	 * @ticket 61165
	 */
	public function test_third_party_blocks_inline_styles_get_registered_to_global_styles_when_per_block() {
		$this->set_up_third_party_block();
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		wp_register_style( 'global-styles', false, array(), true, true );

		$this->assertNotContains(
			':root :where(.wp-block-my-third-party-block){background-color: hotpink;}',
			$this->get_global_styles(),
			'Third party block inline style should not be registered before running wp_add_global_styles_for_blocks()'
		);

		wp_add_global_styles_for_blocks();

		$this->assertContains(
			':root :where(.wp-block-my-third-party-block){background-color: hotpink;}',
			$this->get_global_styles(),
			'Third party block inline style should be registered after running wp_add_global_styles_for_blocks()'
		);
	}

	/**
	 * @ticket 56915
	 * @ticket 61165
	 */
	public function test_third_party_blocks_inline_styles_get_rendered_when_per_block() {
		$this->set_up_third_party_block();
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		wp_register_style( 'global-styles', false, array(), true, true );
		wp_enqueue_style( 'global-styles' );
		wp_add_global_styles_for_blocks();

		$actual = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString(
			':root :where(.wp-block-my-third-party-block){background-color: hotpink;}',
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
	 * @ticket 61165
	 */
	public function test_blocks_inline_styles_get_rendered() {
		$this->set_up_third_party_block();
		wp_register_style( 'global-styles', false, array(), true, true );
		wp_enqueue_style( 'global-styles' );
		wp_add_global_styles_for_blocks();

		$actual = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString(
			':root :where(.wp-block-my-third-party-block){background-color: hotpink;}',
			$actual,
			'Third party block inline style should render'
		);
		$this->assertStringContainsString(
			'.wp-block-post-featured-image',
			$actual,
			'Core block should render'
		);
	}

	/**
	 * @ticket 57868
	 * @ticket 61165
	 */
	public function test_third_party_blocks_inline_styles_for_elements_get_rendered_when_per_block() {
		$this->set_up_third_party_block();
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		wp_register_style( 'global-styles', false, array(), true, true );
		wp_enqueue_style( 'global-styles' );
		wp_add_global_styles_for_blocks();

		$actual = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString(
			':root :where(.wp-block-my-third-party-block cite){color: white;}',
			$actual
		);
	}

	/**
	 * @ticket 57868
	 * @ticket 61165
	 */
	public function test_third_party_blocks_inline_styles_for_elements_get_rendered() {
		$this->set_up_third_party_block();
		wp_register_style( 'global-styles', false, array(), true, true );
		wp_enqueue_style( 'global-styles' );
		wp_add_global_styles_for_blocks();

		$actual = get_echo( 'wp_print_styles' );

		$this->assertStringContainsString(
			':root :where(.wp-block-my-third-party-block cite){color: white;}',
			$actual
		);
	}

	/**
	 * @ticket 57868
	 *
	 * @dataProvider data_wp_get_block_name_from_theme_json_path
	 *
	 * @param array  $path     An array of keys describing the path to a property in theme.json.
	 * @param string $expected The expected block name.
	 */
	public function test_wp_get_block_name_from_theme_json_path( $path, $expected ) {
		$block_name = wp_get_block_name_from_theme_json_path( $path );
		$this->assertSame( $expected, $block_name );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_wp_get_block_name_from_theme_json_path() {
		return array(
			'core block styles'             => array(
				array( 'styles', 'blocks', 'core/navigation' ),
				'core/navigation',
			),
			'core block element styles'     => array(
				array( 'styles', 'blocks', 'core/navigation', 'elements', 'link' ),
				'core/navigation',
			),
			'custom block styles'           => array(
				array( 'styles', 'blocks', 'my/third-party-block' ),
				'my/third-party-block',
			),
			'custom block element styles'   => array(
				array( 'styles', 'blocks', 'my/third-party-block', 'elements', 'cite' ),
				'my/third-party-block',
			),
			'custom block wrong format'     => array(
				array( 'styles', 'my/third-party-block' ),
				'',
			),
			'invalid path but works for BC' => array(
				array( 'something', 'core/image' ),
				'core/image',
			),
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
