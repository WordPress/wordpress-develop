<?php
/**
 * Invariants of PCRE use.
 *
 * These hook built-in PHP functions, which slows down every call to a built-in
 * function. Leave them out with WP_INVARIANTS_SKIP=pcre.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'no-silent-failure' )
		->describe( 'No PCRE function fails: none runs out of backtracking or recursion, or is given invalid UTF-8 for a /u pattern. Those failures return null or false, which callers rarely check.' )
		->include_test_callers()
		->after_each(
			array( 'preg_match', 'preg_match_all', 'preg_replace', 'preg_replace_callback', 'preg_split' ),
			static function ( WP_Invariant_Call $call ) {
				// Read it first: nothing before this line calls a PCRE function.
				$error = preg_last_error();
				if ( PREG_NO_ERROR === $error ) {
					return;
				}

				$subject = $call->arg( 'subject' );
				$call->expect(
					false,
					'%s() failed: %s. The pattern was %s, and the subject %s.',
					$call->target(),
					preg_last_error_msg(),
					$call->arg( 'pattern' ),
					is_string( $subject ) ? strlen( $subject ) . ' bytes' : 'not a string'
				);
			}
		),
);
