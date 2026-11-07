<?php

/**
 * Tests for the `add_thickbox()` function.
 *
 * @group general
 * @group script
 *
 * @covers ::add_thickbox
 */
class Tests_General_AddThickbox extends WP_UnitTestCase {

	protected $old_wp_scripts;
	protected $old_wp_styles;

	public function set_up() {
		parent::set_up();
		$this->old_wp_scripts = $GLOBALS['wp_scripts'] ?? null;
		$this->old_wp_styles  = $GLOBALS['wp_styles'] ?? null;

		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		$GLOBALS['wp_styles']  = $this->old_wp_styles;
		remove_action( 'admin_head', '_thickbox_path_admin_subfolder' );
		parent::tear_down();
	}

	/**
	 * Tests that thickbox script and style are enqueued.
	 *
	 * @ticket 65819
	 */
	public function test_add_thickbox_enqueues_assets() {
		wp_default_scripts( $GLOBALS['wp_scripts'] );
		wp_default_styles( $GLOBALS['wp_styles'] );

		add_thickbox();

		$this->assertTrue( wp_script_is( 'thickbox', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'thickbox', 'enqueued' ) );
	}

	/**
	 * Tests that network admin action is not hooked when not in network admin.
	 *
	 * @ticket 65819
	 */
	public function test_add_thickbox_does_not_hook_network_admin_head_by_default() {
		add_thickbox();

		$this->assertFalse( has_action( 'admin_head', '_thickbox_path_admin_subfolder' ) );
	}
}
