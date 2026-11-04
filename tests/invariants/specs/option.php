<?php
/**
 * Invariants of options.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'name-not-empty' )
		->describe( 'The option name given to get_option() is a string which is not empty after trim().' )
		->before(
			'get_option',
			static function ( WP_Invariant_Call $call ) {
				$option = $call->arg( 'option' );
				$call->expect( is_string( $option ) && '' !== trim( $option ), 'get_option() received the option name %s.', var_export( $option, true ) );
			}
		),
);
