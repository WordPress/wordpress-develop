<?php
/**
 * Invariants of the HTML WordPress prints, checked against what the request sent.
 *
 * These hook ob_get_clean() and ob_get_contents(), which tests, get_echo() and
 * PHPUnit use to collect what WordPress printed. Leave them out with
 * WP_INVARIANTS_SKIP=output.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/*
 * Reports each request value which holds HTML syntax where it appears in some
 * HTML: a tag, comment, attribute or attribute value starts inside it, or markup
 * other than an attribute value starts with it.
 */
$check = static function ( WP_Invariant_Call $call, array &$reported, $html, array $values ) {
	$boundaries = null;
	foreach ( $values as $value ) {
		if ( isset( $reported[ $value ] ) ) {
			continue;
		}

		$length = strlen( $value );
		foreach ( WP_Invariant_Request::occurrences( $html, $value ) as $at ) {
			if ( null === $boundaries ) {
				$boundaries = WP_Invariant_HTML_API::boundaries( $html );
				if ( null === $boundaries ) {
					return;
				}
			}

			foreach ( $boundaries as $offset => $kind ) {
				if ( $offset < $at || $offset >= $at + $length ) {
					continue;
				}
				// A value may be text, or fill an attribute value; it may not start markup of its own.
				if ( $offset === $at && 'attribute value' === $kind ) {
					continue;
				}

				$reported[ $value ] = true;
				$call->expect(
					false,
					'The request value %s appears in printed HTML, where a %s starts %s it: %s',
					$value,
					$kind,
					$offset === $at ? 'with' : 'inside',
					substr( $html, max( 0, $at - 40 ), $length + 80 )
				);
				continue 3;
			}
		}
	}
};

return array(
	WP_Invariant::define( 'request-values-stay-text' )
		->describe( 'A value the request sent, where it appears in HTML WordPress printed, is text or a whole attribute value: as the HTML API reads the whole output, no tag, comment, attribute or attribute value starts inside it, and it starts no tag, comment or attribute. The HTML in the strings of a JSON response counts too. Requests from users with unfiltered_html, who may send HTML, are left out. Values shorter than 4 bytes, or without <, >, quotes, = or whitespace, are not followed. Each value is reported once.' )
		->include_test_callers()
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->after_each(
			array( 'ob_get_clean', 'ob_get_contents' ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $check ) {
				$output = $call->return_value();
				if ( ! is_string( $output ) || false === strpos( $output, '<' ) ) {
					return;
				}

				// A user with unfiltered_html may send HTML, and see it printed.
				if ( function_exists( 'current_user_can' ) && did_action( 'set_current_user' ) && current_user_can( 'unfiltered_html' ) ) {
					return;
				}

				$values = WP_Invariant_Request::values( "<>\"'= \t\n\f\r" );
				if ( array() === $values ) {
					return;
				}

				// A JSON response, as AJAX handlers send, holds its HTML in strings.
				$documents = array( $output );
				$first     = substr( ltrim( $output ), 0, 1 );
				if ( '{' === $first || '[' === $first ) {
					$decoded = json_decode( $output, true );
					if ( null !== $decoded ) {
						$documents = array_filter(
							WP_Invariant::strings_in( $decoded ),
							static function ( $text ) {
								return false !== strpos( $text, '<' );
							}
						);
					}
				}

				foreach ( $documents as $html ) {
					$check( $call, $reported, $html, $values );
				}
			}
		),
);
