<?php

require_once __DIR__ . '/base.php';

/**
 * Tests wp_enqueue_global_styles().
 *
 * @group themes
 *
 * @covers ::wp_enqueue_global_styles
 */
class Tests_Theme_WpEnqueueGlobalStyles extends WP_Theme_UnitTestCase {

	/**
	 * Original stylesheet.
	 *
	 * @var string
	 */
	private $original_stylesheet;

	public function set_up() {
		parent::set_up();
		$this->original_stylesheet = get_stylesheet();
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	public function tear_down() {
		if ( get_stylesheet() !== $this->original_stylesheet ) {
			switch_theme( $this->original_stylesheet );
		}

		global $wp_customize;
		$wp_customize = null;

		parent::tear_down();
	}

	/**
	 * Tests that Customizer and theme.json custom CSS render after block styles in block themes.
	 *
	 * @ticket 62734
	 */
	public function test_global_styles_custom_css_rendered_after_block_styles() {
		switch_theme( 'block-theme' );
		add_filter( 'should_load_block_assets_on_demand', '__return_false' );

		$customizer_css = '.my-customizer-rule { color: #123456; }';
		wp_update_custom_css_post( $customizer_css );

		add_filter(
			'wp_theme_json_data_theme',
			static function ( $theme_json ) {
				$theme_json->update_with(
					array(
						'version' => WP_Theme_JSON::LATEST_SCHEMA,
						'styles'  => array(
							'css'    => 'p.global-custom-css { color: #654321; }',
							'blocks' => array(
								'core/paragraph' => array(
									'color' => array(
										'text' => '#112233',
									),
								),
							),
						),
					)
				);
				return $theme_json;
			}
		);
		wp_clean_theme_json_cache();

		wp_enqueue_global_styles();

		$this->assertTrue( wp_style_is( 'global-styles', 'enqueued' ), 'global-styles should be enqueued.' );

		$after_styles = wp_styles()->get_data( 'global-styles', 'after' );
		$this->assertIsArray( $after_styles, 'Inline styles array should exist for global-styles.' );
		$this->assertNotEmpty( $after_styles, 'Inline styles array should not be empty.' );

		$combined_styles = implode( "\n", $after_styles );

		$block_style_pos    = strpos( $combined_styles, 'color: #112233;' );
		$customizer_pos     = strpos( $combined_styles, $customizer_css );
		$theme_json_css_pos = strpos( $combined_styles, 'p.global-custom-css { color: #654321; }' );

		$this->assertNotFalse( $block_style_pos, 'Block styles should be present in global-styles inline CSS.' );
		$this->assertNotFalse( $customizer_pos, 'Customizer CSS should be present in global-styles inline CSS.' );
		$this->assertNotFalse( $theme_json_css_pos, 'theme.json custom-css should be present in global-styles inline CSS.' );

		$this->assertGreaterThan(
			$block_style_pos,
			$customizer_pos,
			'Customizer custom CSS should render after block styles to ensure proper cascade order.'
		);

		$this->assertGreaterThan(
			$block_style_pos,
			$theme_json_css_pos,
			'theme.json custom CSS should render after block styles to ensure proper cascade order.'
		);

		$this->assertGreaterThan(
			$customizer_pos,
			$theme_json_css_pos,
			'theme.json custom CSS should render after Customizer custom CSS.'
		);
	}

	/**
	 * Tests that Customizer preview wraps custom CSS with milestone comments after block styles.
	 *
	 * @ticket 62734
	 */
	public function test_global_styles_custom_css_in_customizer_preview() {
		switch_theme( 'block-theme' );
		add_filter( 'should_load_block_assets_on_demand', '__return_false' );

		$customizer_css = '.preview-rule { font-size: 18px; }';
		wp_update_custom_css_post( $customizer_css );

		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		global $wp_customize;
		$wp_customize = new WP_Customize_Manager();
		$wp_customize->start_previewing_theme();

		wp_enqueue_global_styles();

		$this->assertTrue( wp_style_is( 'global-styles', 'enqueued' ) );

		$after_styles    = wp_styles()->get_data( 'global-styles', 'after' );
		$combined_styles = implode( "\n", $after_styles );

		$milestone_start = '/*BEGIN_CUSTOMIZER_CUSTOM_CSS*/';
		$milestone_end   = '/*END_CUSTOMIZER_CUSTOM_CSS*/';

		$start_pos = strpos( $combined_styles, $milestone_start );
		$end_pos   = strpos( $combined_styles, $milestone_end );

		$this->assertNotFalse( $start_pos, 'Milestone start comment should be present in Customizer preview.' );
		$this->assertNotFalse( $end_pos, 'Milestone end comment should be present in Customizer preview.' );
		$this->assertGreaterThan( $start_pos, $end_pos, 'Milestone end comment should appear after start comment.' );

		$block_style_pos = strpos( $combined_styles, '.wp-block-post-featured-image' );
		if ( false !== $block_style_pos ) {
			$this->assertGreaterThan(
				$block_style_pos,
				$start_pos,
				'Customizer preview custom CSS should render after block styles.'
			);
		}
	}

	/**
	 * Tests that classic themes do not merge Customizer custom CSS into global-styles.
	 *
	 * @ticket 62734
	 */
	public function test_classic_theme_does_not_enqueue_customizer_css_in_global_styles() {
		switch_theme( 'default' );

		$customizer_css = '.classic-customizer-rule { margin: 10px; }';
		wp_update_custom_css_post( $customizer_css );

		wp_enqueue_global_styles();

		$after_styles = wp_styles()->get_data( 'global-styles', 'after' );
		if ( ! empty( $after_styles ) ) {
			$combined_styles = implode( "\n", $after_styles );
			$this->assertStringNotContainsString(
				$customizer_css,
				$combined_styles,
				'Classic themes should not merge Customizer CSS into global-styles.'
			);
		} else {
			$this->assertEmpty( $after_styles );
		}
	}
}
