<?php
/**
 * Plugin Name: Fixture: Requires newer WordPress
 * Description: Declares a WordPress version that does not exist yet. Expected to be skipped.
 * Version: 1.0.0
 * Requires at least: 99.0
 */

add_action( 'wp_footer', '__return_null' );
