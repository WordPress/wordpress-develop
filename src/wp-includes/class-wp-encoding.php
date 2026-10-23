<?php

/**
 * WP_Encoding class for interacting with character encoding names and byte streams,
 * according to the WHATWG Encoding specification.
 *
 * @link https://encoding.spec.whatwg.org/
 *
 * @since 7.2.0
 *
 * @group encoding
 */
final class WP_Encoding {
	private static ?bool $mb_present = null;

	/**
	 * Returns a UTF-8 string loaded from an encoded byte stream.
	 *
	 * @see self::export_from_utf8() for exporting from UTF-8.
	 *
	 * @since 7.2.0
	 *
	 * @param string $source_encoding
	 * @param string $encoded_bytes
	 * @return string|null
	 */
	public static function load_into_utf8( string $source_encoding, string $encoded_bytes ): ?string {
		$encoding = self::encoding_from_label( $source_encoding );
		if ( null === $encoding ) {
			return null;
		}

		if ( 'replacement' === $encoding ) {
			return '';
		}

		self::$mb_present ??= \function_exists( '\mb_convert_encoding' );

		/*
		 * In a future expansion, when the decoders from the WHATWG spec are
		 * introduced, this will return according to those fallbacks. For now,
		 * without the `mbstring` extension, this will fail.
		 */
		if ( ! self::$mb_present ) {
			return null;
		}

		try {
			return \mb_convert_encoding( $encoded_bytes, $encoding, 'UTF-8' );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Returns an encoded byte stream from a source UTF-8 string.
	 *
	 * @see self::load_into_utf8() for importing into UTF-8.
	 *
	 * @since 7.2.0
	 *
	 * @param string $destination_encoding
	 * @param string $utf8_bytes
	 * @return string|null
	 */
	public static function export_from_utf8( string $destination_encoding, string $utf8_bytes ): ?string {
		$encoding = self::encoding_from_label( $destination_encoding );
		if ( null === $encoding || 'replacement' === $encoding ) {
			return null;
		}

		self::$mb_present ??= \function_exists( '\mb_convert_encoding' );

		/*
		 * In a future expansion, when the encoders from the WHATWG spec are
		 * introduced, this will return according to those fallbacks. For now,
		 * without the `mbstring` extension, this will fail.
		 */
		if ( ! self::$mb_present ) {
			return null;
		}

		try {
			return \mb_convert_encoding( $utf8_bytes, 'UTF-8', $encoding );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Canonicalizes a character-encoding label into a supported encoding name.
	 *
	 * Note! “Supported” does not mean that there’s a way to definitive way to
	 *       convert the encoding; it means that the encoding is not rejected
	 *       by WordPress as unsupported. Actual support currently depends on
	 *       the presence of the `mbstring` extension with support built-in
	 *       for the returned encoding.
	 *
	 * The `replacement` encoding implies that a decoder always returns the empty
	 * string. This catches encodings which pose security risks and should never
	 * be attempted to be decoded. The `x-user-defined` is a special form of
	 * decoding byte streams into UTF-8 while preserving the source bytes.
	 *
	 * Example:
	 *
	 *     // Common labels map to well-known encoding names.
	 *     'UTF-8'        === WP_Encoding::encoding_from_label( 'utf8' );
	 *     'windows-1252' === WP_Encoding::encoding_from_label( 'ISO-8859-1' );
	 *     'windows-1252' === WP_Encoding::encoding_from_label( 'cp1252' );
	 *
	 *     // Leading and trailing whitespace is trimmed; matches are ASCII-case-insensitive.
	 *    'UTF-8' === WP_Encoding::encoding_from_label( 'utf8' );
	 *    'UTF-8' === WP_Encoding::encoding_from_label( 'UTF8' );
	 *    'UTF-8' === WP_Encoding::encoding_from_label( "  utf-8\t" );
	 *
	 *     // For security purposes, some labels map to a rejected pseudo-encoding.
	 *     'replacement' === WP_Encoding::encoding_from_label( 'iso-2022-cn' );
	 *
	 *     // Unknown or unrecognized labels are rejected entirely.
	 *     null === WP_Encoding::encoding_from_label( 'utf-7' );
	 *     null === WP_Encoding::encoding_from_label( 'UTF-8; latin1' );
	 *     null === WP_Encoding::encoding_from_label( '<meta charset="utf-8">' );
	 *
	 * @see self::load_into_utf8() for importing into UTF-8 from these encodings.
	 * @see self::export_from_utf8() for exporting from UTF-8 into these encodings.
	 *
	 * @link https://encoding.spec.whatwg.org/#names-and-labels
	 *
	 * @since 7.2.0
	 *
	 * @param string $label Candidate encoding name, e.g. "latin1" or "UTF-8".
	 * @return string|'replacment'|null Canonical name of encoding, if supported, else `null`.
	 *                                  `'replacement'` means that the decode should always be
	 *                                  the empty string, as a security precaution.
	 */
	public static function encoding_from_label( string $label ): ?string {
		/*
		 * > To get an encoding from a string label, run these steps:
		 * > 1. Remove any leading and trailing ASCII whitespace from label.
		 * > 2. If label is an ASCII case-insensitive match for any of the labels listed in the table below,
		 * > then return the corresponding encoding; otherwise return failure.
		 */
		$label = \trim( $label, " \t\f\r\n" );
		$label = \strtolower( $label );

		/*
		 * Pre-filter on the most-common reported names to avoid additional processing.
		 * A survey of 231,566 root-domain pages from the top ten-million visited sites
		 * revealed that 13,402 (5.8%) reported a charset. Among those, these case-sensitive
		 * labels accounted for the overwhelming majority of reports:
		 *
		 *  | Label        | Count |   %   |
		 *  | UTF-8        | 9,295 | 69  % |
		 *  | iso-8859-1   | 2,150 | 16  % |
		 *  | windows-1252 |   663 |  4.9% |
		 *  | us-ascii     |   470 |  3.5% |
		 *  | windows-1251 |   162 |  1.2% |
		 *
		 * These account for 95% of reported charset labels, comprising only three distinct
		 * supported charsets. This is worth the quick-pass even though it induces a tiny
		 * amount of re-computation in the case that none of these match.
		 */
		if ( 'utf-8' === $label ) {
			return 'UTF-8';
		}

		if ( 'iso-8859-1' === $label || 'windows-1252' === $label || 'us-ascii' === $label ) {
			return 'windows-1252';
		}

		if ( 'windows-1251' === $label ) {
			return 'windows-1251';
		}

		/*
		 * If whitespace exists within the label, it’s probably the accidental concatenation
		 * of multiple labels and no disambiguation is possible. If a semicolon is trapped
		 * in the name then this probably comes from mis-parsing of a `charset` property on
		 * a MIME content-type.
		 */
		if ( \strlen( $label ) !== \strcspn( $label, " \t\f\r\n;" ) ) {
			return null;
		}

		$label = " {$label} ";

		/**
		 * Mapping from encoding name to space-separated list of its labels.
		 *
		 * > The table below lists all encodings and their labels user agents must
		 * > support. User agents must not support any other encodings or labels.
		 *
		 * Every label will be surrounded on each side by spaces, as labels containing
		 * spaces are rejected. This allows for efficient string lookup.
		 *
		 * @see \generate_charset_labels_table()
		 *
		 * @since 7.2.0
		 *
		 * @type $table non-empty-array<non-empty-string, non-empty-string>
		 */
		$table = require __DIR__ . '/wp-supported-encodings.php';

		foreach ( $table as $name => $labels ) {
			if ( \str_contains( $labels, $label ) ) {
				return $name;
			}
		}

		return null;
	}
}
