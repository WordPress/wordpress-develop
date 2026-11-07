<?php
/**
 * Invariants of formatting functions, some checked against how a browser reads their output.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

/*
 * The registered shortcodes in a string, counted by kind: `[gallery` for an opening
 * shortcode and `[/gallery` for a closing one. An escaped shortcode, such as
 * `[[gallery]]`, is left out. It is found without PCRE, which would reset
 * preg_last_error() for the code under test.
 */
$shortcodes = static function ( $text ) {
	$counts = array();
	if ( empty( $GLOBALS['shortcode_tags'] ) || ! is_array( $GLOBALS['shortcode_tags'] ) ) {
		return $counts;
	}

	$name_characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
	for ( $at = strpos( $text, '[' ); false !== $at; $at = strpos( $text, '[', $at + 1 ) ) {
		if ( $at > 0 && '[' === $text[ $at - 1 ] ) {
			continue;
		}

		$closing = isset( $text[ $at + 1 ] ) && '/' === $text[ $at + 1 ];
		$starts  = $at + ( $closing ? 2 : 1 );
		$name    = substr( $text, $starts, strspn( $text, $name_characters, $starts ) );
		if ( '' !== $name && isset( $GLOBALS['shortcode_tags'][ $name ] ) ) {
			$kind            = ( $closing ? '[/' : '[' ) . $name;
			$counts[ $kind ] = ( isset( $counts[ $kind ] ) ? $counts[ $kind ] : 0 ) + 1;
		}
	}

	return $counts;
};

// The markup wpautop() adds, as it is meant to.
$paragraphs = array(
	'<p'  => true,
	'</p' => true,
	'<br' => true,
);

return array(
	WP_Invariant::define( 'esc-html-no-tags' )
		->describe( 'What esc_html() returns contains no < or >, so it cannot open or close a tag.' )
		->after(
			'esc_html',
			static function ( WP_Invariant_Call $call ) {
				$result = $call->return_value();
				$call->expect( false === strpbrk( (string) $result, '<>' ), 'esc_html( %s ) returned %s.', $call->arg( 'text' ), $result );
			}
		),

	WP_Invariant::define( 'strip-all-tags-has-no-elements' )
		->describe( 'A browser builds no elements from what wp_strip_all_tags() returns, read as the contents of a BODY element.' )
		->include_test_callers()
		->after(
			'wp_strip_all_tags',
			static function ( WP_Invariant_Call $call ) {
				$text = $call->return_value();
				if ( ! is_string( $text ) || false === strpos( $text, '<' ) ) {
					return;
				}

				$elements = WP_Invariant_Browser::elements( $text );
				$call->expect( array() === $elements, 'wp_strip_all_tags( %s ) returned %s, in which a browser finds the element %s.', $call->arg( 'text' ), $text, array() === $elements ? '' : WP_Invariant_Browser::tag_name( $elements[0] ) );
			}
		),

	WP_Invariant::define( 'no-new-markup' )
		->describe( 'wp_kses(), wpautop() and wptexturize() create no markup. As the HTML API reads them, what they return has no more of any start tag, end tag, comment or DOCTYPE, or of any registered shortcode, than what they were given, apart from the P and BR tags wpautop() adds.' )
		->include_test_callers()
		->after_each(
			array( 'wp_kses', 'wpautop', 'wptexturize' ),
			static function ( WP_Invariant_Call $call ) use ( $shortcodes, $paragraphs ) {
				$args   = $call->args();
				$input  = isset( $args[0] ) ? $args[0] : null;
				$output = $call->return_value();
				if ( ! is_string( $input ) || ! is_string( $output ) || $input === $output || false === strpbrk( $output, '<[' ) ) {
					return;
				}

				$before = WP_Invariant_HTML_API::markup( $input );
				$after  = WP_Invariant_HTML_API::markup( $output );
				if ( null === $before ) {
					return;
				}

				$before += $shortcodes( $input );
				$after  += $shortcodes( $output );
				foreach ( $after as $kind => $count ) {
					$had = isset( $before[ $kind ] ) ? $before[ $kind ] : 0;
					if ( $count > $had && ( 'wpautop' !== $call->target() || ! isset( $paragraphs[ $kind ] ) ) ) {
						$call->expect( false, '%s() created markup: it was given %d of %s and returned %d, from %s to %s', $call->target(), $had, $kind, $count, $input, $output );
						return;
					}
				}
			}
		),
);
