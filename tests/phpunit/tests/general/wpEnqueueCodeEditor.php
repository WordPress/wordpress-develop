<?php

/**
 * Tests for the `wp_enqueue_code_editor()` function.
 *
 * @group general
 * @group dependencies
 *
 * @covers ::wp_enqueue_code_editor
 */
class Tests_General_WpEnqueueCodeEditor extends WP_UnitTestCase {

	protected ?WP_Scripts $old_wp_scripts = null;

	protected ?WP_Styles $old_wp_styles = null;

	public function set_up() {
		parent::set_up();
		$old_wp_scripts       = $GLOBALS['wp_scripts'] ?? null;
		$old_wp_styles        = $GLOBALS['wp_styles'] ?? null;
		$this->old_wp_scripts = $old_wp_scripts instanceof WP_Scripts ? $old_wp_scripts : null;
		$this->old_wp_styles  = $old_wp_styles instanceof WP_Styles ? $old_wp_styles : null;

		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = $this->old_wp_scripts;
		$GLOBALS['wp_styles']  = $this->old_wp_styles;
		parent::tear_down();
	}


	/**
	 * Tests that a logged-in user with syntax highlighting disabled gets false.
	 *
	 * @ticket 66757
	 */
	public function test_wp_enqueue_code_editor_returns_false_when_syntax_highlighting_disabled() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		wp_get_current_user()->syntax_highlighting = 'false';

		$this->assertFalse(
			wp_enqueue_code_editor(
				array(
					'type' => 'text/css',
				)
			)
		);
		$this->assertFalse( wp_script_is( 'code-editor', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'code-editor', 'enqueued' ) );
	}
}
