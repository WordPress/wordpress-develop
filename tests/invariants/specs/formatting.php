<?php
/**
 * Invariants of formatting functions, some checked against how a browser reads their output.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

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
);
