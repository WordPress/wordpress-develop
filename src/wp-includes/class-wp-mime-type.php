<?php

/**
 * Core class for working with MIME type strings.
 *
 * Example:
 *
 *     $mime_type = WP_Mime_Type::from_string( $headers['content-type'] );
 *     if ( isset( $mime_type ) && $mime_type->is_json() ) {
 *         echo '<script type="application/json">';
 *     }
 *
 *     $mime_type = WP_Mime_Type::from_string( 'text/HTML  ; charset=utf8' );
 *     'text/html;charset=utf8' === $mime_type->serialize();
 *
 * @todo Many of these feel like they would be better as static methods taking strings, but also there is
 *       some non-trivial parsing involved, so those static methods should still parse unless we duplicate
 *       all of the parsing logic.
 * @todo Should the `supplied_type` be stored for further raw analysis?
 *
 * @see https://mimesniff.spec.whatwg.org/
 * @see https://www.rfc-editor.org/rfc/rfc2045#section-5.1
 * @see https://www.rfc-editor.org/rfc/rfc9110#name-media-type
 * @see https://www.iana.org/assignments/media-types/media-types.xhtml
 *
 * @since {WP_VERSION}
 */
class WP_Mime_Type {
	const TOKEN_CODE_POINTS = "!#$%&'*+-.0123456789^_`ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz|~";

	/*
	 * > An HTTP quoted-string token code point is U+0009 TAB, a code point in the range U+0020 SPACE to U+007E (~),
	 * > inclusive, or a code point in the range U+0080 through U+00FF (ÿ), inclusive.
	 *
	 * This list includes the inverse set of the above.
	 *
	 * @since {WP_VERSION}
	 */
	const QUOTED_STRING_FORBIDDEN = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

	/**
	 * @since {WP_VERSION}
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * @since {WP_VERSION}
	 *
	 * @var string
	 */
	protected $subtype;

	/**
	 * @since {WP_VERSION}
	 *
	 * @var Array<string, string>
	 */
	protected $parameters = array();

	/**
	 * @todo Not sure what to do with this, or if it’s necessary. It could be masquerading HTML content types.
	 *
	 * > some older installations of Apache contain a bug that causes them to supply one
	 * > of these Content-Type headers when serving files with unrecognized MIME types
	 *
	 * @see https://web.archive.org/web/20250511162054/https://bz.apache.org/bugzilla/show_bug.cgi?id=13986
	 *
	 * @since {WP_VERSION}
	 *
	 * @var bool
	 */
	protected $detected_apache_bug = false;

	/**
	 * @todo Rename to `WP_Mime_Type::sniff()`?
	 */
	public static function from_string( string $supplied_type ): ?self {
		// 1. Remove any leading and trailing HTTP whitespace from input.
		$input = trim( $supplied_type, " \t\r\n" );

		// 2. Let position be a position variable for input, initially pointing at the start of input.
		$position = 0;
		$end      = strlen( $input );

		// 3. Let type be the result of collecting a sequence of code points that are not U+002F (/) from input, given position.
		$type_start  = $position;
		$type_length = strcspn( $input, '/', $type_start );
		$type        = substr( $input, $type_start, $type_length );

		// 4. If type is the empty string or does not solely contain HTTP token code points, then return failure.
		// 5. If position is past the end of the input, then return failure.
		if (
			'' === $type ||
			( $position + $type_length >= $end ) ||
			strspn( $type, self::TOKEN_CODE_POINTS ) !== strlen( $type )
		) {
			return null;
		}

		// 6. Advance position by 1. (This skips past U+002F (/).)
		$position = $type_start + $type_length + 1;

		// 7. Let subtype be the result of collecting a sequence of code points that are not U+003B (;) from input, given position.
		$subtype_start  = $position;
		$subtype_length = strcspn( $input, ';', $subtype_start );

		// 8. Remove any trailing HTTP whitespace from subtype.
		$subtype = substr( $input, $subtype_start, $subtype_length );
		$subtype = rtrim( $subtype, " \t\r\n" );

		// 9. If subtype is the empty string or does not solely contain HTTP token code points, then return failure.
		if ( '' === $subtype || strspn( $subtype, self::TOKEN_CODE_POINTS ) !== strlen( $subtype ) ) {
			return null;
		}

		// 10. Let mimeType be a new MIME type record whose type is type, in ASCII lowercase, and subtype is subtype, in ASCII lowercase.
		$self          = new self();
		$self->type    = strtolower( $type );
		$self->subtype = strtolower( $subtype );

		// 11. While position is not past the end of input:
		$position = $subtype_start + $subtype_length;
		while ( $position < $end ) {
			// 1. Advance position by 1. (This skips past U+003B (;).)
			++$position;

			// 2. Collect a sequence of code points that are HTTP whitespace from input given position.
			$position += strspn( $input, " \t\r\n", $position );

			// 3. Let parameterName be the result of collecting a sequence of code points that are not U+003B (;) or U+003D (=) from input, given position.
			$parameter_start  = $position;
			$parameter_length = strcspn( $input, ';=', $parameter_start );

			// 4. Set parameterName to parameterName, in ASCII lowercase.
			$parameter_name = strtolower( substr( $input, $parameter_start, $parameter_length ) );

			// 5. If position is not past the end of input, then:
			$position = $parameter_start + $parameter_length;
			if ( $position < $end ) {
				// 1. If the code point at position within input is U+003B (;), then continue.
				if ( ';' === $input[ $position ] ) {
					continue;
				}

				// 2. Advance position by 1. (This skips past U+003D (=).)
				++$position;
			}

			// 6. If position is past the end of input, then break.
			if ( $position >= $end ) {
				break;
			}

			/*
			 * 7. Let parameterValue be null.
			 * 8. If the code point at position within input is U+0022 ("), then:
			 */
			if ( '"' === $input[ $position ] ) {
				// 1. Set parameterValue to the result of collecting an HTTP quoted string from input, given position and true.
				$value_start  = $position + 1;
				$value_length = strcspn( $input, '"', $value_start );
				$value        = substr( $input, $value_start, $value_length );
				$value        = strtr( $value, array( "\x5C" => '' ) );

				if ( $value_length > 0 && "\x5C" === $input[ $value_start + $value_length - 1 ] ) {
					$value .= "\x5C";
				}

				$position  = $value_start + $value_length;
				$position .= strcspn( $input, ';', $position );
			} else { // 9. Otherwise:
				// 1. Set parameterValue to the result of collecting a sequence of code points that are not U+003B (;) from input, given position.
				$value_start  = $position;
				$value_length = strcspn( $input, ';', $value_start );
				$position     = $value_start + $value_length;

				// 2. Remove any trailing HTTP whitespace from parameterValue.
				$value = rtrim( substr( $input, $value_start, $value_length ), " \t\r\n" );

				// 3. If parameterValue is the empty string, then continue.
				if ( '' === $value ) {
					continue;
				}
			}

			// 10. If all of the following are true…then set mimeType’s parameters[parameterName] to parameterValue.
			if (
				'' !== $parameter_name &&
				strspn( $parameter_name, self::TOKEN_CODE_POINTS ) === strlen( $parameter_name ) &&
				strcspn( $value, self::QUOTED_STRING_FORBIDDEN ) === strlen( $value ) &&
				! isset( $self->parameters[ $parameter_name ] )
			) {
				$self->parameters[ $parameter_name ] = $value;
			}
		}

		$self->detected_apache_bug = (
			'text' === $type &&
			in_array(
				$supplied_type,
				array(
					'text/plain',
					'text/plain; charset=ISO-8859-1',
					'text/plain; charset=iso-8859-1',
					'text/plain; charset=UTF-8'
				),
				true
			)
		);

		return $self;
	}

	public function serialize(): string {
		$serialization = $this->essence();

		foreach ( $this->parameters as $name => $value ) {
			$serialized_value = $value;
			if (
				'' === $value ||
				strspn( $value, self::TOKEN_CODE_POINTS ) !== strlen( $value )
			) {
				$serialized_value = strtr(
					$serialized_value,
					array(
						'"'    => '\"',
						"\x5C" => "\x5C\x5C",
					)
				);
				$serialized_value = "\"{$serialized_value}\"";
			};

			$serialization .= ";{$name}={$serialized_value}";
		}

		return $serialization;
	}

	public function essence(): string {
		return "{$this->type}/{$this->subtype}";
	}

	public function indicated_charset(): ?string {
		return $this->parameters['charset'] ?? null;
	}

	public function is_archive(): bool {
		return (
			'application' === $this->type &&
			in_array(
				$this->subtype,
				array(
					'x-rar-compressed',
					'zip',
					'x-gzip',
				),
				true
			)
		);
	}

	public function is_font(): bool {
		return (
			'application' === $this->type &&
			in_array(
				$this->subtype,
				array(
					'font-cff',
					'font-otf',
					'font-sfnt',
					'font-ttf',
					'font-woff',
					'vnd.ms-fontobject',
					'vnd.ms-opentype',
				),
				true
			)
		);
	}

	public function is_html(): bool {
		return 'text' === $this->type && 'html' === $this->subtype;
	}

	public function is_image(): bool {
		return 'image' === $this->type;
	}

	public function is_javascript(): bool {
		if ( 'application' === $this->type ) {
			return in_array(
				$this->subtype,
				array(
					'ecmascript',
					'javascript',
					'x-ecmascript',
					'x-javascript',
				),
				true
			);
		}

		if ( 'text' === $this->type ) {
			return in_array(
				$this->subtype,
				array(
					'ecmascript',
					'javascript',
					'javascript1.0',
					'javascript1.1',
					'javascript1.2',
					'javascript1.3',
					'javascript1.4',
					'javascript1.5',
					'jscript',
					'livescript',
					'x-ecmascript',
					'x-javascript',
				),
				true
			);
		}

		return false;
	}

	public function is_json(): bool {
		return (
			( 'application' === $this->type && 'json' === $this->subtype ) ||
			( 'text' === $this->type && 'json' === $this->subtype ) ||
			str_ends_with( $this->subtype, '+json' )
		);
	}

	public function is_media(): bool {
		return (
			'audio' === $this->type ||
			'video' === $this->type ||
			( 'application' === $this->type && 'ogg' === $this->subtype )
		);
	}

	public function is_scriptable(): bool {
		return (
			$this->is_xml() ||
			$this->is_html() ||
			( 'application' === $this->type && 'pdf' === $this->subtype )
		);
	}

	public function is_xml(): bool {
		return (
			( 'xml' === $this->subtype && in_array( $this->type, array( 'text', 'application' ), true ) ) ||
			str_ends_with( $this->subtype, '+xml' )
		);
	}

	public function is_zip(): bool {
		return (
			( 'application' === $this->type && 'zip' === $this->subtype ) ||
			str_ends_with( $this->subtype, '+zip' )
		);
	}
}
