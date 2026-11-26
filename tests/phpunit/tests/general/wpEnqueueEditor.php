<?php

/**
 * Tests for the `wp_enqueue_editor()` function.
 *
 * @group general
 * @group editor
 *
 * @covers ::wp_enqueue_editor
 */
class Tests_General_WpEnqueueEditor extends WP_UnitTestCase {

	/**
	 * Tests that wp_enqueue_editor() loads the default editor assets and footer hooks.
	 *
	 * @ticket 66822
	 */
	public function test_wp_enqueue_editor_enqueues_default_assets() {
		$old_wp_scripts = $GLOBALS['wp_scripts'] ?? null;
		$old_wp_styles  = $GLOBALS['wp_styles'] ?? null;

		try {
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			$GLOBALS['wp_styles']  = new WP_Styles();

			wp_enqueue_editor();

			$this->assertTrue( wp_script_is( 'editor', 'enqueued' ), 'The editor script should be enqueued.' );
			$this->assertTrue( wp_style_is( 'editor-buttons', 'enqueued' ), 'The editor-buttons style should be enqueued.' );
			$this->assertSame(
				45,
				has_action( 'wp_print_footer_scripts', array( '_WP_Editors', 'print_default_editor_scripts' ) ),
				'print_default_editor_scripts should be hooked at priority 45.'
			);
			$this->assertSame(
				1,
				has_action( 'wp_print_footer_scripts', array( '_WP_Editors', 'force_uncompressed_tinymce' ) ),
				'force_uncompressed_tinymce should be hooked at priority 1.'
			);
		} finally {
			$GLOBALS['wp_scripts'] = $old_wp_scripts;
			$GLOBALS['wp_styles']  = $old_wp_styles;
		}
	}

	/**
	 * Tests that wp_enqueue_editor() hooks the footer callbacks to the admin footer in the admin.
	 *
	 * @ticket 66822
	 */
	public function test_wp_enqueue_editor_uses_admin_footer_hook_in_admin() {
		$old_wp_scripts = $GLOBALS['wp_scripts'] ?? null;
		$old_wp_styles  = $GLOBALS['wp_styles'] ?? null;

		try {
			$GLOBALS['wp_scripts'] = new WP_Scripts();
			$GLOBALS['wp_styles']  = new WP_Styles();

			set_current_screen( 'dashboard' );

			wp_enqueue_editor();

			$this->assertSame(
				45,
				has_action( 'admin_print_footer_scripts', array( '_WP_Editors', 'print_default_editor_scripts' ) ),
				'The default editor scripts should be printed in the admin footer.'
			);
			$this->assertSame(
				1,
				has_action( 'admin_print_footer_scripts', array( '_WP_Editors', 'force_uncompressed_tinymce' ) ),
				'TinyMCE should be forced uncompressed in the admin footer.'
			);
			$this->assertFalse(
				has_action( 'wp_print_footer_scripts', array( '_WP_Editors', 'print_default_editor_scripts' ) ),
				'The front-end footer hook should not be used in the admin.'
			);
		} finally {
			$GLOBALS['wp_scripts'] = $old_wp_scripts;
			$GLOBALS['wp_styles']  = $old_wp_styles;
		}
	}
}
