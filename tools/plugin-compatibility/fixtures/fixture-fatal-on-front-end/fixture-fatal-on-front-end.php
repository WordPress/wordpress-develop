<?php
/**
 * Plugin Name: Fixture: Fatal on the front end
 * Description: Fatals before any output on front end requests. Expected to fail with an HTTP 500.
 * Version: 1.0.0
 */

add_action( 'template_redirect', 'fixture_function_that_does_not_exist' );
