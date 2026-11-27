<?php
/**
 * Plugin Name: Fixture: Fatal on activation
 * Description: Calls an undefined function from its activation hook. Expected to fail activation.
 * Version: 1.0.0
 */

register_activation_hook( __FILE__, 'fixture_function_that_does_not_exist' );
