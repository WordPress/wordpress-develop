<?php
/**
 * Plugin Name: Fixture: Requires Akismet
 * Description: Requires the Akismet plugin that ships with core, which is installed but not active. Expected to pass with Akismet active.
 * Version: 1.0.0
 * Requires Plugins: akismet
 */

add_action( 'wp_footer', '__return_null' );
