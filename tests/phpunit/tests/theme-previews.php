<?php

/**
 * test wp-includes/theme-previews.php
 *
 * @group themes
 */
class Tests_Theme_Previews extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
	}

	public function tear_down() {
		unset( $_GET['wp_theme_preview'] );
		parent::tear_down();
	}

	public function test_initialize_theme_preview_hooks() {
		$_GET['wp_theme_preview'] = 'twentytwentythree';
		do_action( 'plugins_loaded' ); // Ensure `plugins_loaded` triggers `wp_initialize_theme_preview_hooks`.

		$this->assertEquals( has_filter( 'stylesheet', 'wp_get_theme_preview_path' ), 10 );
		$this->assertEquals( has_filter( 'template', 'wp_get_theme_preview_path' ), 10 );
		$this->assertEquals( has_action( 'init', 'wp_attach_theme_preview_middleware' ), 10 );
		$this->assertEquals( has_action( 'admin_head', 'wp_block_theme_activate_nonce' ), 10 );
	}
}
