<?php

/**
 * Core class for sniffing MIME types from various sources in standardized and secure ways.
 *
 * This class exists to harmonize content-type detection between the server and browsers.
 * See the following introduction from the WHATWG MIME Sniffing specification:
 *
 * > The HTTP Content-Type header field is intended to indicate the MIME type of an HTTP response.
 * > However, many HTTP servers supply a Content-Type header field value that does not match the
 * > actual contents of the response. Historically, web browsers have tolerated these servers by
 * > examining the content of HTTP responses in addition to the Content-Type header field in order
 * > to determine the effective MIME type of the response.
 * >
 * > Without a clear specification for how to "sniff" the MIME type, each user agent has been
 * > forced to reverse-engineer the algorithms of other user agents in order to maintain
 * > interoperability. Inevitably, these efforts have not been entirely successful, resulting
 * > in divergent behaviors among user agents. In some cases, these divergent behaviors have
 * > had security implications, as a user agent could interpret an HTTP response as a different
 * > MIME type than the server intended.
 * >
 * > These security issues are most severe when an "honest" server allows potentially malicious
 * > users to upload their own files and then serves the contents of those files with a low-privilege
 * > MIME type. For example, if a server believes that the client will treat a contributed file as an
 * > image (and thus treat it as benign), but a user agent believes the content to be HTML (and thus
 * > privileged to execute any scripts contained therein), an attacker might be able to steal the
 * > user’s authentication credentials and mount other cross-site scripting attacks. (Malicious
 * > servers, of course, can specify an arbitrary MIME type in the Content-Type header field.)
 * >
 * > This document describes a content sniffing algorithm that carefully balances the compatibility
 * > needs of user agent with the security constraints imposed by existing web content. The algorithm
 * > originated from research conducted by Adam Barth, Juan Caballero, and Dawn Song, based on content
 * > sniffing algorithms present in popular user agents, an extensive database of existing web content,
 * > and metrics collected from implementations deployed to a sizable number of users.
 * >
 * >  - https://mimesniff.spec.whatwg.org/#introduction
 *
 * Some MIME types are inferred from string sources, such as HTTP headers and HTML meta values. These
 * are usually intentional declarations of a MIME type, and while not always accurate, they are meant
 * to explicitly convey content types.
 *
 * Example:
 *
 *     $mime_type = WP_Mime_Sniffer::from_declaration( $headers['content-type'] );
 *     if ( isset( $mime_type ) && $mime_type->is_json() ) {
 *         echo '<script type="application/json">';
 *     }
 *
 *     $mime_type = WP_Mime_Sniffer::from_declaration( 'text/HTML  ; charset=utf8' );
 *     'text/html;charset=utf8' === $mime_type->serialize();
 *
 * In other cases the MIME types are inferred from _binary_ data, which may pose a higher security
 * risk due to the complexity of binary decoders. While strings and binary data in PHP are both
 * stored identically in a `string` type, the binary sniffing expects non-human-readable inputs,
 * like media files or archives, and operates on the data from inside the file.
 *
 * Example:
 *
 *     $mime_type = WP_Mime_Sniffer::from_binary_file_contents( $uploaded_file_data );
 *
 * It is not necessary to read the file contents before sniffing the MIME type, however. It may
 * be preferable to pass a file path, in which case this class will only read as many bytes as
 * are necessary to perform the sniff. This can prevent out-of-memory crashing when working with
 * large files, such as video content.
 *
 * Example:
 *
 *     $mime_type = WP_Mime_Sniffer::from_file( $tempfile );
 *
 * @see https://mimesniff.spec.whatwg.org/
 * @see https://www.rfc-editor.org/rfc/rfc2045#section-5.1
 * @see https://www.rfc-editor.org/rfc/rfc9110#name-media-type
 * @see https://www.iana.org/assignments/media-types/media-types.xhtml
 *
 * @since 7.0.0
 */
class WP_Mime_Sniffer {
	/**
	 * @since 7.0.0
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * @since 7.0.0
	 *
	 * @var string
	 */
	protected $subtype;

	/**
	 * @since 7.0.0
	 *
	 * @var Array<string, string>
	 */
	protected $parameters = array();

	/**
	 * @since 7.0.0
	 *
	 * @var string|null
	 */
	private static $last_error = null;

	/**
	 * @todo Not sure what to do with this, or if it’s necessary. It could be masquerading HTML content types.
	 *
	 * > some older installations of Apache contain a bug that causes them to supply one
	 * > of these Content-Type headers when serving files with unrecognized MIME types
	 *
	 * @see https://web.archive.org/web/20250511162054/https://bz.apache.org/bugzilla/show_bug.cgi?id=13986
	 *
	 * @since 7.0.0
	 *
	 * @var bool
	 */
	protected $detected_apache_bug = false;

	public function __construct( string $type, string $subtype, ?array $parameters = null ) {
		$this->type    = strtolower( $type );
		$this->subtype = strtolower( $subtype );

		/** @todo Validate parameters */
		if ( isset( $parameters ) ) {
			$this->parameters = $parameters;
		}
	}

	/**
	 * Parses a supplied MIME type declaration, if valid, otherwise returns `null`.
	 *
	 * Example:
	 *
	 *     $mime_type = WP_Mime_Sniffer::from_declaration( 'text/html; charset=utf8' );
	 *     true   === $mime_type->is_html();
	 *     'utf8' === $mime_type->indicated_charset();
	 *
	 *     null === WP_Mime_Sniffer::from_declaration( 'html' );
	 *
	 *     $mime_type = WP_Mime_Sniffer::from_declaration( 'text/json' );
	 *     true               === $mime_type->is_json();
	 *     'application/json' === $mime_type->essence();
	 *
	 * @since 7.0.0
	 *
	 * @param string $supplied_type Provided text of MIME type, e.g. from HTTP "Content-Type" header.
	 * @return self|null MIME type instance if valid, otherwise `null`.
	 */
	public static function from_declaration( string $supplied_type ): ?self {
		// 1. Remove any leading and trailing HTTP whitespace from input.
		$input = trim( $supplied_type, self::HTTP_WHITESPACE );

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
		$subtype = rtrim( $subtype, self::HTTP_WHITESPACE );

		// 9. If subtype is the empty string or does not solely contain HTTP token code points, then return failure.
		if ( '' === $subtype || strspn( $subtype, self::TOKEN_CODE_POINTS ) !== strlen( $subtype ) ) {
			return null;
		}

		// 10. Let mimeType be a new MIME type record whose type is type, in ASCII lowercase, and subtype is subtype, in ASCII lowercase.
		$self = new self( $type, $subtype );

		// 11. While position is not past the end of input:
		$position = $subtype_start + $subtype_length;
		while ( $position < $end ) {
			// 1. Advance position by 1. (This skips past U+003B (;).)
			++$position;

			// 2. Collect a sequence of code points that are HTTP whitespace from input given position.
			$position += strspn( $input, self::HTTP_WHITESPACE, $position );

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
				$value = rtrim( substr( $input, $value_start, $value_length ), self::HTTP_WHITESPACE );

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

	public static function from_file( string $file_path ): ?self {
		$is_file_scheme = 0 === substr_compare( $file_path, 'file://', 0, 7, false );
		$filename       = $is_file_scheme ? $file_path : "file://{$file_path}";

		$handle = fopen( $filename, 'rb' );
		if ( false === $handle ) {
			self::$last_error = 'File not found.';
			return null;
		}

		$resource_header = fread( $handle, 1445 );
		if ( false === $resource_header ) {
			self::$last_error = 'Could not read file.';
			return null;
		}

		return self::from_binary_file_contents( $resource_header );
	}

	/**
	 * Sniffs a MIME type from the contents of a binary file, if possible, otherwise returns `null`.
	 *
	 * @since 7.0.0
	 *
	 * @param string $resource_header Contents of a file, of which only a maximum of 1455 bytes will be analyzed.
	 * @return self|null MIME type instance if detected, otherwise `null`.
	 */
	public static function from_binary_file_contents( string $resource_header ): ?self {
		if ( strlen( $resource_header ) > 1445 ) {
			$resource_header = substr( $resource_header, 0, 1445 );
		}

		$length = strlen( $resource_header );

		if ( str_starts_with( $resource_header, '%PDF-' ) ) {
			return new self( 'application', 'pdf' );
		}

		$leading_ws = strspn( $resource_header, " \t\f\r\n" );

		if ( 0 === substr_compare( $resource_header, '<?xml', $leading_ws, 5, false ) ) {
			return new self( 'text', 'xml' );
		}

		$html_prefixes = array(
			array( '!DOCTYPE HTML', 13 ),
			array( 'HTML', 4 ),
			array( 'HEAD', 4 ),
			array( 'SCRIPT', 6 ),
			array( 'IFRAME', 6 ),
			array( 'H1', 2 ),
			array( 'DIV', 3 ),
			array( 'FONT', 4 ),
			array( 'TABLE', 5 ),
			array( 'A', 1 ),
			array( 'STYLE', 5 ),
			array( 'TITLE', 5 ),
			array( 'B', 1 ),
			array( 'BODY', 4 ),
			array( 'BR', 2 ),
			array( 'P', 1 ),
			array( '!--', 3 ),
		);

		if ( $length > $leading_ws && '<' === $resource_header[ $leading_ws ] ) {
			$prefix_start = $leading_ws + 1;

			foreach ( $html_prefixes as $prefix_pair ) {
				list( $prefix, $prefix_length ) = $prefix_pair;
				$prefix_end = $prefix_start + $prefix_length;

				if (
					$length >= $prefix_end &&
					0 === substr_compare( $resource_header, $prefix[0], $prefix_start, $prefix_length, true ) &&
					( ' ' === $resource_header[ $prefix_end ] || '>' === $resource_header[ $prefix_end ] )
				) {
					return new self( 'text', 'html' );
				}
			}
		}

		if ( str_starts_with( $resource_header, '%!PS-Adobe-' ) ) {
			return new self( 'application', 'postscript' );
		}

		if (
			$length >= 4 &&
			(
				str_starts_with( $resource_header, "\xFE\xFF" ) ||  // UTF-16BE BOM
				str_starts_with( $resource_header, "\xFF\xFE" ) ||  // UTF-16LE BOM
				str_starts_with( $resource_header, "\xEF\xBB\xBF" ) // UTF-8 BOM
			)
		) {
			return new self( 'text', 'plain' );
		}

		$sniffed_type = self::sniff_image_binary( $resource_header );
		if ( isset( $sniffed_type ) ) {
			return $sniffed_type;
		}

		$sniffed_type = self::sniff_audio_video_binary( $resource_header );
		if ( isset( $sniffed_type ) ) {
			return $sniffed_type;
		}

		$sniffed_type = self::sniff_archive_binary( $resource_header );
		if ( isset( $sniffed_type ) ) {
			return $sniffed_type;
		}

		$nonbinary_length = strcspn( $resource_header, self::BINARY_BYTES );
		return $length === $nonbinary_length
			? new self( 'text', 'plain' )
			: new self( 'application', 'octet-stream' );
	}

	private static function sniff_image_binary( string $resource_header ): ?self {
		$image_byte_patterns = array(
			array( "\x00\x00\x01\x00", 'image', 'x-icon' ), // Windows Icon
			array( "\x00\x00\x02\x00", 'image', 'x-icon' ), // Windows Cursor
			array( 'BM', 'image', 'bmp' ),                  // BMP
			array( 'GIF87a', 'image', 'gif' ),              // GIF
			array( 'GIF89a', 'image', 'gif' ),              // GIF
			array( "\x89PNG\r\n\x1A\n", 'image', 'png' ),   // PNG
			array( "\xFF\xD8\xFF", 'image', 'jpg' ),        // PNG
		);

		foreach ( $image_byte_patterns as $pattern_pair ) {
			list( $prefix, $type, $subtype ) = $pattern_pair;

			if ( str_starts_with( $resource_header, $prefix ) ) {
				return new self( $type, $subtype );
			}
		}

		if (
			strlen( $resource_header ) >= 14 &&
			str_starts_with( $resource_header, 'RIFF' ) &&
			0 === substr_compare( $resource_header, 'WEBPVP', 8, 6, false )
		) {
			return new self( 'image', 'webp' );
		}

		return null;
	}

	private static function sniff_audio_video_binary( string $resource_header ): ?self {
		$media_prefixes = array(
			array( 'ID3', 'audio', 'mpeg' ),                  // ID3v2 MP3
			array( "OggS\x00", 'application', 'ogg' ),        // Ogg
			array( "MThd\x00\x00\x00\x06", 'audio', 'midi' ), // MIDI
		);

		foreach ( $media_prefixes as $prefix_pair ) {
			list( $prefix, $type, $subtype ) = $prefix_pair;

			if ( str_starts_with( $resource_header, $prefix ) ) {
				return new self( $type, $subtype );
			}
		}

		$length = strlen( $resource_header );

		if (
			$length >= 12 &&
			str_starts_with( $resource_header, 'FORM' ) &&
			0 === substr_compare( $resource_header, 'AIFF', 8, 4, false )
		) {
			return new self( 'audio', 'aiff' );
		}

		if ( $length >= 12 && str_starts_with( $resource_header, 'RIFF' ) ) {
			if ( 0 === substr_compare( $resource_header, 'AVI ', 8, 4, false ) ) {
				return new self( 'video', 'avi' );
			}

			if ( 0 === substr_compare( $resource_header, 'WAVE', 8, 4, false ) ) {
				return new self( 'audio', 'wave' );
			}
		}

		$media_type = self::sniff_mp4_binary( $resource_header );
		if ( isset( $media_type ) ) {
			return $media_type;
		}

		$media_type = self::sniff_webm_binary( $resource_header );
		if ( isset( $media_type ) ) {
			return $media_type;
		}

		$media_type = self::sniff_mp3_without_id3_binary( $resource_header );
		if ( isset( $media_type ) ) {
			return $media_type;
		}

		return null;
	}

	private static function sniff_mp4_binary( string $resource_header ): ?self {
		$length = strlen( $resource_header );

		if ( $length < 12 ) {
			return null;
		}

		$box_size = unpack( 'N', $resource_header, 0 )[0];
		if ( $length < $box_size || 0 !== ( $box_size % 4 ) ) {
			return null;
		}

		if ( 0 !== substr_compare( $resource_header, 'ftyp', 4, 4, false ) ) {
			return null;
		}

		if ( 0 === substr_compare( $resource_header, 'mp4', 8, 3, false ) ) {
			return new self( 'video', 'mp4' );
		}

		$bytes_read = 16;
		while ( $bytes_read < $box_size ) {
			if ( 0 === substr_compare( $resource_header, 'mp4', $bytes_read, 3, false ) ) {
				return new self( 'video', 'mp4' );
			}

			$bytes_read += 4;
		}

		return null;
	}

	/**
	 * @see https://mimesniff.spec.whatwg.org/#signature-for-webm
	 */
	private static function sniff_webm_binary( string $resource_header ): ?self {
		throw new Exception( 'Not Implemented' );
	}

	/**
	 * @see https://mimesniff.spec.whatwg.org/#signature-for-mp3-without-id3
	 */
	private static function sniff_mp3_without_id3_binary( string $resource_header ): ?self {
		throw new Exception( 'Not Implemented' );
	}

	private static function sniff_archive_binary( string $resource_header ): ?self {
		$archive_prefixes = array(
			array( "\x1F\x8B\x08", 'application', 'x-gzip' ),     // GZIP
			array( "PK\x03\x04", 'application', 'zip' ),          // ZIP
			array( "Rar!\x1A\x07\x00", 'application', 'x-gzip' ), // RAR 4.x
		);

		foreach ( $archive_prefixes as $prefix_pair ) {
			list( $prefix, $type, $subtype ) = $prefix_pair;

			if ( str_starts_with( $resource_header, $prefix ) ) {
				return new self( $type, $subtype );
			}
		}
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

	public function is_html_family(): bool {
		return (
			$this->is_html() ||
			( 'application' === $this->type && 'xhtml+xml' === $this->subtype )
		);
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

	/**
	 * Returns a parsed MIME media type if the given string represents a JavaScript media type.
	 *
	 * @since 7.0.0
	 *
	 * @param string $supplied_type
	 * @return self|null
	 */
	public static function sniff_javascript( string $supplied_type ): ?self {
		$mime_type = self::from_declaration( $supplied_type );

		return isset( $mime_type ) && $mime_type->is_javascript()
			? $mime_type
			: null;
	}

	/**
	 * Returns a parsed MIME media type if the given string represents a JSON media type.
	 *
	 * @since 7.0.0
	 *
	 * @param string $supplied_type
	 * @return self|null
	 */
	public static function sniff_json( string $supplied_type ): ?self {
		$mime_type = self::from_declaration( $supplied_type );

		return isset( $mime_type ) && $mime_type->is_json()
			? $mime_type
			: null;
	}

	/**
	 * > A binary data byte is a byte in the range 0x00 to 0x08 (NUL to BS), the byte 0x0B (VT),
	 * > a byte in the range 0x0E to 0x1A (SO to SUB), or a byte in the range 0x1C to 0x1F (FS to US).
	 *
	 * @since 7.0.0
	 */
	const BINARY_BYTES = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1C\x1D\x1E\x1F";

	/**
	 * > HTTP whitespace is U+000A LF, U+000D CR, or an HTTP tab or space.
	 * > An HTTP tab or space is U+0009 TAB or U+0020 SPACE.
	 *
	 * @see https://fetch.spec.whatwg.org/#http-whitespace
	 *
	 * @since 7.0.0
	 */
	const HTTP_WHITESPACE = " \t\f\r\n";

	/**
	 * > An HTTP quoted-string token code point is U+0009 TAB, a code point in the range U+0020 SPACE to U+007E (~),
	 * > inclusive, or a code point in the range U+0080 through U+00FF (ÿ), inclusive.
	 *
	 * This is the inverse set of the above code points for ease of use as a shorter string.
	 *
	 * @since 7.0.0
	 */
	const QUOTED_STRING_FORBIDDEN = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

	/**
	 * > An HTTP token code point is U+0021 (!), U+0023 (#), U+0024 ($), U+0025 (%), U+0026 (&), U+0027 ('),
	 * > U+002A (*), U+002B (+), U+002D (-), U+002E (.), U+005E (^), U+005F (_), U+0060 (`), U+007C (|),
	 * > U+007E (~), or an ASCII alphanumeric.
	 *
	 * @since 7.0.0
	 */
	const TOKEN_CODE_POINTS = "!#$%&'*+-.0123456789^_`ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz|~";
}
