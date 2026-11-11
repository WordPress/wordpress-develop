<?php
/**
 * The invariants file for the gameshark PHP extension.
 *
 * The extension loads it at the start of every PHP process when it runs with
 *
 *     GAMESHARK_INVARIANTS=1
 *     GAMESHARK_INVARIANTS_FILE=/absolute/path/to/tests/invariants/gameshark.php
 *
 * It runs before PHPUnit and before WordPress, and returns the hooks for every
 * invariant in specs/. With WP_INVARIANTS_TYPES=1, it also returns the hooks
 * which count the types of what every WordPress function is given and returns,
 * for the types report. With WP_INVARIANTS_CANARIES=1, it counts the code in
 * canaries/fixtures/ as code under test, for the canaries.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once __DIR__ . '/includes/load.php';

// The canaries break the invariants on purpose, from code which stands in for WordPress.
if ( getenv( 'WP_INVARIANTS_CANARIES' ) ) {
	$root = dirname( __DIR__, 2 );
	WP_Invariant::configure_paths(
		array(
			'test' => array( "{$root}/tests/phpunit", __DIR__ . '/canaries/tests', __DIR__ . '/includes' ),
			'core' => array( "{$root}/src", "{$root}/build", __DIR__ . '/canaries/fixtures' ),
		)
	);
	unset( $root );
}

$specs = WP_Invariant::load_specs( __DIR__ . '/specs' );

if ( getenv( 'WP_INVARIANTS_TYPES' ) ) {
	$root = dirname( __DIR__, 2 );

	// Bundled third-party libraries, which are not WordPress functions: those listed as such in tests/phpstan/base.neon.
	$bundled = array(
		'src/wp-admin/includes/class-ftp-pure.php',
		'src/wp-admin/includes/class-ftp-sockets.php',
		'src/wp-admin/includes/class-ftp.php',
		'src/wp-admin/includes/class-pclzip.php',
		'src/wp-includes/ID3',
		'src/wp-includes/IXR',
		'src/wp-includes/PHPMailer',
		'src/wp-includes/Requests',
		'src/wp-includes/SimplePie',
		'src/wp-includes/Text',
		'src/wp-includes/atomlib.php',
		'src/wp-includes/class-IXR.php',
		'src/wp-includes/class-avif-info.php',
		'src/wp-includes/class-json.php',
		'src/wp-includes/class-phpass.php',
		'src/wp-includes/class-pop3.php',
		'src/wp-includes/class-requests.php',
		'src/wp-includes/class-simplepie.php',
		'src/wp-includes/class-snoopy.php',
		'src/wp-includes/php-ai-client',
		'src/wp-includes/pomo',
		'src/wp-includes/rss.php',
		'src/wp-includes/sodium_compat',
	);

	$directories = array( "{$root}/src/wp-includes", "{$root}/src/wp-admin" );
	$targets     = WP_Invariant_Types::targets(
		$directories,
		array_map(
			static function ( $path ) use ( $root ) {
				return "{$root}/{$path}";
			},
			$bundled
		),
		sys_get_temp_dir() . '/wp-invariants-types-' . md5( $root ) . '.cache'
	);
	$specs       = array_merge( $specs, WP_Invariant_Types::specs( $targets, $directories, array( "{$root}/tests" ) ) );
	register_shutdown_function( array( 'WP_Invariant_Types', 'save_for_parent' ) );

	unset( $root, $bundled, $directories, $targets );
}

return $specs;
