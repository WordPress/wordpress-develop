<?php

/**
 * Tests for `_default_wp_die_handler()`.
 *
 * @group functions
 *
 * @covers ::_default_wp_die_handler
 */
class Tests_Functions_DefaultWpDieHandler extends WP_UnitTestCase {

	/**
	 * Tests that the `heading` argument is printed as the page's main heading.
	 *
	 * @ticket 65797
	 */
	public function test_should_print_heading_before_message() {
		$actual = get_echo(
			'_default_wp_die_handler',
			array(
				'Something went wrong.',
				'',
				array(
					'heading' => 'WordPress &rsaquo; Error',
					'exit'    => false,
				),
			)
		);

		$this->assertStringContainsString(
			'<h1>WordPress &rsaquo; Error</h1><div class="wp-die-message">Something went wrong.</div>',
			$actual
		);
	}
}
