<?php
/**
 * Multisite upload handler.
 *
 * @since 3.0.0
 *
 * @package WordPress
 * @subpackage Multisite
 */

define( 'SHORTINIT', true );
require_once dirname( __DIR__ ) . '/wp-load.php';

if ( ! is_multisite() ) {
	die( 'Multisite support not enabled' );
}

ms_file_constants();

error_reporting( 0 );

if ( '1' == $current_blog->archived || '1' == $current_blog->spam || '1' == $current_blog->deleted ) {
	status_header( 404 );
	die( '404 &#8212; File not found.' );
}

$file = rtrim( BLOGUPLOADDIR, '/' ) . '/' . str_replace( '..', '', $_GET['file'] );
if ( ! is_file( $file ) ) {
	status_header( 404 );
	die( '404 &#8212; File not found.' );
}

$mime = wp_check_filetype( $file );
if ( false === $mime['type'] && function_exists( 'mime_content_type' ) ) {
	$mime['type'] = mime_content_type( $file );
}

if ( $mime['type'] ) {
	$mimetype = $mime['type'];
} else {
	$mimetype = 'image/' . substr( $file, strrpos( $file, '.' ) + 1 );
}

header( 'Content-Type: ' . $mimetype ); // Always send this.
if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) ) {
	header( 'Content-Length: ' . filesize( $file ) );
}

// Optional support for X-Sendfile and X-Accel-Redirect.
if ( WPMU_ACCEL_REDIRECT ) {
	header( 'X-Accel-Redirect: ' . str_replace( WP_CONTENT_DIR, '', $file ) );
	exit;
} elseif ( WPMU_SENDFILE ) {
	header( 'X-Sendfile: ' . $file );
	exit;
}

$last_modified = gmdate( 'D, d M Y H:i:s', filemtime( $file ) );
$etag          = '"' . md5( $last_modified ) . '"';
header( "Last-Modified: $last_modified GMT" );
header( 'ETag: ' . $etag );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + 100000000 ) . ' GMT' );
// Note support for range requests.
header( 'Accept-Ranges: bytes' );

// Support for conditional GET - use stripslashes() to avoid formatting.php dependency.
$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) : false;

if ( ! isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
	$_SERVER['HTTP_IF_MODIFIED_SINCE'] = false;
}

$client_last_modified = trim( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
// If string is empty, return 0. If not, attempt to parse into a timestamp.
$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;

// Make a timestamp for our most recent modification...
$modified_timestamp = strtotime( $last_modified );

if ( ( $client_last_modified && $client_etag )
	? ( ( $client_modified_timestamp >= $modified_timestamp ) && ( $client_etag == $etag ) )
	: ( ( $client_modified_timestamp >= $modified_timestamp ) || ( $client_etag == $etag ) )
	) {
	status_header( 304 );
	exit;
}

// Support for byte-range requests
// Safari requires this and will not play mp4 files (and possibly others)
// without it.
if ( ! empty( $_SERVER['HTTP_RANGE'] ) && preg_match('/^bytes=(\d+)-(\d+)$/i', $_SERVER['HTTP_RANGE'], $m ) ) {
	$byte_start = (int) $m[1];
	$byte_end = (int) $m[2];
	if ( filesize( $file ) - 1 < $byte_end || $byte_end < $byte_start ) {
		status_header( 416 );
		die( '416 &#8212; Request Range Not Satisfiable.' );
	}
	status_header( 206 );
	header( 'Content-Range: bytes ' . $byte_start . '-' . $byte_end . '/' . filesize( $file ) );
	header( 'Content-Length: ' . ($byte_end - $byte_start + 1) );

	// Stream the file in 1kb chunks to avoid overloading PHP memory usage for large files.
	$handle = fopen( $file, "r" );
	fseek( $handle, $byte_start );
	$chunk_position = $byte_start;
	while ( $chunk_position <= $byte_end && !feof($handle) ) {
		$chunk_length = min( 1024, $byte_end - $chunk_position + 1 );
		print fread( $handle, $chunk_length );
		$chunk_position += 1024;
		flush();
	}
	fclose( $handle );
	flush();
	exit;
}

// If we made it this far, just serve the file.
readfile( $file );
flush();
