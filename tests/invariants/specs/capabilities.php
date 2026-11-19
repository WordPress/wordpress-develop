<?php
/**
 * Invariants of capabilities.
 *
 * WP_User::has_cap() maps the capability it is asked about to primitive
 * capabilities with map_meta_cap(), then checks them. These invariants look at
 * the capabilities map_meta_cap() returned inside a has_cap() call, which
 * `capabilities/well-typed` records by the depth of the call, instead of calling
 * map_meta_cap() again: its filter would run, and tests count how often it runs.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

$mapped = WP_Invariant::shared( 'capabilities/mapped' );

// Returns what map_meta_cap() returned inside a has_cap() call, or null.
$mapped_in = static function ( WP_Invariant_Call $call ) use ( $mapped ) {
	$depth = $call->depth() + 1;
	return isset( $mapped->caps[ $depth ] ) ? $mapped->caps[ $depth ] : null;
};

return array(
	WP_Invariant::define( 'well-typed' )
		->describe( 'map_meta_cap() returns an array of non-empty strings, and WP_User::has_cap() returns a bool.' )
		->include_test_callers()
		->after(
			'map_meta_cap',
			static function ( WP_Invariant_Call $call ) use ( $mapped ) {
				$caps = $call->return_value();
				$mapped->caps[ $call->depth() ] = $caps;

				$valid = is_array( $caps );
				foreach ( $valid ? $caps : array() as $cap ) {
					$valid = $valid && is_string( $cap ) && '' !== $cap;
				}
				$call->expect( $valid, 'map_meta_cap( %s ) returned %s.', $call->arg( 'cap' ), $caps );
			}
		)
		->after(
			'WP_User::has_cap',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( is_bool( $call->return_value() ), 'WP_User::has_cap( %s ) returned %s.', $call->arg( 'cap' ), $call->return_value() );
			}
		),

	WP_Invariant::define( 'do-not-allow-never-granted' )
		->describe( 'WP_User::has_cap() never grants a capability which map_meta_cap() mapped to do_not_allow.' )
		->include_test_callers()
		->after(
			'WP_User::has_cap',
			static function ( WP_Invariant_Call $call ) use ( $mapped_in ) {
				if ( true !== $call->return_value() ) {
					return;
				}

				$caps = $mapped_in( $call );
				$call->expect( ! is_array( $caps ) || ! in_array( 'do_not_allow', $caps, true ), 'WP_User::has_cap( %s ) granted it to the user %d, though it maps to do_not_allow.', $call->arg( 'cap' ), $call->self()->ID );
			}
		),

	WP_Invariant::define( 'logged-out-can-only-exist' )
		->describe( 'For a user who is not logged in, WP_User::has_cap() is only true when the only capability required is exist.' )
		->include_test_callers()
		->after(
			'WP_User::has_cap',
			static function ( WP_Invariant_Call $call ) use ( $mapped_in ) {
				if ( true !== $call->return_value() || 0 !== $call->self()->ID ) {
					return;
				}

				$caps = $mapped_in( $call );
				$call->expect( ! is_array( $caps ) || array() === array_diff( $caps, array( 'exist' ) ), 'WP_User::has_cap( %s ) granted it to a user who is not logged in, though it needs %s.', $call->arg( 'cap' ), is_array( $caps ) ? implode( ', ', $caps ) : $caps );
			}
		),
);
