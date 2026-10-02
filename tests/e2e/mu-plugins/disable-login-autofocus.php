<?php
/**
 * Disables login autofocus during end-to-end tests.
 *
 * @package WordPress
 */

add_filter( 'enable_login_autofocus', '__return_false' );
