<?php
/**
 * Class 'WP_Email_Address'.
 *
 * @package WordPress
 * @since 7.0.0
 */

/**
 * Represents a validated email address. The address may or may not be deliverable.
 *
 * Use the static factory method {@see WP_Email_Address::from_string()} to create instances
 * of this class rather than the constructor, which is private.
 *
 * @since 7.0.0
 */
final class WP_Email_Address {

	/**
	 * Regex for the local part when Unicode is not enabled.
	 *
	 * Matches the character set from the WHATWG email specification:
	 * https://html.spec.whatwg.org/multipage/input.html#email-state-(type=email)
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const LOCAL_PART_ASCII_REGEX = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+$/';

	/**
	 * Regex for the local part when Unicode is enabled.
	 *
	 * Extends the WHATWG character set to allow Unicode letters and numbers,
	 * and applies the same grapheme-cluster structure used for domain labels:
	 * each cluster must open with a non-combining character.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const LOCAL_PART_UNICODE_REGEX = '/^([\p{L}\p{N}.!#$%&\'*+\/=?^_`{|}~-]\p{M}*)+$/u';

	/**
	 * Pattern for a single ASCII domain label (no dot).
	 *
	 * Matches a label from the WHATWG email specification: starts and ends with
	 * a letter or digit; internal characters may include hyphens.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const DOMAIN_LABEL_ASCII = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

	/**
	 * Pattern for a single Unicode domain label (no dot).
	 *
	 * Extends the ASCII label pattern to allow Unicode letters and numbers,
	 * with grapheme-cluster structure: each cluster must open with a letter or
	 * digit (not a combining mark), followed by zero or more combining marks.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const DOMAIN_LABEL_UNICODE = '[\p{L}\p{N}]\p{M}*(?:(?:[\p{L}\p{N}-]\p{M}*)*[\p{L}\p{N}]\p{M}*)?';

	/**
	 * Regex for the domain when Unicode is not enabled.
	 *
	 * Assembled from {@see self::DOMAIN_LABEL_ASCII}: one label, then zero or
	 * more dot-separated labels.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const DOMAIN_ASCII_REGEX = '/^' . self::DOMAIN_LABEL_ASCII . '(?:\.' . self::DOMAIN_LABEL_ASCII . ')*$/';

	/**
	 * Regex for the domain when Unicode is enabled.
	 *
	 * Assembled from {@see self::DOMAIN_LABEL_UNICODE}: one label, then zero or
	 * more dot-prefixed labels.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const DOMAIN_UNICODE_REGEX = '/^' . self::DOMAIN_LABEL_UNICODE . '(?:\.' . self::DOMAIN_LABEL_UNICODE . ')*$/u';

	/**
	 * The local part of the email address (the portion before the '@').
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $localpart;

	/**
	 * The domain part of the email address (the portion after the '@').
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $domain;

	/**
	 * Private constructor. Use {@see WP_Email_Address::from_string()} to create instances.
	 *
	 * @since 7.0.0
	 *
	 * @param string $localpart The local part of the email address.
	 * @param string $domain    The domain part of the email address.
	 */
	private function __construct( string $localpart, string $domain ) {
		$this->localpart = $localpart;
		$this->domain    = $domain;
	}

	/**
	 * Creates a WP_Email_Address from a string.
	 *
	 * This method is intended to accept all strings that are considered valid email
	 * addresses by the WHATWG HTML specification for the email input type:
	 *
	 *     https://html.spec.whatwg.org/multipage/input.html#email-state-(type=email)
	 *
	 * and some additional addresses, while rejecting strings that
	 * are more likely to be typos, mispastes, or attacks. This class
	 * may reject a few address that are valid according to RFC 5322,
	 * but it always accepts an address if it's valid according to
	 * WHATWG. Put differently: If users can type an address into
	 * the major browsers of 2026, this class accepts them, if
	 * they can't (in 2026), this class may or may not. (Note that
	 * "<iframe src=...>"@example.com is valid according to the RFC.)
	 *
	 * @since 7.0.0
	 *
	 * @param string $input   The email address string to parse.
	 * @param bool   $unicode Whether to allow Unicode characters in the address.
	 * @return WP_Email_Address|false A WP_Email_Address instance, or false if the input is invalid.
	 */
	public static function from_string( string $input, bool $unicode ) {
		// There must be exactly one '@' sign.
		$at_pos = strpos( $input, '@' );
		if ( false === $at_pos || strrpos( $input, '@' ) !== $at_pos ) {
			return false;
		}

		$localpart = substr( $input, 0, $at_pos );
		$domain    = substr( $input, $at_pos + 1 );

		foreach ( explode( '.', $domain ) as $label ) {
			// DNS limits each label to 63 octets.
			if ( strlen( $label ) > 63 ) {
				return false;
			}
		}

		if ( $unicode && function_exists( 'idn_to_utf8' ) ) {
			// Validate each domain label, decode any punycode to UTF-8, and
			// reassemble the decoded labels into the local $domain variable.
			$decoded_labels = array();
			foreach ( explode( '.', $domain ) as $label ) {
				// Decode punycode labels to their Unicode form for further validation.
				if ( str_starts_with( $label, 'xn--' ) ) {
					$label = idn_to_utf8( $label, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
					if ( false === $label ) {
						return false;
					}
				}
				// Reject labels with a reserved ACE-like prefix (two chars followed by '--').
				if ( preg_match( '/^..--/u', $label ) ) {
					return false;
				}
				$decoded_labels[] = $label;
			}
			$domain = implode( '.', $decoded_labels );
		} else {
			// Without Unicode support, reject any non-ASCII byte in either part.
			if ( preg_match( '/[\x80-\xff]/', $input ) ) {
				return false;
			}
		}

		// Both parts must be valid UTF-8, regardless of whether Unicode is requested. (A valid ASCII string is also valid UTF-8.)
		if ( ! wp_is_valid_utf8( $localpart ) || ! wp_is_valid_utf8( $domain ) ) {
			return false;
		}

		// Validate the local part against the allowed character set.
		if ( ! preg_match( $unicode ? self::LOCAL_PART_UNICODE_REGEX : self::LOCAL_PART_ASCII_REGEX, $localpart ) ) {
			/** This filter is documented in wp-includes/formatting.php */
			if ( ! apply_filters( 'is_email', false, $input, 'local_invalid_chars' ) ) {
				return false;
			}
		}

		// The domain must contain at least one dot.
		if ( ! str_contains( $domain, '.' ) ) {
			/** This filter is documented in wp-includes/formatting.php */
			if ( ! apply_filters( 'is_email', false, $input, 'domain_no_periods' ) ) {
				return false;
			}
		}

		// Validate the domain against the allowed structure.
		if ( ! preg_match( $unicode ? self::DOMAIN_UNICODE_REGEX : self::DOMAIN_ASCII_REGEX, $domain ) ) {
			return false;
		}

		return new self( $localpart, $domain );
	}

	/**
	 * Returns the local part of the email address (the portion before the '@').
	 *
	 * @since 7.0.0
	 *
	 * @return string The local part of the email address.
	 */
	public function get_localpart(): string {
		return $this->localpart;
	}

	/**
	 * Returns the domain part of the email address (the portion after the '@').
	 *
	 * @since 7.0.0
	 *
	 * @return string The domain part of the email address.
	 */
	public function get_domain(): string {
		return $this->domain;
	}

	/**
	 * Returns the complete email address as a string.
	 *
	 * The returned value can always be passed to {@see WP_Email_Address::from_string()}
	 * and will produce an equivalent WP_Email_Address instance.
	 *
	 * @since 7.0.0
	 *
	 * @return string The complete email address.
	 */
	public function get_address(): string {
		return $this->localpart . '@' . $this->domain;
	}
}
