<?php
/**
 * PHPUnit bootstrap for running the WordPress test suite with invariants.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// Already loaded when the extension loaded the invariants; loaded here to report when it did not.
require_once __DIR__ . '/includes/load.php';
wp_invariants_require_loaded( __DIR__ . '/gameshark.php' );

require_once __DIR__ . '/includes/class-wp-invariant-phpunit-extension.php';
require dirname( __DIR__ ) . '/phpunit/includes/bootstrap.php';
