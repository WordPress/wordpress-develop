<?php
/**
 * Plugin Name: Fixture: Parse error after output
 * Description: Flushes the page and then includes a file with a syntax error. Expected to fail from the debug log.
 * Version: 1.0.0
 */

add_action(
	'wp_footer',
	static function () {
		while ( ob_get_level() ) {
			ob_end_flush();
		}
		flush();

		// Not a .php file, so that nothing linting the repository trips over it.
		include __DIR__ . '/syntax-error.inc';
	}
);
