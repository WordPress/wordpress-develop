<?php
/**
 * Plugin Name: Fixture: Requires newer PHP
 * Description: Declares a PHP version no runner has. Expected to be skipped.
 * Version: 1.0.0
 * Requires PHP: 99.0
 */

add_action( 'wp_footer', '__return_null' );
