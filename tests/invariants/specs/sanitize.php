<?php
/**
 * Invariants of the sanitizing functions: sanitizing twice changes nothing, and what they return fits what they promise.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/*
 * Sanitizing functions, with the filter each one runs, the callbacks WordPress
 * itself adds to it, and the characters what it returns may hold, if it says.
 */
$sanitizers = array(
	'sanitize_key'            => array( 'sanitize_key', array(), 'abcdefghijklmnopqrstuvwxyz0123456789_-' ),
	'sanitize_html_class'     => array( 'sanitize_html_class', array(), 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-' ),
	'sanitize_text_field'     => array( 'sanitize_text_field', array(), null ),
	'sanitize_textarea_field' => array( 'sanitize_textarea_field', array(), null ),
	'sanitize_title'          => array( 'sanitize_title', array( 'sanitize_title_with_dashes' ), null ),
	'sanitize_email'          => array( 'sanitize_email', array(), null ),
	'sanitize_user'           => array( 'sanitize_user', array(), null ),
	'sanitize_file_name'      => array( 'sanitize_file_name', array(), null ),
);

// The characters sanitize_user() keeps in strict mode.
$strict_user = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 _.-@';

// Whether a filter has only the callbacks WordPress adds to it, so that sanitizing again is up to WordPress alone.
$only_core_callbacks = static function ( $hook, array $core ) {
	if ( ! WP_Invariant::has_callbacks( $hook ) ) {
		return true;
	}
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( ! is_string( $callback['function'] ) || ! in_array( $callback['function'], $core, true ) ) {
				return false;
			}
		}
	}

	return true;
};

return array(
	WP_Invariant::define( 'idempotent' )
		->describe( 'Sanitizing what a sanitizing function returned changes nothing: sanitize_key(), sanitize_html_class(), sanitize_text_field(), sanitize_textarea_field(), sanitize_title(), sanitize_email(), sanitize_user() and sanitize_file_name() return what they are given when they are given what they returned, with the same other arguments. Otherwise a check made between two sanitizations can be passed with a value which changes after it. What sanitize_key(), sanitize_html_class() and sanitize_user() in strict mode return holds only the characters they keep. Calls are left out while a plugin or test filters the function\'s result.' )
		->include_test_callers()
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->after_each(
			array_keys( $sanitizers ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $sanitizers, $strict_user, $only_core_callbacks ) {
				$result = $call->return_value();
				if ( ! is_string( $result ) ) {
					return;
				}

				$function                        = $call->target();
				list( $hook, $core, $alphabet )  = $sanitizers[ $function ];
				if ( ! $only_core_callbacks( $hook, $core ) ) {
					return;
				}

				$args = $call->args();
				if ( 'sanitize_user' === $function && ! empty( $args[1] ) ) {
					$alphabet = $strict_user;
				}

				if ( null !== $alphabet && strspn( $result, $alphabet ) !== strlen( $result ) && ! isset( $reported[ "{$function} alphabet" ] ) ) {
					$reported[ "{$function} alphabet" ] = true;
					$call->expect( false, '%s( %s ) returned %s, with characters it does not keep.', $function, isset( $args[0] ) ? $args[0] : null, $result );
				}

				// Sanitizing again runs PCRE functions; leave preg_last_error() as the call left it.
				$pcre_error = preg_last_error();
				$args[0]    = $result;
				$again      = call_user_func_array( $function, $args );
				if ( PREG_NO_ERROR === $pcre_error && PREG_NO_ERROR !== preg_last_error() ) {
					preg_match( '/^/', '' );
				}

				if ( $again === $result || isset( $reported[ "{$function} " . md5( $result ) ] ) ) {
					return;
				}
				$reported[ "{$function} " . md5( $result ) ] = true;
				$call->expect( false, '%s() returned %s, which it sanitizes again to %s.', $function, $result, $again );
			}
		),
);
