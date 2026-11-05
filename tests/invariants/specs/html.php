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
);
