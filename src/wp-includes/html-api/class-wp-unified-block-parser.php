<?php

/**
 * Class letting us trap JSON ranges without parsing them, so that if we never
 * need the values we don't pay the parsing cost.
 *
 * Might not want to trap the parsed value to avoid holding onto it for a long time.
 */
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
	 * This is the thing we may not want to hold onto to avoid memory leaks.
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

/**
 * This holds references to the source text where the block is found. It also stores basic information
 * in a class/Record type for performance over arrays as well as inline documentation and autocomplete.
 */
class WP_Lazy_Parsed_Block {
	/**
	 * The block's namespace.
	 *
	 * @var string
	 */
	public $namespace;

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public $block_name;

	/**
	 * Whether the block is void.
	 *
	 * @var bool
	 */
	public $is_void;

	/**
	 * The block's JSON attributes, if available, lazily evaluated.
	 *
	 * @var ?WP_Lazy_JSON_Object
	 */
	public $attributes;

	/**
	 * The block's inner blocks.
	 *
	 * @var WP_Lazy_Parsed_Block[].
	 */
	public $inner_blocks = array();

	/**
	 * Bookmark for block opener.
	 *
	 * @var string
	 */
	private $block_opener_at;

	/**
	 * Bookmark for block closer.
	 *
	 * @var string
	 */
	private $block_closer_at;

	public function __construct( $namespace, $block_name, $attributes, $opener_at, $is_void ) {
		$this->namespace       = $namespace;
		$this->block_name      = $block_name;
		$this->attributes      = $attributes;
		$this->block_opener_at = $opener_at;
		$this->is_void         = $is_void;
		if ( $is_void ) {
			$this->block_closer_at = $opener_at;
		}
	}

	public function end_at( $bookmark_name ) {
		$this->block_closer_at = $bookmark_name;
	}
}

/**
 * This was used to communicate up. It could probably go away.
 */
class WP_Parsed_Block_Comment {
	/**
	 * The block's namespace.
	 *
	 * @var string
	 */
	public $namespace;

	/**
	 * The block's name.
	 *
	 * @var string
	 */
	public $block_name;

	/**
	 * The block's attributes, if an opener.
	 *
	 * @var ?WP_Lazy_JSON_Object
	 */
	public $attributes;

	/**
	 * What kind of comment delimiter this is.
	 *
	 * @var string One of "opener" "closer" or "void".
	 */
	public $type;

	public function __construct( $type, $namespace, $block_name, $attributes ) {
		$this->type       = $type;
		$this->namespace  = $namespace;
		$this->block_name = $block_name;
		if ( self::CLOSER !== $type ) {
			$this->attributes = $attributes;
		}
	}

	const OPENER = 'opener';

	const CLOSER = 'closer';

	const VOID = 'void';
}


///
/// ACTUAL CODE
///


class WP_Unified_Block_Parser {
	/** @var WP_HTML_Processor */
	private $processor;

	/** @var WP_Lazy_Parsed_Block[] */
	private $blocks = array();

	private $block_count = 0;

	public function __construct( $html ) {
		$this->processor = WP_HTML_Processor::create_fragment( $html );
	}

	/**
	 * @param WP_Lazy_Parsed_Block $block
	 */
	private function open_block( $block ) {
		if ( $block->is_void ) {
			echo "\e[90mFound a \e[32mvoid\e[90m block of type \e[34m{$block->namespace}\e[90m/\e[34m{$block->block_name}\e[90m with ";
			if ( isset( $block->attributes ) ) {
				echo "\e[3;35m{$block->attributes->source()}\e[m\n";
			} else {
				echo "\e[3mno attributes\e[m\n";
			}

			return;
		}

		echo "\e[90mOpening a block of type \e[34m{$block->namespace}\e[90m/\e[34m{$block->block_name}\e[90m with ";
		if ( isset( $block->attributes ) ) {
			echo "\e[3;35m{$block->attributes->source()}\e[m\n";
		} else {
			echo "\e[3mno attributes\e[m\n";
		}
	}

	/**
	 * @param WP_Lazy_Parsed_Block $block
	 */
	private function close_block( $block ) {
		echo "\e[90mClosing block of type \e[34m{$block->namespace}\e[90m/\e[34m{$block->block_name}\e[m\n";
		if ( count( $block->inner_blocks ) > 0 ) {
			echo "  \e[90mit contained inner blocks:\n";
			foreach ( $block->inner_blocks as $inner_block ) {
				echo "\e[90m    - \e[34m{$inner_block->namespace}\e[90m/\e[34m{$inner_block->block_name}";
				if ( isset( $inner_block->inner_blocks ) && count( $inner_block->inner_blocks ) > 0 ) {
					echo "\e[90m which itself contained \e[33m" . count( $inner_block->inner_blocks ) . "\e[90m inner blocks";
				}
				echo "\e[m\n";
			}
		}
	}

	public function get_depth() {
		return count( $this->blocks );
	}

	public function step() {
		if ( ! $this->processor->next_token() ) {
			return false;
		}

		if ( WP_HTML_Tag_Processor::COMMENT_AS_HTML_COMMENT === $this->processor->get_comment_type() ) {
			$comment_text  = $this->processor->get_modifiable_text();
			$block_comment = self::parse_block_comment_text( $comment_text );

			if ( isset( $block_comment ) ) {
				switch ( $block_comment->type ) {
					case WP_Parsed_Block_Comment::OPENER:
					case WP_Parsed_Block_Comment::VOID:
						++$this->block_count;
						$bookmark = "block-{$this->block_count}";
						$this->processor->set_bookmark( $bookmark );

						$block = new WP_Lazy_Parsed_Block(
							$block_comment->namespace,
							$block_comment->block_name,
							$block_comment->attributes,
							$bookmark,
							WP_Parsed_Block_Comment::VOID === $block_comment->type
						);

						$open_block = end( $this->blocks );
						if ( false !== $open_block ) {
							$open_block->inner_blocks[] = $block;
						}

						$this->blocks[] = $block;
						$this->open_block( $block );
						break;

					case WP_Parsed_Block_Comment::CLOSER:
						// Ignore closers if there are no openers.
						if ( 0 === count( $this->blocks ) ) {
							break;
						}

						// Ignore also if it's not the associated closer for the most-recently opened block.
						$opener = end( $this->blocks );
						if ( $opener->namespace !== $block_comment->namespace || $opener->block_name !== $block_comment->block_name ) {
							break;
						}

						++$this->block_count;
						$bookmark = "block-{$this->block_count}";
						$this->processor->set_bookmark( $bookmark );
						$opener->end_at( $bookmark );

						array_pop( $this->blocks );
						$this->close_block( $opener );

						break;
				}
				return $block_comment;
			}
		}
	}

	/**
	 * Hypothetical function to find block comments without relying on the HTML API.
	 *
	 * @param $html
	 * @param $at
	 *
	 * @return false|void
	 */
	function find_comment( $html, $at ) {
		$next_at = strpos( $html, '<!--', $at );
		if ( false === $next_at ) {
			return false;
		}

		$closer_at = strpos( $html, '-->', $next_at + 4 );
		if ( false === $next_at ) {
			return false;
		}

		$block = self::parse_block_comment_text( $html, $next_at + 4, $closer_at - $next_at - 4 );
	}

	/**
	 * Parses a comment's modifiable text to determine if it represents
	 * a valid block comment delimiter, and if so, returns the block meta.
	 *
	 * Example:
	 *
	 *     $block = parse_block_comment_text( ' wp:paragraph {"dropCaps":true} ' );
	 *     $block === WP_Lazy_Parsed_Block( 'core', 'paragraph', WP_Lazy_JSON_Object( '{"dropCaps":true}' ) );
	 *
	 *     $block = parse_block_comment_text( '[IF[IE>6]]' );
	 *     $block === null;
	 *
	 * @since {WP_VERSION}
	 *
	 * @param string $text Modifiable text for an HTML comment to parse.
	 * @return WP_Parsed_Block_Comment|null Parsed block comment delimiter, if possible, otherwise null.
	 */
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
			return null;
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
			return null;
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
			return null;
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
			return null;
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
			return null;
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
			return null;
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
			return new WP_Parsed_Block_Comment( $delimiter_type, $namespace, $block_name, null );
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
			return null;
		}

		/*
		 * Ensure there's whitespace after the potential JSON attributes.
		 * This could appear at the end, or if it's a void tag, immediately before it.
		 */
		if ( ! str_contains( " \t\r\n\f", $text[ $length - ( $is_void ? 2 : 1 ) ] ) ) {
			return null;
		}

		$json_region = substr( $text, $at, $length - $at - ( $is_void ? 1 : 0 ) );
		$json_region = trim( $json_region, " \t\r\n\f" );

		if ( '}' !== $json_region[ strlen( $json_region ) - 1 ] ) {
			return null;
		}

		/*
		 * @todo Should the JSON be validated here? If it fails, should the delimiter
		 *       be rejected or should it only return broken attributes? By avoiding
		 *       the parse for now it can defer the parsing costs until they are read.
		 */
		$attributes = $is_closer ? null : new WP_Lazy_JSON_Object( $text, $at, $length - $at - ( $is_void ? 1 : 0 ) );
		return new WP_Parsed_Block_Comment( $delimiter_type, $namespace, $block_name, $attributes );
	}
}
