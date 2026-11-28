<?php
/**
 * Plugin Name: Fixture: Fatal on the login screen
 * Description: Fatals before any output on the login screen only. Expected to fail with an HTTP 500.
 * Version: 1.0.0
 */

add_action( 'login_init', 'fixture_function_that_does_not_exist' );
