<?php
/**
 * Invariants of file names.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

return array(
	WP_Invariant::define( 'upload-names-safe' )
		->describe( 'What sanitize_file_name() and wp_unique_filename() return is a name, not a path: it has no slash, backslash or NUL, and is not . or ..' )
		->include_test_callers()
		->after_each(
			array( 'sanitize_file_name', 'wp_unique_filename' ),
			static function ( WP_Invariant_Call $call ) {
				$name = $call->return_value();
				if ( is_string( $name ) ) {
					$call->expect( false === strpbrk( $name, "/\\\0" ) && '.' !== $name && '..' !== $name, '%s( %s ) returned %s.', $call->target(), $call->arg( 'filename' ), $name );
				}
			}
		),
);
