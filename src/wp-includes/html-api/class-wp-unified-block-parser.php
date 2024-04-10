<?php

class WP_Lazy_JSON_Object {
	/**
	 * Contains the input document as a string.
	 *
	 * If unset, read the parsed value directly. This is freed
	 * to release memory from the input string.
	 *
	 * @var ?string.
	 */
	private $json;

	private $at;

	private $length;

	/**
	 * If set, contains the parsed value of the JSON string.
	 *
	 * @var ?mixed.
	 */
	private $parsed_value = null;

	/**
	 * Constructor function.
	 *
	 * @param string $json JSON to potentially/eventually parse.
	 */
	public function __construct( $json, $at = 0, $length = null ) {
		$this->json   = $json;
		$this->at     = $at;
		$this->length = $length;
	}

	/**
	 * Parses and returns the value of the input JSON.
	 *
	 * @return mixed
	 */
	public function parse() {
		if ( isset( $this->parsed_value ) ) {
			return $this->parsed_value;
		}

		$this->parsed_value = json_decode( $this->source() );
		return $this->parsed_value;
	}

	public function source() {
		$length = $this->length ?? strlen( $this->json ) - $this->at;

		return substr( $this->json, $this->at, $length );
	}
}

class WP_Unified_Block_Parser {
	/** @var WP_HTML_Processor */
	private $processor;

	/** @var array[] */
	private $blocks = array();

	public function __construct( $html ) {
		$this->processor = WP_HTML_Processor::create_fragment( $html );
	}

	public function step() {
		if ( ! $this->processor->next_token() ) {
			return false;
		}

		if ( WP_HTML_Tag_Processor::COMMENT_AS_HTML_COMMENT === $this->processor->get_comment_type() ) {
			$comment_text = $this->processor->get_modifiable_text();
			echo "\e[90mFound a \e[32mcomment\e[90m: \e[34m{$comment_text}\e[m\n";
			$block = self::parse_block_comment_text( $comment_text );
			if ( false !== $block ) {
				echo "  \e[90mand it was a block!\e[m\n";
				$json = isset( $block[3] ) ? $block[3]->source() : '(no attributes)';
				echo "  \e[90m  \e[31m{$block[0]} for \e[33m{$block[1]}\e[90m/\e[33m{$block[2]}\e[90m: \e[3;35m{$json}\e[m\n";
				return $block;
			}
		}
	}

	public static function parse_block_comment_text( $text ) {
		$at     = 0;
		$length = strlen( $text );

		/*
		 * The minimum block comment is not that short.
		 *
		 * Example:
		 *
		 *     <!-- wp:b -->
		 *         └────┘ 6 characters.
		 */
		if ( $length < 6 ) {
			return false;
		}

		/*
		 * Skip whitespace.
		 *
		 * Example:
		 *
		 *     <!--    wp:paragraph -->
		 *         └──┘
		 */
		$at += strspn( $text, " \t\r\n\f", $at );
		if ( $at >= $length ) {
			return false;
		}

		/*
		 * Is this a block closer?
		 *
		 * Example:
		 *
		 *     <!-- /wp:paragraph -->
		 *          ^
		 */
		$is_closer = '/' === $text[ $at ];
		if ( $is_closer ) {
			++$at;
		}

		/*
		 * Is this a void block?
		 *
		 * Example:
		 *
		 *     <!-- wp:paragraph /-->
		 *                       ^
		 *
		 * The self-closing flag takes precedence over
		 * the closing flag, so the following would be
		 * considered a void tag.
		 *
		 * Example:
		 *
		 *     <!-- /wp:more /-->
		 */
		$is_void = '/' === $text[ $length - 1 ];

		$delimiter_type = $is_void ? 'void' : ( $is_closer ? 'closer' : 'opener' );

		/*
		 * Does this have the block comment start?
		 *
		 * Example:
		 *
		 *     <!-- wp:core/list -->
		 *          └─┘
		 */
		if ( 0 !== substr_compare( $text, 'wp:', $at, 3 ) ) {
			return false;
		}

		/*
		 * Determine block name portion, which _must_ be followed by whitespace.
		 *
		 * Example:
		 *
		 *     <!-- wp:paragraph -->
		 *             └───────┘
		 */
		$name_length = strcspn( $text, " \t\r\n\f", $at );
		if ( 0 === $name_length ) {
			return false;
		}

		/*
		 * Determine if the block name contains a namespace or is
		 * implicitly the "core/" namespace because none is present.
		 *
		 * Example:
		 *
		 *     <!-- wp:core/paragraph -->
		 *                 ^
		 */
		$slash_offset = strcspn( $text, '/', $at );
		if ( 0 === $slash_offset || $name_length === $slash_offset ) {
			return false;
		}

		$has_namespace = $slash_offset === $name_length;

		/*
		 * Separate the namespace from the block name, if a namespace is present.
		 *
		 * Example:
		 *
		 *     <!-- wp:core/paragraph {"dropCap": true} -->
		 *             └──┘ └───────┘
		 */
		$namespace = $has_namespace
			? substr( $text, $at, $slash_offset )
			: 'core';

		$block_name = $has_namespace
			? substr( $text, $at + $slash_offset, $name_length - $slash_offset )
			: substr( $text, $at, $name_length );

		$at += $name_length;

		/*
		 * Validate the namespace and block name.
		 */
		$name_pattern = '~[a-z][a-z0-9_-]*~';
		if (
			1 !== preg_match( $name_pattern, $namespace ) ||
			1 !== preg_match( $name_pattern, $block_name )
		) {
			return false;
		}

		/*
		 * Skip whitespace, which _must_ follow regardless of whether
		 * there are JSON block attributes.
		 *
		 * Example:
		 *
		 *     <!-- wp:paragraph   -->
		 *                      └─┘
		 *
		 *     <!-- wp:paragraph   {"var": "value"} -->
		 *                      └─┘
		 */
		$at += strspn( $text, " \t\r\n\f", $at );

		// If this ends the comment, then there are no attributes.
		if ( $at >= $length ) {
			return array( $delimiter_type, $namespace, $block_name, null );
		}

		/*
		 * Find the JSON attributes; these are the only things allowed
		 * after this point other than the void block indicator.
		 *
		 * Example:
		 *
		 *     <!-- wp:paragraph {"var": "value"} -->
		 *                       ^
		 */
		if ( '{' !== $text[ $at ] ) {
			return false;
		}

		/*
		 * Ensure there's whitespace after the potential JSON attributes.
		 * This could appear at the end, or if it's a void tag, immediately before it.
		 */
		if ( ! str_contains( " \t\r\n\f", $text[ $length - ( $is_void ? 2 : 1 ) ] ) ) {
			return false;
		}

		$json_region = substr( $text, $at, $length - $at - ( $is_void ? 1 : 0 ) );
		$json_region = trim( $json_region, " \t\r\n\f" );

		if ( '}' !== $json_region[ strlen( $json_region ) - 1 ] ) {
			return false;
		}

		/*
		 * @todo Should the JSON be validated here? If it fails, should the delimiter
		 *       be rejected or should it only return broken attributes? By avoiding
		 *       the parse for now it can defer the parsing costs until they are read.
		 */
		$attributes = $is_closer ? null : new WP_Lazy_JSON_Object( $json_region );
		return array( $delimiter_type, $namespace, $block_name, $attributes );
	}
}
