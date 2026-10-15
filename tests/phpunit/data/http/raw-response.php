<?php
/**
 * Serves one raw HTTP response for the legacy streams transport tests.
 *
 * @package WordPress
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- This standalone socket fixture does not load WordPress.

$server = stream_socket_server( 'tcp://127.0.0.1:0', $errno, $errstr );
if ( false === $server ) {
	fwrite( STDERR, $errstr );
	exit( 1 );
}

fwrite( STDOUT, stream_socket_get_name( $server, false ) . "\n" );
fflush( STDOUT );

$connection = stream_socket_accept( $server, 10 );
fclose( $server );
if ( false === $connection ) {
	exit( 1 );
}

stream_set_timeout( $connection, 5 );
do {
	$line = fgets( $connection );
} while ( false !== $line && "\r\n" !== $line );

$response = json_decode( $argv[1], true );
if ( '' !== $response ) {
	fwrite( $connection, $response );
}
fclose( $connection );
