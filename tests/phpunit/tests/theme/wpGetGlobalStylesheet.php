<?php

/**
 * Tests wp_get_global_stylesheet().
 *
 * @group themes
 */
class Tests_Theme_wpGetGlobalStylesheet extends WP_UnitTestCase {

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

		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];
		$this->theme_root     = realpath( DIR_TESTDATA . '/themedir1' );

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );

		// Set up the new root.
		add_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );

		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;

		// Clear up the filters to modify the theme root.
		remove_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		remove_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		remove_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );

		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		parent::tear_down();
	}

	/**
	 * Cleans up global scope.
	 *
	 * @global WP_Styles $wp_styles
	 */
	public function clean_up_global_scope() {
		global $wp_styles;
		parent::clean_up_global_scope();
		$wp_styles = null;
	}

	public function filter_set_theme_root() {
		return $this->theme_root;
	}

	public function test_block_theme_using_variables() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertStringContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is 13px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is 36px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );
		$this->assertStringContainsString( '--wp--preset--font-size--custom: 100px;', $styles, 'custom font size is 100px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_block_theme_using_presets() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertStringNotContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--custom: 100px;', $styles, 'custom font size is not present' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_block_theme_using_defaults() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet();
		$this->assertStringContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is 13px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is 36px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );
		$this->assertStringContainsString( '--wp--preset--font-size--custom: 100px;', $styles, 'custom font size is 100px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_variables() {
		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertStringContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is 13px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is 36px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_presets() {
		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertStringNotContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is not present' );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_defaults() {
		$styles = wp_get_global_stylesheet();
		$this->assertStringContainsString( '--wp--preset--font-size--small: 13px', $styles, 'small font size is 13px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 36px', $styles, 'large font size is 36px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );
	}

	public function test_variables_in_classic_theme_with_presets_using_variables() {
		add_theme_support(
			'editor-font-sizes',
			array(
				array(
					'name' => 'Small',
					'size' => 18,
					'slug' => 'small',
				),
				array(
					'name' => 'Large',
					'size' => 26.25,
					'slug' => 'large',
				),
			)
		);

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertStringContainsString( '--wp--preset--font-size--small: 18px', $styles, 'small font size is 18px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 26.25px', $styles, 'large font size is 26.25px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is 42px' );

		remove_theme_support( 'editor-font-sizes' );
	}

	public function test_variables_in_classic_theme_with_presets_using_presets() {
		add_theme_support(
			'editor-font-sizes',
			array(
				array(
					'name' => 'Small',
					'size' => 18,
					'slug' => 'small',
				),
				array(
					'name' => 'Large',
					'size' => 26.25,
					'slug' => 'large',
				),
			)
		);

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertStringNotContainsString( '--wp--preset--font-size--small: 18px', $styles, 'small font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--large: 26.25px', $styles, 'large font size is not present' );
		$this->assertStringNotContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'x-large font size is not present' );

		remove_theme_support( 'editor-font-sizes' );
	}

	public function test_variables_in_classic_theme_with_presets_using_defaults() {
		add_theme_support(
			'editor-font-sizes',
			array(
				array(
					'name' => 'Small',
					'size' => 18,
					'slug' => 'small',
				),
				array(
					'name' => 'Large',
					'size' => 26.25,
					'slug' => 'large',
				),
			)
		);

		$styles = wp_get_global_stylesheet();
		$this->assertStringContainsString( '--wp--preset--font-size--small: 18px', $styles, 'small font size is 18px' );
		$this->assertStringContainsString( '--wp--preset--font-size--medium: 20px', $styles, 'medium font size is 20px' );
		$this->assertStringContainsString( '--wp--preset--font-size--large: 26.25px', $styles, 'large font size is 26.25px' );
		$this->assertStringContainsString( '--wp--preset--font-size--x-large: 42px', $styles, 'small font size is 42px' );

		remove_theme_support( 'editor-font-sizes' );
	}

	/**
	 * Tests that stored CSS is enqueued.
	 *
	 * @ticket 56467
	 *
	 * @covers ::wp_enqueue_stored_styles
	 */
	public function test_should_enqueue_stored_styles() {
		$core_styles_to_enqueue = array(
			array(
				'selector'     => '.saruman',
				'declarations' => array(
					'color'        => 'white',
					'height'       => '100px',
					'border-style' => 'solid',
				),
			),
		);

		// Enqueues a block supports (core styles).
		wp_style_engine_get_stylesheet_from_css_rules(
			$core_styles_to_enqueue,
			array(
				'context' => 'block-supports',
			)
		);

		$my_styles_to_enqueue = array(
			array(
				'selector'     => '.gandalf',
				'declarations' => array(
					'color'        => 'grey',
					'height'       => '90px',
					'border-style' => 'dotted',
				),
			),
		);

		// Enqueues some other styles.
		wp_style_engine_get_stylesheet_from_css_rules(
			$my_styles_to_enqueue,
			array(
				'context' => 'my-styles',
			)
		);

		wp_enqueue_stored_styles( array( 'prettify' => false ) );

		$this->assertSame(
			array( '.saruman{color:white;height:100px;border-style:solid;}' ),
			wp_styles()->registered['core-block-supports']->extra['after'],
			'Registered styles with handle of "core-block-supports" do not match expected value from Style Engine store.'
		);

		$this->assertSame(
			array( '.gandalf{color:grey;height:90px;border-style:dotted;}' ),
			wp_styles()->registered['wp-style-engine-my-styles']->extra['after'],
			'Registered styles with handle of "wp-style-engine-my-styles" do not match expected value from the Style Engine store.'
		);
	}
}
