<?php
/**
 * PHPUnit bootstrap for the self-tests of the invariant support code.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once dirname( __DIR__ ) . '/includes/load.php';
wp_invariants_require_loaded( __DIR__ . '/gameshark.php' );

require_once dirname( __DIR__ ) . '/includes/class-wp-invariant-phpunit-extension.php';
require_once __DIR__ . '/fixtures/core.php';
require_once __DIR__ . '/fixtures/class-wp-invariants-fixture.php';
require_once __DIR__ . '/fixtures/class-wp-invariants-fixture-properties.php';
require_once __DIR__ . '/fixtures/types/functions.php';
require_once __DIR__ . '/fixtures/types/class-wp-invariants-types-fixture.php';
require_once __DIR__ . '/fixtures/types/class-wp-invariants-types-private-fixture.php';
require_once __DIR__ . '/fixtures/types/interface-wp-invariants-types-fixture-interface.php';
