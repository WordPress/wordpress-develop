<?php
/**
 * Invariants of where extracting and copying directories writes.
 *
 * These hook built-in PHP functions, which slows down every call to a built-in
 * function. Leave them out with WP_INVARIANTS_SKIP=archives.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// The extractions and copies running in the test, innermost last.
$destinations = WP_Invariant::shared( 'archives/destinations' );

// Functions which write a path: the positions of the paths written.
$writes = array(
	'mkdir'                              => array( 0 ),
	'file_put_contents'                  => array( 0 ),
	'fopen'                              => array( 0 ),
	'touch'                              => array( 0 ),
	'copy'                               => array( 1 ),
	'rename'                             => array( 1 ),
	'ZipArchive::extractTo'              => array( 0 ),
	'WP_Filesystem_Direct::put_contents' => array( 0 ),
	'WP_Filesystem_Direct::mkdir'        => array( 0 ),
	'WP_Filesystem_Direct::copy'         => array( 1 ),
	'WP_Filesystem_Direct::move'         => array( 1 ),
);

return array(
	WP_Invariant::define( 'extract-inside-destination' )
		->describe( 'While unzip_file() or copy_dir() runs, every file and directory it writes is inside the directory it was given to write to, with .. and symbolic links resolved: no archive entry such as ../../wp-config.php, or absolute name, takes a write outside it. Each place in WordPress is reported once for each path.' )
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->before_each(
			array( 'unzip_file', 'copy_dir' ),
			static function ( WP_Invariant_Call $call ) use ( $destinations ) {
				$to = $call->arg( 'to' );
				if ( ! is_string( $to ) || '' === $to ) {
					return;
				}
				if ( ! isset( $destinations->stack ) ) {
					$destinations->stack = array();
				}
				$destinations->stack[] = array(
					'function'    => $call->target(),
					'mark'        => WP_Invariant::frame_mark( $call->target() ),
					'to'          => $to,
					'directories' => WP_Invariant::directory_forms( $to ),
				);
			}
		)
		->before_each(
			array_keys( $writes ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $destinations, $writes ) {
				if ( empty( $destinations->stack ) ) {
					return;
				}

				// The innermost extraction or copy which is still running.
				$destination = null;
				while ( array() !== $destinations->stack ) {
					$destination = end( $destinations->stack );
					if ( WP_Invariant::running( $destination['mark'], $destination['function'] ) ) {
						break;
					}
					array_pop( $destinations->stack );
					$destination = null;
				}
				if ( null === $destination ) {
					return;
				}

				$args = $call->args();
				if ( 'fopen' === $call->target() && ! WP_Invariant_Security::is_write_mode( isset( $args[1] ) ? (string) $args[1] : 'r' ) ) {
					return;
				}

				foreach ( $writes[ $call->target() ] as $position ) {
					$path = isset( $args[ $position ] ) ? $args[ $position ] : null;
					if ( ! is_string( $path ) || '' === $path || false !== strpos( $path, "\0" ) || false !== strpos( $path, '://' ) ) {
						continue;
					}
					if ( WP_Invariant::path_resolves_inside( $path, $destination['directories'] ) ) {
						continue;
					}

					$caller = $call->caller();
					$site   = $caller['file'] . ':' . $caller['line'] . ' ' . $path;
					if ( isset( $reported[ $site ] ) ) {
						continue;
					}
					$reported[ $site ] = true;

					$call->expect( false, '%s() wrote %s, which is outside %s, while %s() ran.', $call->target(), $path, $destination['to'], $destination['function'] );
				}
			}
		),
);
