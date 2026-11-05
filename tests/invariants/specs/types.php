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
);
