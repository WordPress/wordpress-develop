<?php
/**
 * Plugin Name: Fixture: Fatal in a background request
 * Description: Starts a non-blocking request on the front page that fatals a few seconds later, after the page itself has returned. Expected to fail from the debug log.
 * Version: 1.0.0
 */

add_action(
	'template_redirect',
	static function () {
		wp_remote_post(
			admin_url( 'admin-ajax.php?action=fixture_background_task' ),
			array(
				'blocking' => false,
				'timeout'  => 0.01,
			)
		);
	}
);

add_action(
	'wp_ajax_nopriv_fixture_background_task',
	static function () {
		ignore_user_abort( true );
		sleep( 3 );
		fixture_function_that_does_not_exist();
	}
);
