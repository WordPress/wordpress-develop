<?php

/*
 * The error_reporting() function can be disabled in php.ini. On systems where that is the case,
 * it's best to add a dummy function to the wp-config.php file, but as this call to the function
 * is run prior to wp-config.php loading, it is wrapped in a function_exists() check.
 */
if ( function_exists( 'error_reporting' ) ) {
	/*
	 * Disable error reporting.
	 *
	 * Set this to error_reporting( -1 ) for debugging.
	 */
	error_reporting( 0 );
}

// Set ABSPATH for execution.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

define( 'WPINC', 'wp-includes' );

$protocol = $_SERVER['SERVER_PROTOCOL'];
if ( ! in_array( $protocol, array( 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3' ), true ) ) {
	$protocol = 'HTTP/1.0';
}

$load = $_GET['load'];
if ( is_array( $load ) ) {
	ksort( $load );
	$load = implode( '', $load );
}

$load = preg_replace( '/[^a-z0-9,_-]+/i', '', $load );
$load = array_unique( explode( ',', $load ) );

if ( empty( $load ) ) {
	header( "$protocol 400 Bad Request" );
	exit;
}

require ABSPATH . 'wp-admin/includes/noop.php';
require ABSPATH . WPINC . '/script-loader.php';
require ABSPATH . WPINC . '/version.php';

$expires_offset = 31536000; // 1 year.

/*
 * Begin the concatenated output with an empty statement.
 *
 * The concatenated files are appended one after another, so a "use strict"
 * directive at the very top of the first bundled script (for example the
 * one shipped in wp-includes/js/dist/hooks.js) would be treated as the
 * directive prologue for the entire file. That would force every legacy,
 * non-strict script bundled afterwards (such as ThickBox) to run in strict
 * mode, where implicit global assignments throw a ReferenceError.
 *
 * Prepending an empty statement ensures any later "use strict" string is no
 * longer the first statement, so it is evaluated as a harmless expression
 * rather than enabling strict mode for the whole concatenated file.
 */
$out = ";\n";

$wp_scripts = new WP_Scripts();
wp_default_scripts( $wp_scripts );
wp_default_packages_vendor( $wp_scripts );
wp_default_packages_scripts( $wp_scripts );

$etag = $wp_scripts->get_etag( $load );

if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) === $etag ) {
	header( "$protocol 304 Not Modified" );
	exit;
}

foreach ( $load as $handle ) {
	if ( ! array_key_exists( $handle, $wp_scripts->registered ) ) {
		continue;
	}

	$path = ABSPATH . $wp_scripts->registered[ $handle ]->src;
	$out .= get_file( $path ) . "\n";
}

header( "Etag: $etag" );
header( 'Content-Type: application/javascript; charset=UTF-8' );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $expires_offset ) . ' GMT' );
header( "Cache-Control: public, max-age=$expires_offset" );

echo $out;
exit;
