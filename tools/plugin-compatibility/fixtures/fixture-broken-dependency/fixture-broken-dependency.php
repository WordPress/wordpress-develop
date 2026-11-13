<?php
/**
 * Plugin Name: Fixture: Broken dependency
 * Description: Fatals on the front end. Only tested as a dependency of fixture-requires-broken-dependency.
 * Version: 1.0.0
 */

add_action( 'template_redirect', 'fixture_function_that_does_not_exist' );
