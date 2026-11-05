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

	WP_Invariant::define( 'names-fit-columns' )
		->describe( 'Names fit the database columns they are stored in, in characters: option names in option_name, 191; transient names, which are stored with the prefix _transient_timeout_, 172, and site transient names, with _site_transient_timeout_, 167; and meta keys in meta_key, 255. MySQL would quietly cut a longer name short.' )
		->before_each(
			array( 'add_option', 'update_option', 'set_transient', 'set_site_transient', 'add_metadata', 'update_metadata' ),
			static function ( WP_Invariant_Call $call ) {
				$limits = array(
					'add_option'         => array( 'option', 191 ),
					'update_option'      => array( 'option', 191 ),
					'set_transient'      => array( 'transient', 172 ),
					'set_site_transient' => array( 'transient', 167 ),
					'add_metadata'       => array( 'meta_key', 255 ),
					'update_metadata'    => array( 'meta_key', 255 ),
				);

				list( $parameter, $limit ) = $limits[ $call->target() ];
				$name                      = $call->arg( $parameter );
				if ( ! is_string( $name ) ) {
					return;
				}

				// Options are trimmed, and meta keys unslashed, before they are stored.
				$name   = 'meta_key' === $parameter ? stripslashes( $name ) : trim( $name );
				$length = mb_strlen( $name, 'UTF-8' );
				$call->expect( $length <= $limit, '%s() was given a name of %d characters, which is more than %d: %s', $call->target(), $length, $limit, $name );
			}
		),
);
