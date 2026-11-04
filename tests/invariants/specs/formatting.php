<?php
/**
 * Invariants of formatting functions.
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
);
