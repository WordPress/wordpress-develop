<?php

/**
 * Downloads the web-platform-test test suite for MIME sniffing.
 *
 * Update the SHA as appropriate to keep updated with the upstream
 * project. It may happen that changes to the project move files
 * around, rename, or delete files. When that happens, update the
 * manifest accordingly. This script is locked to a specific SHA
 * to ensure that when backwards-incompatible changes occur, that
 * WordPress tests aren’t broken because of it.
 */

$opts     = getopt( 'q', array( 'color::', 'force' ) );
$force_it = isset( $opts['force'] );
if ( isset( $opts['q'] ) || 'never' === ( $opts['color'] ?? 'auto' ) ) {
	$use_color = false;
} elseif ( 'always' === ( $opts['color'] ?? 'auto' ) ) {
	$use_color = true;
} else {
	$use_color = posix_isatty( STDOUT );
}
$r = $use_color ? "\e[31m" : '';
$g = $use_color ? "\e[32m" : '';
$y = $use_color ? "\e[33m" : '';
$b = $use_color ? "\e[34m" : '';
$v = $use_color ? "\e[35m" : '';
$c = $use_color ? "\e[36m" : '';
$_ = $use_color ? "\e[90m" : '';
$z = $use_color ? "\e[m" : '';

$base_url = 'https://raw.githubusercontent.com';
$repo     = 'web-platform-tests/wpt';
$sha      = 'f1edffe3511493e684402fdfed42433e79374d55';

// Contains a map of { "FilePath:ETag": { "/path/to/file.json": "LastEtagValue" | null } }
$manifest_path = __DIR__ . '/manifest.json';
$manifest      = json_decode( file_get_contents( $manifest_path ), true );

foreach ( $manifest['FilePath:ETag'] as $file_path => $etag ) {
	$url = "{$base_url}/{$repo}/{$sha}/{$file_path}";
	$ch  = curl_init();

	echo "{$_}Fetching '{$c}{$url}{$_}'...{$z}\n";
	$use_etag = ! $force_it && is_string( $etag );

	curl_setopt_array( $ch, [
		CURLOPT_URL            => $url,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 3,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_USERAGENT      => 'PHP/WordPress Tests/updating-test-data',
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_HTTPHEADER     => $use_etag ? array( "If-None-Match: \"{$etag}\"" ) : array(),
		CURLOPT_HEADER         => true,
	] );

	$response = curl_exec( $ch );
	if ( $response === false ) {
		$error = curl_error( $ch );
		echo "{$_}Failed to download '{$g}{$file_path}{$_}'. Skipping.{$z}\n";
		continue;
	}

	$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	$headers     = substr( $response, 0, $header_size );
	$test_data   = substr( $response, $header_size );

	$status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

	if ( $status_code === 304 ) {
		echo "{$_}Test file '{$g}{$file_path}{$_}' unchanged since last request. Skipping.{$z}\n";
		continue;
	}

	if ( $status_code !== 200 ) {
		echo "{$_}Unhandled HTTP {$y}{$status_code}{$_} response for '{$g}{$file_path}{$_}'. Skipping.{$z}\n";
		continue;
	}

	if ( str_ends_with( $file_path, ".json" ) ) {
		$test_decode = json_decode( $test_data, true );
		if ( null === $test_decode || JSON_ERROR_NONE !== json_last_error() ) {
			$error = json_last_error_msg();
			echo "{$_}Unable to parse JSON for '{$g}{$file_path}{$_}'. Skipping.{$z}\n";
			echo "{$r}  {$error}{$z}\n";
			continue;
		}
	}

	$new_etag   = null;
	if ( 1 === preg_match( '~(^|\r\n)ETag:\s+"(?P<ETAG_VALUE>[^"]+)"\r\n~i', $headers, $etag_match ) ) {
		$new_etag = $etag_match['ETAG_VALUE'];
	}

	file_put_contents( __DIR__ . '/wpt-tests/' . basename( $file_path ), $test_data );
	if ( isset( $new_etag ) ) {
		$manifest[ 'FilePath:ETag' ][ $file_path ] = $new_etag;
		echo "{$_}Updated '{$g}{$file_path}{$_}' setting ETag to '{$v}{$new_etag}{$_}'.{$z}\n";
	} else {
		echo "{$_}Updated '{$g}{$file_path}{$_}' but {$r}received no ETag.{$z}\n";
	}
}

file_put_contents( $manifest_path, json_encode( $manifest, JSON_PRETTY_PRINT ) );
