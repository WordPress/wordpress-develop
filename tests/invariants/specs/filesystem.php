<?php
/**
 * Invariants of the paths WordPress gives the file system.
 *
 * These hook built-in PHP functions, which slows down every call to a built-in
 * function. Leave them out with WP_INVARIANTS_SKIP=filesystem.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

// Built-in functions which read, write or look at a path, and which of their arguments are paths.
$functions = array(
	'file_exists'        => array( 0 ),
	'is_file'            => array( 0 ),
	'is_dir'             => array( 0 ),
	'is_link'            => array( 0 ),
	'is_readable'        => array( 0 ),
	'is_writable'        => array( 0 ),
	'is_writeable'       => array( 0 ),
	'filesize'           => array( 0 ),
	'filemtime'          => array( 0 ),
	'fileperms'          => array( 0 ),
	'stat'               => array( 0 ),
	'fopen'              => array( 0 ),
	'file_get_contents'  => array( 0 ),
	'file_put_contents'  => array( 0 ),
	'file'               => array( 0 ),
	'readfile'           => array( 0 ),
	'md5_file'           => array( 0 ),
	'sha1_file'          => array( 0 ),
	'hash_file'          => array( 1 ),
	'getimagesize'       => array( 0 ),
	'unlink'             => array( 0 ),
	'mkdir'              => array( 0 ),
	'rmdir'              => array( 0 ),
	'touch'              => array( 0 ),
	'chmod'              => array( 0 ),
	'opendir'            => array( 0 ),
	'scandir'            => array( 0 ),
	'glob'               => array( 0 ),
	'tempnam'            => array( 0 ),
	'copy'               => array( 0, 1 ),
	'rename'             => array( 0, 1 ),
	'move_uploaded_file' => array( 0, 1 ),
);

/*
 * The directories WordPress runs in, as the configuration names them, each as it
 * resolves and as its real path, ending in a slash. Those are where WordPress is
 * installed, its content, plugin, language and theme directories, temporary
 * directories, and the test suite's own files, which tests give to WordPress.
 */
$roots = static function () {
	static $known = array();

	$directories = array( sys_get_temp_dir(), '/tmp', (string) ini_get( 'upload_tmp_dir' ), dirname( __DIR__, 2 ) );
	foreach ( array( 'ABSPATH', 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR', 'WP_LANG_DIR', 'WP_TEMP_DIR' ) as $constant ) {
		if ( defined( $constant ) ) {
			$directories[] = (string) constant( $constant );
		}
	}
	if ( ! empty( $GLOBALS['wp_theme_directories'] ) ) {
		$directories = array_merge( $directories, array_filter( (array) $GLOBALS['wp_theme_directories'], 'is_string' ) );
	}

	$key = implode( "\0", $directories );
	if ( ! isset( $known[ $key ] ) ) {
		$roots = array();
		foreach ( array_filter( $directories ) as $directory ) {
			$roots = array_merge( $roots, WP_Invariant::directory_forms( $directory ) );
		}
		$known = array( $key => array_values( array_unique( $roots ) ) );
	}

	return $known[ $key ];
};

// Devices PHP code reads for random bytes, or writes to throw output away.
$devices = array(
	'/dev/null'    => true,
	'/dev/random'  => true,
	'/dev/urandom' => true,
);

return array(
	WP_Invariant::define( 'paths-stay-inside' )
		->describe( 'WordPress gives the file system no path outside the directories it runs in: where it is installed, its content, plugin, language and theme directories, and temporary directories, apart from /dev/null and the random devices. A .. in a path never takes it out of them. Each place in WordPress is reported once for each path.' )
		->state(
			static function () {
				return array();
			},
			'run'
		)
		->before_each(
			array_keys( $functions ),
			static function ( WP_Invariant_Call $call, array &$reported ) use ( $functions, $roots, $devices ) {
				$args = $call->args();
				foreach ( $functions[ $call->target() ] as $position ) {
					$path = isset( $args[ $position ] ) ? $args[ $position ] : null;
					if ( ! is_string( $path ) || '' === $path || false !== strpos( $path, "\0" ) ) {
						continue;
					}

					// Other stream wrappers, such as php://memory, are not paths in a directory.
					$scheme = strpos( $path, '://' );
					if ( false !== $scheme && 0 === strncasecmp( $path, 'file://', 7 ) ) {
						$path = substr( $path, 7 );
					} elseif ( false !== $scheme ) {
						continue;
					}

					$resolved = WP_Invariant::resolve_path( $path );
					if ( isset( $devices[ $resolved ] ) || WP_Invariant::path_inside( $resolved, $roots() ) ) {
						continue;
					}

					// A symbolic link, such as /tmp on macOS, can lead inside.
					$real = realpath( $resolved );
					$real = false === $real ? realpath( dirname( $resolved ) ) : $real;
					if ( false !== $real && WP_Invariant::path_inside( $real, $roots() ) ) {
						continue;
					}

					$caller = $call->caller();
					$site   = $caller['file'] . ':' . $caller['line'] . ' ' . $resolved;
					if ( isset( $reported[ $site ] ) || ! WP_Invariant::is_core_file( $caller['file'] ) ) {
						continue;
					}

					$reported[ $site ] = true;
					$stack             = $call->stack( 1 );
					$call->expect(
						false,
						'%s() in %s was given %s%s, outside the directories WordPress runs in.',
						$call->target(),
						$stack ? $stack[0] : $caller['file'] . ':' . $caller['line'],
						$path,
						$resolved === $path ? '' : ", which is {$resolved}"
					);
				}
			}
		),
);
