<?php

/**
 * Tests wp_add_global_styles_for_blocks().
 *
 * @group themes
 */
class Tests_Theme_wpGetGlobalStylesForBlocks extends WP_UnitTestCase {

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

	public function test_styles_for_third_party_blocks() {
		switch_theme( 'block-theme' );

		// The 3rd party block styles are only missing
		// if the assets are loaded per block.
		add_filter( 'should_load_separate_core_block_assets', '__return_true' );

		$name     = 'my/third-party-block';
		$settings = array(
			'icon'            => 'text',
			'category'        => 'common',
			'render_callback' => 'foo',
		);
		register_block_type( $name, $settings );
		wp_enqueue_global_styles();
		unregister_block_type( $name );

		$this->assertStringContainsString( '.wp-block-my-third-party-block{background-color: hotpink;}', get_echo( 'wp_print_styles' ), '3rd party block styles are included' );

		switch_theme( WP_DEFAULT_THEME );
	}

}
