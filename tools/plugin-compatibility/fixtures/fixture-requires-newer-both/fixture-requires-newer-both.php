<?php
/**
 * Plugin Name: Fixture: Requires newer WordPress and PHP
 * Description: Declares WordPress and PHP versions no runner has, which core words differently from either alone. Expected to be skipped.
 * Version: 1.0.0
 * Requires at least: 99.0
 * Requires PHP: 99.0
 */

add_action( 'wp_footer', '__return_null' );
