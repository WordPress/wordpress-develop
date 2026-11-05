<?php
/**
 * Invariants of wp_kses(), checked against how a browser reads its output.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// What wp_kses_allowed_html() and wp_kses_uri_attributes() last returned, as kses read them.
$kses = WP_Invariant::shared( 'kses/settings' );

// Elements a browser adds by itself, such as the TBODY around table rows.
$implied = array(
	'html'     => true,
	'head'     => true,
	'body'     => true,
	'tbody'    => true,
	'colgroup' => true,
);

return array(
	WP_Invariant::define( 'allowed-markup-only' )
		->describe( 'Read as a browser reads it, what wp_kses() returns has only elements its allowed HTML allows, no event handler attributes unless allowed, and no URL with a scheme outside its allowed protocols.' )
		->include_test_callers()
		->after(
			'wp_kses_allowed_html',
			static function ( WP_Invariant_Call $call ) use ( $kses ) {
				$context = $call->arg( 'context' );
				if ( is_string( $context ) ) {
					$kses->allowed[ $context ] = $call->return_value();
				}
			}
		)
		->after(
			'wp_kses_uri_attributes',
			static function ( WP_Invariant_Call $call ) use ( $kses ) {
				$kses->uri_attributes = array_fill_keys( array_map( 'strtolower', (array) $call->return_value() ), true );
			}
		)
		->after(
			'wp_kses',
			static function ( WP_Invariant_Call $call ) use ( $kses, $implied ) {
				$output = $call->return_value();
				if ( ! is_string( $output ) || false === strpos( $output, '<' ) ) {
					return;
				}

				// A context name is looked up as kses looked it up, without running its filter again.
				$allowed = $call->arg( 'allowed_html' );
				if ( is_string( $allowed ) ) {
					$allowed = isset( $kses->allowed[ $allowed ] ) ? $kses->allowed[ $allowed ] : null;
				}
				if ( is_array( $allowed ) ) {
					$allowed = array_change_key_case( $allowed, CASE_LOWER );
				}

				$protocols = $call->arg( 'allowed_protocols' );
				if ( empty( $protocols ) ) {
					$protocols = wp_allowed_protocols();
				}
				$protocols = array_map( 'strtolower', $protocols );

				foreach ( WP_Invariant_Browser::elements( $output ) as $element ) {
					$tag = WP_Invariant_Browser::tag_name( $element );
					if ( isset( $implied[ $tag ] ) ) {
						continue;
					}

					if ( is_array( $allowed ) && ! $call->expect( isset( $allowed[ $tag ] ), 'wp_kses() let through a %s element: %s', $tag, $output ) ) {
						return;
					}

					$allowed_attributes = is_array( $allowed ) && is_array( $allowed[ $tag ] ) ? array_change_key_case( $allowed[ $tag ], CASE_LOWER ) : array();
					foreach ( $element->attributes as $attribute ) {
						$name = strtolower( $attribute->name );
						if ( 0 === strpos( $name, 'on' ) && empty( $allowed_attributes[ $name ] ) ) {
							$call->expect( false, 'wp_kses() let through the event handler %s on a %s element: %s', $name, $tag, $output );
							return;
						}

						if ( isset( $kses->uri_attributes[ $name ] ) ) {
							$scheme = WP_Invariant_Browser::url_scheme( $attribute->value );
							if ( null !== $scheme && ! $call->expect( in_array( $scheme, $protocols, true ), 'wp_kses() let through a %s URL in %s: %s', $scheme, $name, $output ) ) {
								return;
							}
						}
					}
				}
			}
		),

	WP_Invariant::define( 'idempotent-when-reapplied' )
		->describe( 'Given something it returned earlier in the test, wp_kses() returns it unchanged, when its allowed HTML and protocols are the same.' )
		->include_test_callers()
		->state(
			static function () {
				return array();
			},
			'test'
		)
		->after(
			'wp_kses',
			static function ( WP_Invariant_Call $call, array &$outputs ) {
				$input  = $call->arg( 'content' );
				$output = $call->return_value();
				if ( ! is_string( $input ) || ! is_string( $output ) ) {
					return;
				}

				$allowed = $call->arg( 'allowed_html' );
				$key     = md5( serialize( array( is_string( $allowed ) ? $allowed : (array) $allowed, $call->arg( 'allowed_protocols' ) ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Only to compare arguments.
				if ( isset( $outputs[ $key ][ $input ] ) ) {
					$call->expect( $output === $input, 'wp_kses() changed what it had returned before, %s, to %s.', $input, $output );
				}

				unset( $outputs[ $key ][ $output ] );
				$outputs[ $key ][ $output ] = true;
				if ( count( $outputs[ $key ] ) > 256 ) {
					unset( $outputs[ $key ][ array_key_first( $outputs[ $key ] ) ] );
				}
			}
		),
);
