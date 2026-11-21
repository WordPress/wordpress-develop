<?php
/**
 * Plugin Name: Fixture: Fatal after output
 * Description: Flushes the page and then fatals, so the response is still a 200. Expected to fail from the debug log.
 * Version: 1.0.0
 */

add_action(
	'wp_footer',
	static function () {
		while ( ob_get_level() ) {
			ob_end_flush();
		}
		flush();

		fixture_function_that_does_not_exist();
	}
);
