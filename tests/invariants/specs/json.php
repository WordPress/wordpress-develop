<?php
/**
 * Invariants of JSON.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'no-silent-failure' )
		->describe( 'wp_json_encode() returns a string. When it returns false, a REST response or a script\'s data is quietly empty.' )
		->after(
			'wp_json_encode',
			static function ( WP_Invariant_Call $call ) {
				$call->expect( is_string( $call->return_value() ), 'wp_json_encode() could not encode a value of type %s: %s', gettype( $call->arg( 'value' ) ), json_last_error_msg() );
			}
		),
);
