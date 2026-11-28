<?php
/**
 * Plugin Name: Fixture: Fatal under WP-CLI only
 * Description: Reads back a file scope variable with global, which WP-CLI does not make global. Expected to pass with a note.
 * Version: 1.0.0
 */

$fixture_wp_cli_only_fatal = true;

add_action(
	'init',
	static function () {
		global $fixture_wp_cli_only_fatal;

		if ( ! $fixture_wp_cli_only_fatal ) {
			fixture_function_that_does_not_exist();
		}
	}
);
