<?php
/**
 * Disables login autofocus during end-to-end tests.
 *
 * WordPress's delayed autofocus can clear a password after automated typing.
 * The E2E runner temporarily installs this plugin to prevent that interference.
 *
 * The runner creates its own copy under LOCAL_DIR/wp-content/mu-plugins (src by
 * default). It removes that copy on normal exit or when interrupted by SIGHUP,
 * SIGINT, SIGQUIT, or SIGTERM.
 *
 * If the tested WordPress installation is elsewhere, install this file in that
 * installation's wp-content/mu-plugins for the test run, then remove it afterward.
 *
 * @package WordPress
 */

add_filter( 'enable_login_autofocus', '__return_false' );
