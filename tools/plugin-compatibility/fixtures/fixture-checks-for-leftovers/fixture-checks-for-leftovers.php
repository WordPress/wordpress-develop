<?php
/**
 * Plugin Name: Fixture: Checks for leftovers
 * Description: Fatals if anything from fixture-leaves-files-behind survived the reset. Expected to pass when tested straight after it.
 * Version: 1.0.0
 */

add_action(
	'template_redirect',
	static function () {
		global $wpdb;

		if ( file_exists( ABSPATH . 'fixture-leftover.txt' )
			|| get_option( 'fixture_leftover' )
			|| $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fixture_leftover'" ) ) {
			fixture_function_that_does_not_exist();
		}
	}
);
