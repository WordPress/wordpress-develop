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

$size = filesize( $file );
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
header( 'Accept-Ranges: bytes' );

// Support for byte-range requests
if ( ! empty( $_SERVER['HTTP_RANGE'] ) && preg_match( '/^bytes=(?P<start>\d+)-(?P<end>\d*)$/i', $_SERVER['HTTP_RANGE'], $m ) ) {
	$is_byte_range_request = true;

	$byte_start = (int) $m['start'];
	$byte_end   = (int) $m['end'] ? $m['end'] + 1 : $size;

	// Validate the file & request matches the size limitations.
	if ( $byte_start > $size || $byte_end > $size || $byte_end < $byte_start || $byte_start === $byte_end ) {
		status_header( 416 );
		header( 'Content-Range: bytes */' . $size );
		die( '416 &#8212; Request Range Not Satisfiable.' );
	}

	status_header( 206 );
	header( sprintf( 'Content-Range: bytes %d-%d/%d', $byte_start, $byte_end - 1, $size ) );
	header( 'Content-Length: ' . ( $byte_end - $byte_start ) );
} else {
	$is_byte_range_request = false;
	if ( false === strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) ) {
		header( 'Content-Length: ' . $size );
	}
}

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

if ( $is_byte_range_request ) {
	// Open the file and stream it.
	$handle = fopen( $file, 'rb' );
	fseek( $handle, $byte_start );

	// Optimization, if it's reading the rest of the file, stream it directly.
	if ( $byte_end === $size ) {
		fpassthru( $handle );
	} else {
		$chunk_size = 8 * KB_IN_BYTES;
		$size_left  = $byte_end - $byte_start;

		while (
			$size_left &&
			! feof( $handle ) &&
			$chunk = fread( $handle, min( $chunk_size, $size_left ) )
		) {
			$size_left -= strlen( $chunk );
			echo $chunk;
			flush();
		}
	}

	fclose( $handle );
} else {
	// Just serve the file.
	readfile( $file );
}

flush();