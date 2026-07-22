<?php
/**
 * Email Support
 *
 * Governs how WordPress treats email addresses and what level of support
 * it provides for different email addressing standards.
 *
 * @package WordPress
 * @subpackage Email
 * @since 7.1.0
 */

/**
 * Selects which rules to apply to email addresses based on the intended
 * level of support for Unicode, ASCII, and legacy address formats.
 *
 * @since 7.1.0
 *
 * @return 'unicode'|'ascii'|'legacy' Which email support level was selected.
 */
function wp_select_email_address_support(): string {
	/**
	 * Filters the supported email address formats: Unicode, ASCII, or legacy.
	 *
	 * Note that Internationalized Domain Names (IDN) are supported in every
	 * email address policy when encoded in their ASCII compatible encoding (ACE),
	 * also known as “punycode.” Unicode email addresses makes it possible to
	 * support addresses with non-ASCII characters in the localpart, which has
	 * no equivalent ACE.
	 *
	 *  - 'unicode' supports non-US-ASCII characters in the mailbox or localpart of the address;
	 *              e.g. "josé@españa.es" or "josé@xn--espaa-rta.es".
	 *  - 'ascii'   applies updated parsing rules for traditional non-Unicode addresses, allowing
	 *              for stronger interoperability with email systems.
	 *              e.g. "jose@espana.com" or "jose@xn--espaa-rta.es".
	 *  - 'legacy'  supports the same email addresses as before WordPress 7.1.0.
	 *
	 * @see https://datatracker.ietf.org/doc/html/rfc3490
	 * @see https://datatracker.ietf.org/doc/html/rfc6530
	 *
	 * @since 7.1.0
	 *
	 * @param 'unicode'|'ascii'|'legacy' $support_level Which kinds of email addresses to allow.
	 */
	$support_level = apply_filters( 'wp_email_address_support', 'ascii' );

	/**
	 * If Unicode support has been requested, but the site is incapable of storing
	 * and interacting with Unicode addresses, degrade to ASCII support.
	 */
	if ( 'unicode' === $support_level && ! wp_can_support_unicode_email_addresses() ) {
		$support_level = 'ascii';

		wp_trigger_error(
			__FUNCTION__,
			__( 'Unable to support Unicode email addresses because of missing platform support; supporting ASCII addresses instead.' ),
			E_USER_WARNING
		);
	}

	remove_filter( 'is_email', 'wp_is_ascii_email' );
	remove_filter( 'is_email', 'wp_is_unicode_email' );
	remove_filter( 'sanitize_email', 'wp_sanitize_ascii_email' );
	remove_filter( 'sanitize_email', 'wp_sanitize_unicode_email' );

	switch ( $support_level ) {
		// Revert to pre-7.1 email address support.
		case 'legacy':
			break;

		case 'ascii':
			add_filter( 'is_email', 'wp_is_ascii_email', 10, 3 );
			add_filter( 'sanitize_email', 'wp_sanitize_ascii_email', 10, 3 );
			break;

		case 'unicode':
			add_filter( 'is_email', 'wp_is_unicode_email', 10, 3 );
			add_filter( 'sanitize_email', 'wp_sanitize_unicode_email', 10, 3 );
			break;
	}

	return $support_level;
}

/**
 * Returns whether Unicode email addresses could be supported.
 *
 * Handling Unicode email addresses depends on some platform support, namely
 * that the emails can be stored in the database and that functionality
 * exists to convert to and from punycode-encoded IDN domains.
 *
 * This function does not indicate what kind of email addresses WordPress
 * supports, only whether it is possible to store Unicode email addresses.
 *
 * @see \wp_select_email_address_support() to change the support level.
 * @see 'wp_email_address_support' filter for how to override the default support level.
 *
 * @since 7.1.0
 *
 * @return bool
 */
function wp_can_support_unicode_email_addresses(): bool {
	global $wpdb;

	/*
	 * While domains can be reliably encoded with punycode and stored in ASCII,
	 * Unicode usernames have no translation format, therefore need safe support
	 * for storing any UTF-8 character.
	 */
	$is_utf8_db = 'utf8mb4' === $wpdb->charset;

	/**
	 * These functions convert into and out of punycode. Should WordPress add a polyfill
	 * for these then this check could be removed. The {@see \WpOrg\Requests\IdnaEncoder}
	 * class exists, but provides encoding only, and incompletely as noted in the class.
	 */
	$has_idn_codec = function_exists( 'idn_to_utf8' ) && function_exists( 'idn_to_ascii' );

	return $is_utf8_db && $has_idn_codec;
}
