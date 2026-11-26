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

			$this->assertTrue( wp_script_is( 'editor', 'enqueued' ) );
			$this->assertTrue( wp_style_is( 'editor-buttons', 'enqueued' ) );
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
}
