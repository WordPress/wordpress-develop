<?php
/**
 * Plugin Name: Fixture: Requires a plugin that fatals under WP-CLI only
 * Description: Healthy itself, but its dependency stops WP-CLI from loading, so it cannot be activated through it. Expected to be skipped rather than failed.
 * Version: 1.0.0
 * Requires Plugins: fixture-wp-cli-only-fatal
 */

add_action( 'wp_footer', '__return_null' );
