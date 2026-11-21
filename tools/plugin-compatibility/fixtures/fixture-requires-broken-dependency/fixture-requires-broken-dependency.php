<?php
/**
 * Plugin Name: Fixture: Requires a broken dependency
 * Description: Healthy itself, but requires fixture-broken-dependency. Expected to be skipped and the dependency blamed.
 * Version: 1.0.0
 * Requires Plugins: fixture-broken-dependency
 */

add_action( 'wp_footer', '__return_null' );
