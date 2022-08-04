<?php

/**
 * Legacy plural form function.
 *
 * @param int $nplurals
 * @param string $expression
 */
function tests_make_plural_form_function( $nplurals, $expression ) {
	$closure = static function ( $n ) use ( $nplurals, $expression ) {
		$expression = str_replace( 'n', $n, $expression );

		// phpcs:ignore Squiz.PHP.Eval -- This is test code, not production.
		$index = (int) eval( 'return ' . $expression . ';' );

		return ( $index < $nplurals ) ? $index : $nplurals - 1;
	};

	return $closure;
}
