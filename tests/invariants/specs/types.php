<?php
/**
 * Invariants of the types of arguments.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'no-wp-error-arguments' )
		->describe( 'Functions which look up or change an object by its ID are never given a WP_Error, which makes them quietly return null or false.' )
		->before_each(
			array( 'get_post', 'get_post_field', 'get_userdata', 'get_term', 'get_comment', 'get_permalink', 'get_post_meta', 'update_post_meta', 'wp_update_post', 'wp_delete_post', 'user_can' ),
			static function ( WP_Invariant_Call $call ) {
				foreach ( $call->args() as $position => $arg ) {
					if ( $arg instanceof WP_Error ) {
						$call->expect( false, '%s() was given a WP_Error (%s) as argument %d.', $call->target(), $arg->get_error_code(), $position + 1 );
						return;
					}
				}
			}
		),

	WP_Invariant::define( 'no-false-ids' )
		->describe( 'get_post() and get_comment() are never given false or an empty string, which they quietly read as the current global post or comment, unless their caller was given it and passes it on. Pass null for the current one.' )
		->before_each(
			array( 'get_post', 'get_comment' ),
			static function ( WP_Invariant_Call $call ) {
				$args = $call->args();
				$id   = isset( $args[0] ) ? $args[0] : null;
				if ( false !== $id && '' !== $id ) {
					return;
				}

				// A value the caller was given came from further out, such as a test of falsy input.
				$call->expect( in_array( $id, $call->caller_args(), true ), '%s() was given %s, which means the current global object.', $call->target(), var_export( $id, true ) );
			}
		),

	WP_Invariant::define( 'metadata-object-id' )
		->describe( 'The object ID given to the metadata API is a whole number, 0 or more, as an int or a string of digits. absint() would quietly turn -5 into the object 5, and 1.5 into 1.' )
		->before_each(
			array( 'add_metadata', 'update_metadata', 'get_metadata_raw', 'metadata_exists', 'delete_metadata' ),
			static function ( WP_Invariant_Call $call ) {
				if ( 'delete_metadata' === $call->target() && $call->arg( 'delete_all' ) ) {
					return;
				}

				$id = $call->arg( 'object_id' );
				$call->expect( ( is_int( $id ) && $id >= 0 ) || ( is_string( $id ) && ctype_digit( $id ) ), '%s() was given the object ID %s.', $call->target(), is_scalar( $id ) || null === $id ? var_export( $id, true ) : gettype( $id ) );
			}
		),
);
