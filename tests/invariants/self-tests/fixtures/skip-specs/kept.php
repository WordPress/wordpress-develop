<?php
/**
 * Spec file which the self-tests load while leaving out its neighbor.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'kept' )
		->describe( 'Loaded by the self-tests of WP_INVARIANTS_SKIP.' )
		->before(
			'wp_invariants_fixture_counter',
			static function () {}
		),
);
