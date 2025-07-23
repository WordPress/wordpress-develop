<?php

/**
 * Implements the decoder from RFC 2047:
 * MIME Part 3: Message Header Extensions for Non-ASCII Text.
 *
 * This module contains decoding functions for supported MIME
 * encodings as are used with email servers which don’t support
 * or haven’t activated UTF-8 support.
 *
 * @see https://www.rfc-editor.org/rfc/rfc2047
 *
 * @package WordPress
 * @subpackage rfc2044
 */

/**
 * Decodes text potentially containing RFC2047 MIME encoded words.
 * Returns decoded text as UTF-8, if supported, else `null`.
 *
 * Example:
 *
 *     // Quoted forms have non-printable ASCII encoded as octets.
 *     'this is some text' === wp_decode_rfc2047( '=?iso-8859-1?q?this=20is_some=20text?=' );
 *     '👌' === wp_decode_rfc2047( '=?utf-8?q?=F0=9F=91=8C?=' );
 *
 *     // Binary forms are base64-encoded.
 *     '👌' === wp_decode_rfc2047( '=?utf-8?B??=8J+RjA==?=' );
 *     'םולש ןב ילטפנ' === wp_decode_rfc2047( '=?iso-8859-8?b?7eXs+SDv4SDp7Oj08A==?=' );
 *
 *     // Character sets are re-encoded into UTF-8
 *     '100¥' === wp_decode_rfc2047( '=?iso-8859-1?Q?500=A5?=' );
 *     '🏴󠁧󠁢󠁥󠁮󠁧󠁿' === wp_decode_rfc2047( '=?GB-18030?Q?=949=C82=D36=A01=D36=9F6=D36=9F9=D36=A08=D36=A01=D36=A25?=' );
 *
 *     // Linear white-space is collapsed.
 *     'ab c d e' === wp_decode_rfc2047( '=?ASCII?Q?a?= =?ASCII?Q?b?= c d=?ASCII?Q?=20?==?ASCII?Q?e?=' )
 *
 *     // Error-handling is up to the call site.
 *     '=?UTF-8?Q?=6f?=' === wp_decode_rfc2047( '=?UTF-8?Q?=6f?=' );
 *     '=?UTF-8?Q?=6f?=' === wp_decode_rfc2047( '=?UTF-8?Q?=6f?=', 'preserve-errors' );
 *     '�' === wp_decode_rfc2047( '=?UTF-8?Q?=6f?=', 'replace-errors' );
 *     null === wp_decode_rfc2047( '=?UTF-8?Q?=6f?=', 'bail-on-error' );
 *
 *     // Invalid character encodings are errors.
 *     null === wp_decode_rfc2047( '=?UTF-8?Q?=C0?=', 'bail-on-error' );
 *
 * @see https://www.rfc-editor.org/rfc/rfc2047
 *
 * @since {WP_VERSION}
 *
 * @param string                                                $encoded US-ASCII text potentially containing MIME encoded words.
 * @param ?('preserve-errors'|'replace-errors'|'bail-on-error') $errors  Optional. How to handle invalid encoded words.
 *                                                                       Default is to preserve invalid encoded words as plaintext.
 * @return string Decoded string in UTF-8, if supported, else `null`.
 */
function wp_decode_rfc2047( $encoded, $errors = 'preserve-errors' ) {
	/**
	 * {@see iconv_mime_decode()} which does not give control over error-handling
	 * at the granularity necessary for this decoder.
	 */

	$decoded               = '';
	$end                   = strlen( $encoded );
	$at                    = 0;
	$was_at                = 0;

	set_error_handler(
		static function ( $errno, $errstr ) {
			if (
				str_starts_with( $errstr, 'mb_convert_encoding():' ) ||
				str_starts_with( $errstr, 'iconv():' )
			) {
				throw new Error( $errstr );
			}

			return false;
		},
		E_WARNING
	);

	while ( $at < $end ) {
		$encoded_word_at = strpos( $encoded, '=?', $at );
		if ( $encoded_word_at === false ) {
			break;
		}

		/*
		 * > charset   = token
		 * > token     = 1*<Any CHAR except SPACE, CTLs, and especials>
		 * > especials = "(" / ")" / "<" / ">" / "@" / "," / ";" / ":" / <"> / "/" / "[" / "]" / "?" / "." / "="
		 * > CHAR      = %00–%7F
		 * > CTL       = %00–%1F
		 * > SPACE     = %20
		 */
		$charset_at     = $encoded_word_at + 2;
		$charset_length = strspn( $encoded, "!#$%&'*+-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ\^_`abcdefghijklmnopqrstuvwxyz{|}~", $charset_at );
		if ( $charset_length < 1 ) {
			$at = $charset_at;
			continue;
		}

		$after_charset = $charset_at + $charset_length;
		if ( $after_charset >= $end || '?' !== $encoded[ $after_charset ] ) {
			$at = $after_charset;
			continue;
		}

		$encoding_at     = $after_charset + 1;
		$encoding_length = strspn( $encoded, "!#$%&'*+-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ\^_`abcdefghijklmnopqrstuvwxyz{|}~", $encoding_at );
		if ( $encoding_length < 1 ) {
			$at = $encoding_at;
			continue;
		}

		$after_encoding = $encoding_at + $encoding_length;
		if ( $after_encoding >= $end || '?' !== $encoded[ $after_encoding ] ) {
			$at = $after_encoding;
			continue;
		}

		// > encoded-text = 1*<Any printable ASCII character other than "?" or SPACE>
		$chunk_at     = $after_encoding + 1;
		$chunk_length = strspn( $encoded, "!\"#$%&'()*+,-./0123456789:;<=>@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~", $chunk_at );
		if ( $chunk_length < 1 ) {
			$at = $chunk_at;
			continue;
		}

		$closer_at = $chunk_at + $chunk_length;
		if ( $closer_at >= $end || '?' !== $encoded[ $closer_at ] || '=' !== $encoded[ $closer_at + 1 ] ) {
			$at = $closer_at;
			continue;
		}

		$after_encoded_word = $closer_at + 2;
		/*
		 * RFC2047 says the total length MUST be no more than 75 characters,
		 * but doesn’t indicate resolution when the length is greater than this.
		 *
		 *   - Should this be treated as unencoded text?
		 *   - Should this be corrupted and rejected?
		 *   - Should this be decoded anyway?
		 *
		 * Given that the intent is to fit encoded-words within a single line of
		 * a header and to ensure parsers need not lookahead too far, this will
		 * be decoded if possible. The failure was in the encoder, not here.
		 */

		if ( 1 !== $encoding_length || 1 !== strspn( $encoded, 'bBqQ', $encoding_at, 1 ) ) {
			goto handle_invalid;
		}

		/*
		 * > If the mail reader does not support the character set used, it may
		 * > (a) display the 'encoded-word' as ordinary text (i.e., as it appears
		 * > in the header), (b) make a "best effort" to display using such
		 * > characters as are available, or (c) substitute an appropriate message
		 * > indicating that the decoded text could not be displayed.
		 *
		 * > For the ISO-8859-* character sets, the mail reading program must at
		 * > least be able to display the characters which are also in the ASCII set.
		 */

		// Shorten the charset to ignore any RFC2184/RC2231 language tag.
		$charset_length = strcspn( $encoded, '*', $charset_at, $charset_length );

		/**
		 * Disregard over-long charset names. This value was chosen by inspecting the
		 * names returned by {@see mb_convert_encoding()} and {@see mb_encoding_aliases()}.
		 *
		 * The goal is to pragmatically balance supporting all possible charsets and
		 * over-eagerly allocating strings, only to disregard them immediately.
		 */
		if ( $charset_length > 32 ) {
			goto handle_invalid;
		}

		/*
		 * Only UTF-8 is supported without conversion mechanisms. When errors are
		 * preserved, the ISO-8859 family’s ASCII-compatible characters will remain.
		 */
		$charset = substr( $encoded, $charset_at, $charset_length );
		if (
			! in_array( strtoupper( $charset ), array( 'ASCII', 'US-ASCII', 'UTF8', 'UTF-8' ), true ) &&
			! function_exists( 'mb_convert_encoding' ) &&
			! function_exists( 'iconv' )
		) {
			goto handle_invalid;
		}

		/*
		 * > A mail reader need not attempt to display the text associated with an
		 * > 'encoded-word' that is incorrectly formed.  However, a mail reader
		 * > MUST NOT prevent the display or handling of a message because an
		 * > 'encoded-word' is incorrectly formed.
		 */

		$encoding = $encoded[ $encoding_at ];
		if ( 'b' === $encoding || 'B' === $encoding ) {
			$decoded_chunk = base64_decode( substr( $encoded, $chunk_at, $chunk_length ), false );
			if ( false === $decoded_chunk ) {
				goto handle_invalid;
			}
		} else {
			// @todo There is no error-handling indication here for the Q decoding.
			$failed_decode = false;
			$decoded_chunk = substr( $encoded, $chunk_at, $chunk_length );
			$decoded_chunk = strtr( $decoded_chunk, '_', ' ' );
			$decoded_chunk = preg_replace_callback(
				'/=[0-9A-F]{2}|=/', // Lower-case are not allowed.
				function ( $matches ) use ( &$failed_decode ) {
					if ( '=' === $matches[0] ) {
						$failed_decode = true;
						return $matches[0];
					}
					return hex2bin( substr( $matches[0], 1, 2 ) );
				},
				$decoded_chunk
			);

			if ( $failed_decode ) {
				goto handle_invalid;
			}
		}

		// Re-encode into UTF-8.
		if ( in_array( strtoupper( $charset ), array( 'ASCII', 'US-ASCII', 'UTF8', 'UTF-8' ), true ) ) {
			// Skip re-encoding for this one.
		} elseif ( function_exists( 'mb_convert_encoding' ) ) {
			try {
				$decoded_chunk = mb_convert_encoding( $decoded_chunk, 'UTF-8', $charset );
			} catch ( \Throwable $exception ) {
				goto handle_invalid;
			}
		} elseif ( function_exists( 'iconv' ) ) {
			$decoded_chunk = iconv( $charset, 'UTF-8', $decoded_chunk );
		}

		// Verify the encoding.
		if ( false === $decoded_chunk || ! wp_is_valid_utf8( $decoded_chunk ) ) {
			goto handle_invalid;
		}

		// Append the decoded chunk.
		$prefix_length = $encoded_word_at - $was_at;
		if ( $prefix_length === 0 || rfc2047_only_LWS( $encoded, $was_at, $prefix_length ) ) {
			$decoded .= $decoded_chunk;
		} else {
			$prefix  = substr( $encoded, $was_at, $prefix_length );
			$decoded .= "{$prefix}{$decoded_chunk}";
		}
		$was_at = $after_encoded_word;
		$at     = $was_at;
		continue;

		handle_invalid:
		$at = $after_encoded_word;
		switch ( $errors ) {
			case 'bail-on-error':
				restore_error_handler();
				return null;

			case 'preserve-errors':
				break;

			case 'replace-errors':
				$prefix_length = $encoded_word_at - $was_at;
				if ( $prefix_length === 0 || rfc2047_only_LWS( $encoded, $was_at, $prefix_length ) ) {
					$decoded .= "\u{FFFD}";
				} else {
					$prefix  = substr( $encoded, $was_at, $prefix_length );
					$decoded .= "{$prefix}\u{FFFD}";
				}
				$was_at = $after_encoded_word;
				break;

			default:
				_doing_it_wrong(
					__FUNCTION__,
					"Use only one of 'preserve-errors' or 'replace-errors' for error-handling.",
					'{WP_VERSION}'
				);
				restore_error_handler();
				return null;
		}
	}

	if ( $at === 0 ) {
		return $encoded;
	}

	$decoded .= substr( $encoded, $was_at );

	restore_error_handler();

	return $decoded;
}

/**
 * Determines if a span of text represents only linear white space.
 *
 * @since {WP_VERSION}
 * @access private
 *
 * @param string $string
 * @param int    $start
 * @param int    $length
 * @return bool
 */
function rfc2047_only_LWS( $string, $start, $length ) {
	$at  = $start;
	$end = $start + $length;
	$one = false;

	while ( $at < $end ) {
		$had_crlf = false;

		// Advance past one optional CRLF.
		if ( $at + 1 < $end && "\r" === $string[ $at ] && "\n" === $string[ $at + 1 ] ) {
			$had_crlf = true;
			$at       += 2;
		}

		// Advance past any SPACE / HTAB
		$horizontal_spaces = strspn( $string, " \t", $at, $end - $at );

		if ( 0 === $horizontal_spaces ) {
			return ! $had_crlf && $one && $at === $end;
		}

		$one = true;
		$at += $horizontal_spaces;
	}

	return $one;
}
