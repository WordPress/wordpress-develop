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

	WP_Invariant::define( 'validated-paths-stay-inside' )
		->describe( 'A path validate_file() accepts, by returning 0, stays inside the directory it is read from: no .. in it climbs above where it starts. Callers append it to a directory, such as WP_PLUGIN_DIR, so a path starting with a slash starts there too.' )
		->include_test_callers()
		->after(
			'validate_file',
			static function ( WP_Invariant_Call $call ) {
				$file = $call->arg( 'file' );
				if ( 0 !== $call->return_value() || ! is_scalar( $file ) ) {
					return;
				}

				// As validate_file() reads it, with backslashes as slashes. A stream wrapper, such as php://memory, is not in a directory.
				$path = str_replace( '\\', '/', (string) $file );
				if ( false !== strpos( $path, '://' ) ) {
					return;
				}

				$depth = 0;
				foreach ( explode( '/', $path ) as $segment ) {
					if ( '..' === $segment ) {
						--$depth;
					} elseif ( '' !== $segment && '.' !== $segment ) {
						++$depth;
					}
					if ( $depth < 0 ) {
						break;
					}
				}

				$call->expect( $depth >= 0, 'validate_file( %s ) accepted a path which climbs above the directory it is read from.', $file );
			}
		),
);
