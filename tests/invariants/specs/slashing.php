<?php
/**
 * Invariants of slashed and unslashed data.
 *
 * For historical reasons, functions such as wp_insert_post() and add_metadata()
 * expect slashed input and remove one level of slashes from it, while functions
 * such as update_option() store their input as given. Passing the wrong kind
 * loses backslashes, or stores extra ones.
 *
 * These invariants track recent results: the strings wp_unslash() and wp_slash()
 * just returned which contain a backslash, the only strings which a wrong level
 * of slashing changes. They are kept in shared tables, the latest 512 of each
 * in each test.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

$unslashed = WP_Invariant::shared( 'slashing/unslashed' );
$slashed   = WP_Invariant::shared( 'slashing/slashed' );

// Remembers the strings in a value which contain a backslash.
$remember = static function ( stdClass $table, $value ) {
	foreach ( WP_Invariant::strings_in( $value ) as $string ) {
		if ( false === strpos( $string, '\\' ) ) {
			continue;
		}

		if ( ! isset( $table->strings ) ) {
			$table->strings = array();
		}
		unset( $table->strings[ $string ] );
		$table->strings[ $string ] = true;
		if ( count( $table->strings ) > 512 ) {
			unset( $table->strings[ array_key_first( $table->strings ) ] );
		}
	}
};

// Returns the first string in a call's arguments which is in a table, or null.
$find = static function ( stdClass $table, WP_Invariant_Call $call ) {
	if ( empty( $table->strings ) ) {
		return null;
	}

	foreach ( $call->args() as $arg ) {
		foreach ( WP_Invariant::strings_in( $arg ) as $string ) {
			if ( isset( $table->strings[ $string ] ) ) {
				return $string;
			}
		}
	}

	return null;
};

return array(
	WP_Invariant::define( 'slashed-input-not-unslashed' )
		->describe( 'A function which removes slashes from its input never receives a string which wp_unslash() just returned with a backslash in it: removing slashes again would lose that backslash.' )
		->after(
			'wp_unslash',
			static function ( WP_Invariant_Call $call ) use ( $unslashed, $remember ) {
				$remember( $unslashed, $call->return_value() );
			}
		)
		->before_each(
			array( 'wp_insert_post', 'wp_update_post', 'wp_insert_comment', 'wp_update_comment', 'wp_insert_term', 'wp_update_term', 'add_metadata', 'update_metadata' ),
			static function ( WP_Invariant_Call $call ) use ( $unslashed, $find ) {
				$string = $find( $unslashed, $call );
				$call->expect( null === $string, '%s() expects slashed data, but received %s, which wp_unslash() had just returned.', $call->target(), $string );
			}
		),

	WP_Invariant::define( 'unslashed-input-not-slashed' )
		->describe( 'A function which stores its input as given never receives a string which wp_slash() just returned with added backslashes: they would be stored too.' )
		->after(
			'wp_slash',
			static function ( WP_Invariant_Call $call ) use ( $slashed, $remember ) {
				$remember( $slashed, $call->return_value() );
			}
		)
		->before_each(
			array( 'update_option', 'add_option', 'set_transient', 'update_network_option', 'add_network_option' ),
			static function ( WP_Invariant_Call $call ) use ( $slashed, $find ) {
				$string = $find( $slashed, $call );
				$call->expect( null === $string, '%s() stores data as given, but received %s, which wp_slash() had just returned.', $call->target(), $string );
			}
		),
);
