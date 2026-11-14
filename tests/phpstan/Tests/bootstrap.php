<?php
/**
 * Bootstraps the tests of the PHPStan extensions.
 *
 * @package WordPress
 */

declare(strict_types=1);

/*
 * The WordPress root that the hook documentation fixtures resolve their
 * "This filter is documented in <file>" references against, in place of `src`.
 * Read by tests.neon, which has no other way to learn where this directory is.
 */
putenv( 'WP_PHPSTAN_TESTS_WORDPRESS_ROOT=' . __DIR__ . '/data/wordpress' );

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';
