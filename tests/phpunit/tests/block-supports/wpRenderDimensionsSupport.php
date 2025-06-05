<?php
/**
 * @group block-supports
 *
 * @covers ::wp_render_dimensions_support
 */
class Tests_Block_Supports_WpRenderDimensionsSupport extends WP_UnitTestCase {
	/**
	 * @var string|null
	 */
	private $test_block_name;

	/**
	 * Theme root directory.
	 *
	 * @var string
	 */
	private $theme_root;

	/**
	 * Original theme directory.
	 *
	 * @var string
	 */
	private $orig_theme_dir;

	public function set_up() {
		parent::set_up();
		$this->test_block_name = null;
		$this->theme_root      = realpath( DIR_TESTDATA . '/themedir1' );
		$this->orig_theme_dir  = $GLOBALS['wp_theme_directories'];

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );

		add_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );

		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		WP_Style_Engine_CSS_Rules_Store::remove_all_stores();
	}

	public function tear_down() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;

		// Clear up the filters to modify the theme root.
		remove_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		remove_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		remove_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );

		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		WP_Style_Engine_CSS_Rules_Store::remove_all_stores();
		unregister_block_type( $this->test_block_name );
		$this->test_block_name = null;
		parent::tear_down();
	}

	public function filter_set_theme_root() {
		return $this->theme_root;
	}

	/**
	 * Tests that dimensions block support works as expected.
	 *
	 * @ticket 60365
	 *
	 * @covers ::wp_render_dimensions_support
	 *
	 * @dataProvider data_dimensions_block_support
	 *
	 * @param string $theme_name          The theme to switch to.
	 * @param string $block_name          The test block name to register.
	 * @param mixed  $dimensions_settings The dimensions block support settings.
	 * @param mixed  $dimensions_style    The dimensions styles within the block attributes.
	 * @param string $expected_wrapper    Expected markup for the block wrapper.
	 * @param string $wrapper             Existing markup for the block wrapper.
	 */
	public function test_dimensions_block_support( $theme_name, $block_name, $dimensions_settings, $dimensions_style, $expected_wrapper, $wrapper ) {
		switch_theme( $theme_name );
		$this->test_block_name = $block_name;

		register_block_type(
			$this->test_block_name,
			array(
				'api_version' => 2,
				'attributes'  => array(
					'style' => array(
						'type' => 'object',
					),
				),
				'supports'    => array(
					'dimensions' => $dimensions_settings,
				),
			)
		);

		$block = array(
			'blockName' => $block_name,
			'attrs'     => array(
				'style' => array(
					'dimensions' => $dimensions_style,
				),
			),
		);

		$actual = wp_render_dimensions_support( $wrapper, $block );

		$this->assertSame(
			$expected_wrapper,
			$actual,
			'Dimensions block wrapper markup should be correct'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_dimensions_block_support() {
		return array(
			'aspect ratio style is applied, with min-height unset' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/dimensions-rules-are-output',
				'dimensions_settings' => array(
					'aspectRatio' => true,
				),
				'dimensions_style'    => array(
					'aspectRatio' => '16/9',
				),
				'expected_wrapper'    => '<div class="has-aspect-ratio" style="aspect-ratio:16/9;min-height:unset;">Content</div>',
				'wrapper'             => '<div>Content</div>',
			),
			'dimensions style is appended if a style attribute already exists' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/dimensions-rules-are-output',
				'dimensions_settings' => array(
					'aspectRatio' => true,
				),
				'dimensions_style'    => array(
					'aspectRatio' => '16/9',
				),
				'expected_wrapper'    => '<div class="wp-block-test has-aspect-ratio" style="color:red;aspect-ratio:16/9;min-height:unset;">Content</div>',
				'wrapper'             => '<div class="wp-block-test" style="color:red;">Content</div>',
			),
			'aspect ratio style is unset if block has min-height set' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/dimensions-rules-are-output',
				'dimensions_settings' => array(
					'aspectRatio' => true,
				),
				'dimensions_style'    => array(
					'minHeight' => '100px',
				),
				'expected_wrapper'    => '<div style="min-height:100px;aspect-ratio:unset;">Content</div>',
				'wrapper'             => '<div style="min-height:100px">Content</div>',
			),
			'aspect ratio style is not applied if the block does not support aspect ratio' => array(
				'theme_name'          => 'block-theme-child-with-fluid-typography',
				'block_name'          => 'test/background-rules-are-not-output',
				'dimensions_settings' => array(
					'aspectRatio' => false,
				),
				'dimensions_style'    => array(
					'aspectRatio' => '16/9',
				),
				'expected_wrapper'    => '<div>Content</div>',
				'wrapper'             => '<div>Content</div>',
			),
		);
	}
}
