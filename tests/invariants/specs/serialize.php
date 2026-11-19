<?php
/**
 * Invariants of the serialized data WordPress unserializes.
 *
 * These hook serialize() and unserialize(), built-in PHP functions, which slows
 * down every call to a built-in function. Leave them out with
 * WP_INVARIANTS_SKIP=serialize.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// What serialize() returned in this run with objects in it, by MD5 hash.
$produced = WP_Invariant::shared( 'serialize/produced', 'run' );

// Most hashes kept; past that, the oldest are forgotten.
$most_produced = 100000;

// Whether data is a value the request sent, as given or with slashes removed.
$from_request = static function ( $data ) {
	if ( ! is_string( $data ) || strlen( $data ) < WP_Invariant_Request::MIN_LENGTH ) {
		return false;
	}

	$sent = array_flip( WP_Invariant_Request::values( ':' ) );
	return isset( $sent[ $data ] );
};

return array(
	WP_Invariant::define( 'no-foreign-objects' )
		->describe( 'unserialize() is never given serialized objects of a loaded class with __wakeup(), __unserialize(), __destruct(), __toString(), __call() or __get(), through which it could run code, unless serialize() made that exact data in this run or allowed_classes leaves the class out. Neither unserialize() nor maybe_unserialize() is ever given a value the request sent. Each place in WordPress is reported once for each class.' )
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->after(
			'serialize',
			static function ( WP_Invariant_Call $call ) use ( $produced, $most_produced ) {
				$data = $call->return_value();
				if ( is_string( $data ) && ( false !== strpos( $data, 'O:' ) || false !== strpos( $data, 'C:' ) ) ) {
					if ( ! isset( $produced->hashes ) || count( $produced->hashes ) >= $most_produced ) {
						$produced->hashes = array();
					}
					$produced->hashes[ md5( $data ) ] = true;
				}
			}
		)
		->before(
			'unserialize',
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $produced, $from_request ) {
				$data = $call->arg( 'data' );
				if ( ! is_string( $data ) ) {
					return;
				}

				$caller = $call->caller();
				$site   = $caller['file'] . ':' . $caller['line'];
				if ( $from_request( $data ) && ! isset( $reported[ "{$site} request" ] ) ) {
					$reported[ "{$site} request" ] = true;
					$call->expect( false, 'unserialize() was given a value the request sent: %s', $data );
				}

				$options = $call->arg( 'options' );
				$allowed = is_array( $options ) && array_key_exists( 'allowed_classes', $options ) ? $options['allowed_classes'] : true;
				$gadgets = WP_Invariant_Security::gadget_classes( $data, $allowed );
				if ( array() === $gadgets || isset( $produced->hashes[ md5( $data ) ] ) ) {
					return;
				}

				foreach ( $gadgets as $class ) {
					if ( isset( $reported[ "{$site} {$class}" ] ) ) {
						continue;
					}
					$reported[ "{$site} {$class}" ] = true;
					$call->expect( false, 'unserialize() was given an object of %s, which this run did not serialize: %s', $class, $data );
				}
			}
		)
		->before(
			'maybe_unserialize',
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $from_request ) {
				$data   = $call->arg( 'data' );
				$caller = $call->caller();
				$site   = $caller['file'] . ':' . $caller['line'];
				if ( $from_request( $data ) && is_serialized( $data ) && ! isset( $reported[ "{$site} request" ] ) ) {
					$reported[ "{$site} request" ] = true;
					$call->expect( false, 'maybe_unserialize() was given a value the request sent: %s', $data );
				}
			}
		),
);
