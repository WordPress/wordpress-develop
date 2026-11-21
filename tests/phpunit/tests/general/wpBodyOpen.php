<?php

/**
 * Tests for the `wp_body_open()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::wp_body_open
 */
class Tests_General_WpBodyOpen extends WP_UnitTestCase {

	/**
	 * Tests that wp_body_open() triggers the action and outputs content from hooked callbacks.
	 *
	 * @ticket 65819
	 */
	public function test_wp_body_open_should_fire_action_and_output_hooked_content() {
		remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );

		$action = new MockAction();
		add_action( 'wp_body_open', array( $action, 'action' ) );

		add_action(
			'wp_body_open',
			static function () {
				echo '<div id="custom-body-header">Hello</div>';
			}
		);

		$this->expectOutputString( '<div id="custom-body-header">Hello</div>' );
		wp_body_open();

		$this->assertSame( 1, $action->get_call_count() );
	}
}
