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
	 * Tests that wp_body_open() fires the 'wp_body_open' action.
	 *
	 * @ticket 65819
	 */
	public function test_wp_body_open_should_fire_action() {
		$action = new MockAction();
		add_action( 'wp_body_open', array( $action, 'action' ) );

		wp_body_open();

		$this->assertSame( 1, $action->get_call_count() );
	}

	/**
	 * Tests that wp_body_open() executes callbacks hooked to 'wp_body_open' in priority order.
	 *
	 * @ticket 65819
	 */
	public function test_wp_body_open_should_execute_callbacks_in_priority_order() {
		$execution_order = array();

		add_action(
			'wp_body_open',
			static function () use ( &$execution_order ) {
				$execution_order[] = 'second';
			},
			10
		);

		add_action(
			'wp_body_open',
			static function () use ( &$execution_order ) {
				$execution_order[] = 'first';
			},
			5
		);

		add_action(
			'wp_body_open',
			static function () use ( &$execution_order ) {
				$execution_order[] = 'third';
			},
			15
		);

		wp_body_open();

		$this->assertSame( array( 'first', 'second', 'third' ), $execution_order );
	}

	/**
	 * Tests that wp_body_open() outputs content echoed by hooked callbacks.
	 *
	 * @ticket 65819
	 */
	public function test_wp_body_open_should_output_hooked_content() {
		add_action(
			'wp_body_open',
			static function () {
				echo '<div id="custom-body-header">Hello</div>';
			}
		);

		ob_start();
		wp_body_open();
		$output = ob_get_clean();

		$this->assertSame( '<div id="custom-body-header">Hello</div>', $output );
	}
}
