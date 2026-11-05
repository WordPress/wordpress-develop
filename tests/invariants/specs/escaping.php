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
);
