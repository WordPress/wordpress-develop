<?php

/**
 * Tests for the `wp_meta()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::wp_meta
 */
class Tests_General_WpMeta extends WP_UnitTestCase {

	/**
	 * Tests that wp_meta() fires the 'wp_meta' action and outputs content from hooked callbacks.
	 *
	 * @ticket 65819
	 */
	public function test_wp_meta_should_fire_action_and_output_hooked_content() {
		$action = new MockAction();
		add_action( 'wp_meta', array( $action, 'action' ) );

		add_action(
			'wp_meta',
			static function () {
				echo '<li id="custom-meta-item">Custom Meta</li>';
			}
		);

		$this->expectOutputString( '<li id="custom-meta-item">Custom Meta</li>' );
		wp_meta();

		$this->assertSame( 1, $action->get_call_count() );
	}
}
