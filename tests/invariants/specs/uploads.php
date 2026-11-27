<?php
/**
 * Invariants of the files WordPress writes into the uploads directory.
 *
 * These hook built-in PHP functions, which slows down every call to a built-in
 * function. Leave them out with WP_INVARIANTS_SKIP=uploads.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// Functions which write a file: the position of the path written, and of the contents, if they are given.
$functions = array(
	'move_uploaded_file'                 => array( 1, null ),
	'copy'                               => array( 1, null ),
	'rename'                             => array( 1, null ),
	'file_put_contents'                  => array( 0, 1 ),
	'fopen'                              => array( 0, null ),
	'WP_Filesystem_Direct::put_contents' => array( 0, 1 ),
	'WP_Filesystem_Direct::copy'         => array( 1, null ),
	'WP_Filesystem_Direct::move'         => array( 1, null ),
);

return array(
	WP_Invariant::define( 'nothing-executable' )
		->describe( 'Nothing WordPress writes into the uploads directory could run as code on a web server: no file has a PHP, PHAR, CGI, Perl or server-side include extension among its extensions, as in photo.php.jpg, none is a .htaccess, .user.ini or web.config file, and no contents written start with <?php or <?=, or hold __HALT_COMPILER. Each place in WordPress is reported once for each file.' )
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->before_each(
			array_keys( $functions ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $functions ) {
				$args = $call->args();
				list( $path_at, $contents_at ) = $functions[ $call->target() ];

				$path = isset( $args[ $path_at ] ) ? $args[ $path_at ] : null;
				if ( ! is_string( $path ) || '' === $path || false !== strpos( $path, "\0" ) || false !== strpos( $path, '://' ) ) {
					return;
				}
				if ( 'fopen' === $call->target() && ! WP_Invariant_Security::is_write_mode( isset( $args[1] ) ? (string) $args[1] : 'r' ) ) {
					return;
				}

				$why = WP_Invariant_Security::executable_name( $path );
				if ( null === $why && null !== $contents_at && isset( $args[ $contents_at ] ) && is_string( $args[ $contents_at ] ) ) {
					$why = WP_Invariant_Security::executable_contents( $args[ $contents_at ] );
				}
				if ( null === $why || ! function_exists( 'wp_upload_dir' ) ) {
					return;
				}

				$uploads = wp_upload_dir( null, false );
				if ( empty( $uploads['basedir'] ) || ! WP_Invariant::path_resolves_inside( $path, WP_Invariant::directory_forms( $uploads['basedir'] ) ) ) {
					return;
				}

				$caller = $call->caller();
				$site   = $caller['file'] . ':' . $caller['line'] . ' ' . $path;
				if ( isset( $reported[ $site ] ) ) {
					return;
				}
				$reported[ $site ] = true;

				$call->expect( false, '%s() wrote %s into the uploads directory, which could run as code: %s.', $call->target(), $path, $why );
			}
		),
);
