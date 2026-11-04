<?php
/**
 * The invariants file for the self-tests of the invariant support code.
 *
 * Code in fixtures/ stands in for WordPress, and the tests in tests/ are the test code.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once dirname( __DIR__ ) . '/includes/load.php';

WP_Invariant::configure_paths(
	array(
		'test' => array( __DIR__ . '/tests' ),
		'core' => array( __DIR__ . '/fixtures' ),
	)
);

return WP_Invariant::load_specs( __DIR__ . '/specs' );
