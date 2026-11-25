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
