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

// The functions in fixtures/types/ are hooked for the types report, as WordPress's functions are with WP_INVARIANTS_TYPES=1.
return array_merge(
	WP_Invariant::load_specs( __DIR__ . '/specs' ),
	WP_Invariant_Types::specs( WP_Invariant::functions_in( array( __DIR__ . '/fixtures/types' ) ), array( __DIR__ . '/fixtures/types' ), array( __DIR__ . '/tests' ) )
);
