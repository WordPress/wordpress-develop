<?php

/**
 * test wp-includes/site-previews.php
 *
 * @group themes
 */
class Tests_Site_Previews extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
	}

	public function tear_down() {
		unset( $_GET['wp_site_preview'] );
		parent::tear_down();
	}

	/**
	 * Test that the admin bar is hidden when wp_site_preview parameter is set to 1.
	 */
	public function test_initialize_site_preview_hooks() {
		$_GET['wp_site_preview'] = 1;
		do_action( 'init' ); // Ensure `init` triggers `wp_initialize_site_preview_hooks`.
		$this->assertEquals( has_filter( 'show_admin_bar', '__return_false' ), 10 );
	}

	/**
	 * Test that the admin bar is not hidden when wp_site_preview parameter is set to a different value.
	 */
	public function test_initialize_site_preview_hooks_different_value() {
		$_GET['wp_site_preview'] = 2;
		do_action( 'init' );
		$this->assertFalse( has_filter( 'show_admin_bar', '__return_false' ) );
	}
}
