<?php

/**
 * @group themes
 */
class Tests_Global_Stylesheet extends WP_UnitTestCase {

	public function test_block_theme() {
		switch_theme( 'block-theme' );

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 13px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 36px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--custom: 100px;' ) );

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--small: 13px' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--large: 36px' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--custom: 100px;' ) );

		$styles = wp_get_global_stylesheet();
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 13px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 36px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--custom: 100px;' ) );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_no_presets() {
		switch_theme( 'default' );

		$styles = wp_get_global_stylesheet( array( 'variables' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 13px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 36px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ) );

		$styles = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--small: 13px' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--large: 36px' ) );
		$this->assertFalse( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ) );

		$styles = wp_get_global_stylesheet();
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--small: 13px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--medium: 20px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--large: 36px' ) );
		$this->assertTrue( str_contains( $styles, '--wp--preset--font-size--x-large: 42px' ) );

		switch_theme( WP_DEFAULT_THEME );
	}

	public function test_variables_in_classic_theme_with_presets() {
		switch_theme( 'classic-with-presets' );

		$this->assertTrue( current_theme_supports( 'editor-font-sizes' ) );

		// $styles = wp_get_global_stylesheet( array( 'variables' ) );
		// $this->assertTrue( str_contains( $styles, "--wp--preset--font-size--small: 18px" ) );
		// $this->assertTrue( str_contains( $styles, "--wp--preset--font-size--medium: 20px" ) );
		// $this->assertTrue( str_contains( $styles, "--wp--preset--font-size--large: 26.25px" ) );
		// $this->assertTrue( str_contains( $styles, "--wp--preset--font-size--x-large: 42px" ) );

		// $styles = wp_get_global_stylesheet( array( 'presets' ) );
		// $this->assertFalse( str_contains( $styles, "--wp--preset--font-size--small: 18px" ) );
		// $this->assertFalse( str_contains( $styles, "--wp--preset--font-size--medium: 20px" ) );
		// $this->assertFalse( str_contains( $styles, "--wp--preset--font-size--large: 26.25px" ) );
		// $this->assertFalse( str_contains( $styles, "--wp--preset--font-size--x-large: 42px" ) );

		// $styles = wp_get_global_stylesheet();
		// $this->assertTrue( str_contains( $styles, "--wp--preset--font-size--small: 18px" ) );
		// $this->assertTrue( str_contains( $styles, "--wp--preset--font-size--medium: 20px" ) );
		// $this->assertTrue( str_contains( $styles, "--wp--preset--font-size--large: 26.25px" ) );
		// $this->assertTrue( str_contains( $styles, "--wp--preset--font-size--x-large: 42px" ) );

		switch_theme( WP_DEFAULT_THEME );
	}

}
