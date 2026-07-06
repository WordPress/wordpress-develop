<?php
/**
 * AT Protocol functions.
 *
 * @package WordPress
 * @subpackage ATProto
 */

/**
 * Determines whether a value is a valid AT Protocol DID.
 *
 * @since 7.1.0
 *
 * @param mixed $did The value to check.
 * @return bool Whether the value is a valid AT Protocol DID.
 */
function wp_is_atproto_did( $did ) {
	if ( ! is_string( $did ) ) {
		return false;
	}

	$did = trim( $did );

	if ( '' === $did || str_contains( $did, "\n" ) || str_contains( $did, "\r" ) ) {
		return false;
	}

	if ( preg_match( '/^did:plc:[a-z2-7]{24}$/', $did ) ) {
		return true;
	}

	if ( preg_match( '/^did:web:localhost(?:%3[Aa][0-9]+)?$/', $did ) ) {
		return true;
	}

	return (bool) preg_match( '/^did:web:(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $did );
}

/**
 * Retrieves the AT Protocol DID for this site.
 *
 * @since 7.1.0
 *
 * @return string The AT Protocol DID, or an empty string if one is not configured.
 */
function get_atproto_did() {
	$did = get_option( 'atproto_did', '' );

	/**
	 * Filters the AT Protocol DID for this site.
	 *
	 * Returning an empty string disables the AT Protocol DID endpoint.
	 *
	 * @since 7.1.0
	 *
	 * @param string $did The configured AT Protocol DID.
	 */
	$did = apply_filters( 'atproto_did', $did );

	if ( ! wp_is_atproto_did( $did ) ) {
		return '';
	}

	return trim( $did );
}

/**
 * Displays the AT Protocol DID document.
 *
 * @since 7.1.0
 */
function do_atproto_did() {
	$did = get_atproto_did();

	if ( '' === $did ) {
		status_header( 404 );
		nocache_headers();
		return;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	echo $did;
}
