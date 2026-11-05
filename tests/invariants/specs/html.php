<?php
/**
 * Invariants of the HTML API, checked against how a browser parses HTML.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'normalize-agrees-with-browser' )
		->describe( 'When WP_HTML_Processor::normalize() returns HTML, a browser builds the same tree from it as from the original HTML, both parsed as the contents of a BODY element.' )
		->include_test_callers()
		->after(
			'WP_HTML_Processor::normalize',
			static function ( WP_Invariant_Call $call ) {
				$normalized = $call->return_value();
				if ( ! is_string( $normalized ) ) {
					return;
				}

				$html     = $call->arg( 'html' );
				$expected = WP_Invariant_Browser::tree( $html );
				$actual   = WP_Invariant_Browser::tree( $normalized );
				$call->expect( $expected === $actual, 'WP_HTML_Processor::normalize( %s ) returned %s, which a browser reads as %s instead of %s.', $html, $normalized, $actual, $expected );
			}
		),

	WP_Invariant::define( 'get-attribute-agrees-with-browser' )
		->describe( 'What WP_HTML_Tag_Processor::get_attribute() returns for a start tag in the document is what a browser reads from that tag: the value of a string, an empty value for true, and no attribute for null.' )
		->include_test_callers()
		->after(
			'WP_HTML_Tag_Processor::get_attribute',
			static function ( WP_Invariant_Call $call ) {
				$processor = $call->self();
				$name      = $call->arg( 'name' );
				$value     = $call->return_value();
				$class     = 'WP_HTML_Tag_Processor';

				// Pending changes are not in the document yet, and asking for `class` applies them.
				if (
					! is_string( $name ) || 'class' === strtolower( $name ) ||
					WP_HTML_Tag_Processor::STATE_MATCHED_TAG !== WP_Invariant::property( $processor, $class, 'parser_state' ) ||
					WP_Invariant::property( $processor, $class, 'is_closing_tag' ) ||
					array() !== WP_Invariant::property( $processor, $class, 'lexical_updates' ) ||
					array() !== WP_Invariant::property( $processor, $class, 'classname_updates' )
				) {
					return;
				}

				$tag     = substr( WP_Invariant::property( $processor, $class, 'html' ), WP_Invariant::property( $processor, $class, 'token_starts_at' ), WP_Invariant::property( $processor, $class, 'token_length' ) );
				$browser = WP_Invariant_Browser::start_tag( $tag );

				// The browser changes some elements into others, such as IMAGE into IMG, or ignores them, such as BODY.
				if ( null === $browser || ! preg_match( '~^<([^\s/>]+)~', $tag, $match ) || strtolower( $match[1] ) !== $browser['name'] ) {
					return;
				}

				$name     = strtolower( $name );
				$expected = isset( $browser['attributes'][ $name ] ) ? $browser['attributes'][ $name ] : null;
				$actual   = true === $value ? '' : $value;
				$call->expect( $actual === $expected, 'WP_HTML_Tag_Processor::get_attribute( %s ) returned %s for %s, which a browser reads as %s.', $name, $value, $tag, $expected );
			}
		),

	WP_Invariant::define( 'decoder-agrees-with-browser' )
		->describe( 'WP_HTML_Decoder decodes character references as a browser does: in text as in the contents of a TEXTAREA, and in attribute values as in a double-quoted attribute.' )
		->include_test_callers()
		->after(
			'WP_HTML_Decoder::decode_text_node',
			static function ( WP_Invariant_Call $call ) {
				$text = $call->arg( 'text' );

				// A browser changes line breaks and NUL, which the decoder leaves to the tag processor.
				if ( ! is_string( $text ) || false === strpos( $text, '&' ) || false !== strpbrk( $text, "\r\0" ) || false !== stripos( $text, '</textarea' ) ) {
					return;
				}

				// After a first character: a browser drops a leading LF in a TEXTAREA, even one from a character reference.
				$expected = WP_Invariant_Browser::textarea_value( "x{$text}" );
				$expected = null === $expected ? null : substr( $expected, 1 );
				$call->expect( $call->return_value() === $expected, 'WP_HTML_Decoder::decode_text_node( %s ) returned %s, which a browser reads as %s.', $text, $call->return_value(), $expected );
			}
		)
		->after(
			'WP_HTML_Decoder::decode_attribute',
			static function ( WP_Invariant_Call $call ) {
				$text = $call->arg( 'text' );
				if ( ! is_string( $text ) || false === strpos( $text, '&' ) || false !== strpbrk( $text, "\"\r\0" ) ) {
					return;
				}

				$tag      = WP_Invariant_Browser::start_tag( '<a title="' . $text . '">' );
				$expected = null === $tag ? null : $tag['attributes']['title'];
				$call->expect( $call->return_value() === $expected, 'WP_HTML_Decoder::decode_attribute( %s ) returned %s, which a browser reads as %s.', $text, $call->return_value(), $expected );
			}
		),
);
