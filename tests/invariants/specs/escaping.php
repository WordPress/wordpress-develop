<?php
/**
 * Invariants of the escaping functions, checked against how browsers and XML parsers read their output.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'esc-url-allowed-protocol' )
		->describe( 'Read as a browser reads an href, what esc_url() returns stays in its attribute, and is relative, unparseable, or has a scheme in its allowed protocols.' )
		->include_test_callers()
		->after(
			'esc_url',
			static function ( WP_Invariant_Call $call ) {
				$url = $call->return_value();
				if ( ! is_string( $url ) || '' === $url || ( false === strpos( $url, ':' ) && false === strpos( $url, '&' ) ) ) {
					return;
				}

				// In the display context the URL is written into HTML, and a browser decodes it first.
				if ( 'display' === $call->arg( '_context' ) ) {
					$url = WP_Invariant_Browser::attribute_value( $url );
					if ( ! $call->expect( null !== $url, 'esc_url() returned %s, which breaks out of an attribute.', $call->return_value() ) ) {
						return;
					}
				}

				$scheme = WP_Invariant_Browser::url_scheme( $url );
				if ( null === $scheme ) {
					return;
				}

				$protocols = $call->arg( 'protocols' );
				if ( empty( $protocols ) ) {
					$protocols = wp_allowed_protocols();
				}
				$call->expect( in_array( $scheme, array_map( 'strtolower', $protocols ), true ), 'esc_url( %s ) returned a %s URL: %s', $call->arg( 'url' ), $scheme, $call->return_value() );
			}
		),

	WP_Invariant::define( 'esc-attr-safe' )
		->describe( 'What esc_attr() returns cannot break out of an attribute value, in double or single quotes, as a browser reads it.' )
		->include_test_callers()
		->after(
			'esc_attr',
			static function ( WP_Invariant_Call $call ) {
				$text = $call->return_value();
				if ( ! is_string( $text ) || false === strpbrk( $text, "\"'<>" ) ) {
					return;
				}

				foreach ( array( '<div title="' . $text . '"></div>', "<div title='" . $text . "'></div>" ) as $markup ) {
					$elements = WP_Invariant_Browser::elements( $markup );
					if ( ! $call->expect( 1 === count( $elements ) && 1 === count( $elements[0]->attributes ), 'esc_attr( %s ) returned %s, which breaks out of an attribute.', $call->arg( 'text' ), $text ) ) {
						return;
					}
				}
			}
		),

	WP_Invariant::define( 'esc-xml-well-formed' )
		->describe( 'What esc_xml() returns is well-formed XML text: inside an element, it is parsed without error and adds no elements.' )
		->include_test_callers()
		->after(
			'esc_xml',
			static function ( WP_Invariant_Call $call ) {
				$text = $call->return_value();
				if ( ! is_string( $text ) || false === strpbrk( $text, '<&' ) ) {
					return;
				}

				$children = WP_Invariant_Browser::xml_child_elements( $text );
				$call->expect( 0 === $children, 'esc_xml( %s ) returned %s, which is %s.', $call->arg( 'text' ), $text, null === $children ? 'not well-formed' : 'markup' );
			}
		),

	WP_Invariant::define( 'esc-js-stays-in-string' )
		->describe( 'What esc_js() returns stays in a single-quoted JavaScript string inside an inline event handler, as a browser reads it: the attribute stays one attribute, and the string has no unescaped quote or line break.' )
		->include_test_callers()
		->after(
			'esc_js',
			static function ( WP_Invariant_Call $call ) {
				$escaped = $call->return_value();
				if ( ! is_string( $escaped ) || '' === $escaped ) {
					return;
				}

				$tag = WP_Invariant_Browser::start_tag( '<a onclick="f(\'' . $escaped . '\')">' );
				if ( ! $call->expect( null !== $tag && array( 'onclick' ) === array_keys( $tag['attributes'] ), 'esc_js( %s ) returned %s, which breaks out of an attribute.', $call->arg( 'text' ), $escaped ) ) {
					return;
				}

				// What the script sees between the quotes, with its escapes taken out.
				$script = $tag['attributes']['onclick'];
				$string = preg_replace( '/\\\\./s', '', substr( $script, 3, -2 ) );
				$call->expect(
					"f('" === substr( $script, 0, 3 ) && "')" === substr( $script, -2 ) && false === strpbrk( $string, "'\r\n" ),
					'esc_js( %s ) returned %s, which a browser runs as %s.',
					$call->arg( 'text' ),
					$escaped,
					$script
				);
			}
		),

	WP_Invariant::define( 'esc-textarea-reads-back' )
		->describe( 'What esc_textarea() returns, inside a TEXTAREA, stays one TEXTAREA, which a browser gives the original text as its value, apart from a leading line break, which browsers drop.' )
		->include_test_callers()
		->after(
			'esc_textarea',
			static function ( WP_Invariant_Call $call ) {
				$escaped = $call->return_value();
				$text    = $call->arg( 'text' );
				if ( ! is_string( $escaped ) || ! is_scalar( $text ) ) {
					return;
				}

				$text = (string) $text;
				if ( '' === $escaped && '' !== $text ) {
					$call->expect( false, 'esc_textarea() returned an empty string for %d bytes of text, which are %s.', strlen( $text ), mb_check_encoding( $text, 'UTF-8' ) ? 'valid UTF-8' : 'not valid UTF-8' );
					return;
				}
				if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
					// In another character set, which the browser here does not read.
					return;
				}

				// As a browser reads text: line breaks become LF, and NUL the replacement character.
				$expected = str_replace( array( "\r\n", "\r", "\0" ), array( "\n", "\n", "\u{FFFD}" ), $text );
				if ( "\n" === substr( $expected, 0, 1 ) ) {
					$expected = substr( $expected, 1 );
				}
				$value = WP_Invariant_Browser::textarea_value( $escaped );
				$call->expect( $value === $expected, 'esc_textarea( %s ) returned %s, which a browser reads as %s.', $text, $escaped, $value );
			}
		),
);
