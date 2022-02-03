<?php

/**
 * @group themes
 */
class Tests_Theme_GlobalStylesheet extends WP_UnitTestCase {

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

	public function filter_set_theme_root() {
		return $this->theme_root;
	}

	public function test_block_theme_using_variables() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 13px' ), 'small font size is 13px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is 20px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 36px' ), 'large font size is 36px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'x-large font size is 42px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--custom: 100px;' ), 'custom font size is 100px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_block_theme_using_presets() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--small: 13px' ), 'small font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--large: 36px' ), 'large font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'x-large font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--custom: 100px;' ), 'custom font size is not present' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_block_theme_using_defaults() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet();
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 13px' ), 'small font size is 13px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is 20px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 36px' ), 'large font size is 36px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'x-large font size is 42px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--custom: 100px;' ), 'custom font size is 100px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_variables() {
		switch_theme( 'default' );

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 13px' ), 'small font size is 13px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is 20px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 36px' ), 'large font size is 36px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'x-large font size is 42px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_presets() {
		switch_theme( 'default' );

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--small: 13px' ), 'small font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--large: 36px' ), 'large font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'x-large font size is not present' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_no_presets_using_defaults() {
		switch_theme( 'default' );

		$styles = wp_get_global_stylesheet();
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 13px' ), 'small font size is 13px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is 20px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 36px' ), 'large font size is 36px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'x-large font size is 42px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_presets_using_variables() {
		switch_theme( 'classic-with-presets' );

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 18px' ), 'small font size is 18px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is 20px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 26.25px' ), 'large font size is 26.25px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'x-large font size is 42px' );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_presets_using_presets() {
		switch_theme( 'classic-with-presets' );

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--small: 18px' ), 'small font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--large: 26.25px' ), 'large font size is not present' );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'x-large font size is not present' );

		switch_theme( WP_DEFAULT_THEME );
	}

	/**
	 * @group my-unit-test
	 */
	public function test_variables_in_classic_theme_with_presets_using_defaults() {
		switch_theme( 'classic-with-presets' );

		$styles = wp_get_global_stylesheet();
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 18px' ), 'small font size is 18px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ), 'medium font size is 20px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 26.25px' ), 'large font size is 26.25px' );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ), 'small font size is 42px' );

		switch_theme( WP_DEFAULT_THEME );
	}

}
