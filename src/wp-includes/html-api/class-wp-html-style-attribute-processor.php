<?php
/**
 * HTML API: WP_HTML_Style_Attribute_Processor class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 7.3.0
 */

/**
 * Core class used to inspect and modify CSS declarations in an HTML style attribute.
 *
 * The processor operates on the decoded CSS text value of a style attribute:
 * the CSS declaration-list contents of a declaration block, excluding the
 * delimiting braces. It does not parse HTML attribute syntax, decode character
 * references, or operate on raw HTML markup.
 *
 * Values read from {@see WP_HTML_Tag_Processor::get_attribute()} can be passed
 * to the constructor, and values returned by
 * {@see WP_HTML_Style_Attribute_Processor::get_updated_style()} can be passed
 * back to {@see WP_HTML_Tag_Processor::set_attribute()}. A missing (`null`) or
 * boolean (`true`) style attribute gives an empty style.
 *
 * Unlike associative-array based style helpers, this processor preserves the
 * declaration list model of CSS. Multiple declarations with the same property
 * name remain separate entries and can be inspected or changed individually.
 *
 * ## Setting a property
 *
 * To set a property, remove its declarations in one `next_declaration( $name )`
 * loop, then call append_declaration().
 *
 * Example:
 *
 * ```php interactive
 * $styles = new WP_HTML_Style_Attribute_Processor( 'background-image: url(a.png); color: red' );
 * while ( $styles->next_declaration( 'background-image' ) ) {
 *     $styles->remove_declaration();
 * }
 * $styles->append_declaration( 'background-image', WP_CSS_Builder::url( 'https://example.com/b.png' ) );
 * echo $styles->get_updated_style();
 * // Outputs: color: red; background-image: url("https://example.com/b.png");
 * ```
 *
 * The cursor moves forward only. After one `next_declaration( $name )` loop
 * ends, a second one finds nothing. To set several properties, remove all of
 * their declarations in one `next_declaration()` loop that compares
 * get_property_name() with each name, then append each declaration.
 *
 * Example:
 *
 * ```php interactive
 * $values = array(
 *     'color' => 'green',
 *     'width' => '2px',
 * );
 * $styles = new WP_HTML_Style_Attribute_Processor( 'color: red; width: 1px; COLOR: blue; height: 2px' );
 * while ( $styles->next_declaration() ) {
 *     if ( isset( $values[ $styles->get_property_name() ] ) ) {
 *         $styles->remove_declaration();
 *     }
 * }
 * foreach ( $values as $name => $value ) {
 *     $styles->append_declaration( $name, $value );
 * }
 * echo $styles->get_updated_style();
 * // Outputs: height: 2px; color: green; width: 2px;
 * ```
 *
 * The appended declaration is last, so it overrides earlier declarations that
 * set the same thing, shorthands included:
 *
 * - In `background-image: url(a); background: red`, setting `background-image`
 *   to `url(b)` gives `background: red; background-image: url(b);`, which shows
 *   image b.
 * - In `background-color: blue`, setting `background` to `url(c)` gives
 *   `background-color: blue; background: url(c);`. The shorthand resets the
 *   color.
 *
 * set_value() keeps the declaration where it is, so a later shorthand still
 * overrides it.
 *
 * @since 7.3.0
 *
 * @see WP_CSS_Builder
 */
class WP_HTML_Style_Attribute_Processor {
	/**
	 * CSS whitespace characters.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @var string
	 */
	private const WHITESPACE = " \t\n\r\f";

	/**
	 * Decoded style attribute value.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @var string
	 */
	private $style;

	/**
	 * CSS tokens of the style attribute value.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @var WP_CSS_Token[]
	 */
	private $tokens = array();

	/**
	 * Parsed declarations.
	 *
	 * Each declaration records byte offsets into the style text. The offsets
	 * of the second declaration in this style are:
	 *
	 *     color: red;  width: 1px !important;  top: 0
	 *                  ╰────────────────────╯     start to after
	 *                         ╰─╯                 value_start to value_end
	 *                             ╰────────╯      important_start to important_end
	 *
	 * `value_tokens` is the range of token indexes, end exclusive, of the
	 * value. The `important_*` offsets are null when the declaration has no
	 * `!important` priority.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @var array<int, array{name:string, start:int, after:int, value_start:int, value_end:int, value_tokens:array{int,int}, important:bool, important_start:int|null, important_end:int|null}>
	 */
	private $declarations = array();

	/**
	 * Whether the style attribute value has been parsed.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @var bool
	 */
	private $parsed = false;

	/**
	 * Index of the current declaration, or -1 before the first declaration.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @var int
	 */
	private $current_declaration = -1;

	/**
	 * Whether the current declaration was removed and no new declaration has been selected.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @var bool
	 */
	private $current_declaration_removed = false;

	/**
	 * Constructor.
	 *
	 * `true` and `null` give an empty style.
	 *
	 * @since 7.3.0
	 *
	 * @param string|true|null $decoded_css_text Decoded CSS text value from a style attribute.
	 */
	public function __construct( $decoded_css_text ) {
		if ( true === $decoded_css_text || null === $decoded_css_text ) {
			$decoded_css_text = '';
		}

		if ( ! is_string( $decoded_css_text ) ) {
			throw new TypeError( __METHOD__ . '(): Argument #1 ($decoded_css_text) must be of type string|true|null' );
		}

		$this->style = $decoded_css_text;
	}

	/**
	 * Moves to the next declaration in the style attribute value.
	 *
	 * When a property name is provided, normal CSS properties are matched
	 * ASCII-case-insensitively while custom properties are matched exactly.
	 *
	 * @since 7.3.0
	 *
	 * @param string|null $property_name Optional declaration property name to match.
	 * @return bool Whether a matching declaration was found.
	 */
	public function next_declaration( ?string $property_name = null ): bool {
		$this->ensure_parsed();

		for ( $i = $this->current_declaration + 1; $i < count( $this->declarations ); $i++ ) {
			if (
				null === $property_name ||
				$this->matches_property_name( $this->declarations[ $i ]['name'], $property_name )
			) {
				$this->current_declaration         = $i;
				$this->current_declaration_removed = false;
				return true;
			}
		}

		$this->current_declaration         = count( $this->declarations );
		$this->current_declaration_removed = false;
		return false;
	}

	/**
	 * Gets the current declaration's property name.
	 *
	 * @since 7.3.0
	 *
	 * @return string|null Property name, or null when not on a declaration.
	 */
	public function get_property_name(): ?string {
		$declaration = $this->get_current_declaration();
		return null === $declaration ? null : $declaration['name'];
	}

	/**
	 * Indicates whether the current declaration has an !important priority.
	 *
	 * @since 7.3.0
	 *
	 * @return bool|null Whether the current declaration has an !important priority,
	 *                   or null when not on a declaration.
	 */
	public function is_important(): ?bool {
		$declaration = $this->get_current_declaration();
		return null === $declaration ? null : $declaration['important'];
	}

	/**
	 * Gets the current declaration's value as a list of CSS tokens.
	 *
	 * The list covers the declaration value only. Leading and trailing
	 * whitespace and comments and the `!important` priority are excluded;
	 * whitespace and comments between value tokens are included. The list is
	 * empty when the declaration has no value tokens, as in `color:;`.
	 *
	 *     color:  red  blue !important;
	 *             ╰───┬───╯
	 *     The value tokens are `red`, a whitespace token and `blue`.
	 *
	 * Token offsets index the current style text, which
	 * {@see WP_HTML_Style_Attribute_Processor::get_updated_style()} returns.
	 * Before any mutation that is the text passed to the constructor.
	 *
	 * @since 7.3.0
	 *
	 * @return WP_CSS_Token[]|null Value tokens, or null when not on a declaration.
	 */
	public function get_value_tokens(): ?array {
		$declaration = $this->get_current_declaration();
		if ( null === $declaration ) {
			return null;
		}

		list( $start, $end ) = $declaration['value_tokens'];
		return array_slice( $this->tokens, $start, $end - $start );
	}

	/**
	 * Serializes the current declaration.
	 *
	 * The property name is escaped as an identifier, and the value is written
	 * from its tokens: comments are dropped, each whitespace run becomes one
	 * space, and blocks left open at the end of the style are closed. The
	 * result is `name: value;` or `name: value !important;`, as in CSSOM, and
	 * CSS reads it as the same declaration.
	 *
	 * @since 7.3.0
	 *
	 * @see WP_CSS_Token_Serializer::serialize()
	 *
	 * @return string|null Declaration text, or null when not on a declaration.
	 */
	public function serialize_declaration(): ?string {
		$declaration = $this->get_current_declaration();
		if ( null === $declaration ) {
			return null;
		}

		return $this->format_declaration(
			WP_CSS_Token_Serializer::escape_ident( $declaration['name'] ),
			WP_CSS_Token_Serializer::serialize( $this->get_value_tokens() ),
			$declaration['important']
		);
	}

	/**
	 * Sets the value of the current declaration.
	 *
	 * The declaration is written as serialize_declaration() writes it: the
	 * name is escaped as an identifier and the value is written from its
	 * tokens.
	 *
	 * @since 7.3.0
	 *
	 * @param string    $value     CSS declaration value, without the property name.
	 * @param bool|null $important Optional. Whether the declaration should be important.
	 *                             Defaults to preserving the current priority.
	 * @return bool Whether the current declaration was updated.
	 */
	public function set_value( string $value, ?bool $important = null ): bool {
		$declaration = $this->get_current_declaration();
		if ( null === $declaration ) {
			return false;
		}

		if ( ! $this->is_valid_declaration_value( $value ) ) {
			return false;
		}

		$is_important = null === $important ? $declaration['important'] : $important;
		$text         = $this->format_declaration( WP_CSS_Token_Serializer::escape_ident( $declaration['name'] ), $this->serialize_value( $value ), $is_important );
		$parsed_value = $this->get_parsed_declaration_value( $text );
		if ( null === $parsed_value ) {
			return false;
		}

		$updated = $this->verify_current_declaration_replacement(
			$declaration,
			$declaration['start'],
			$declaration['after'] - $declaration['start'],
			$text,
			$is_important,
			$parsed_value
		);
		if ( null === $updated ) {
			return false;
		}

		$this->apply_update( $updated, $this->current_declaration );

		return true;
	}

	/**
	 * Sets the !important priority of the current declaration.
	 *
	 * @since 7.3.0
	 *
	 * @param bool $important Whether the declaration should be important.
	 * @return bool Whether the current declaration priority is set as requested.
	 */
	public function set_important( bool $important ): bool {
		$declaration = $this->get_current_declaration();
		if ( null === $declaration ) {
			return false;
		}

		if ( $important === $declaration['important'] ) {
			return true;
		}

		if ( $important ) {
			if ( $declaration['value_start'] >= $declaration['value_end'] ) {
				return false;
			}

			$insert_text = ' !important';
			$updated     = $this->verify_current_declaration_replacement(
				$declaration,
				$declaration['value_end'],
				0,
				$insert_text,
				true
			);
			if ( null === $updated ) {
				$eof_repair    = strlen( $this->style ) === $declaration['after']
					? $this->get_eof_repair( $declaration['after'] )
					: null;
				$current_value = substr( $this->style, $declaration['value_start'], $declaration['after'] - $declaration['value_start'] );

				if ( null === $eof_repair || '' === $eof_repair ) {
					return false;
				}

				$updated = $this->verify_current_declaration_replacement(
					$declaration,
					$declaration['after'],
					0,
					$eof_repair . $insert_text,
					true,
					trim( $current_value . $eof_repair, self::WHITESPACE )
				);
			}
		} else {
			if ( null === $declaration['important_start'] || null === $declaration['important_end'] ) {
				return false;
			}

			$updated = $this->verify_current_declaration_replacement(
				$declaration,
				$declaration['important_start'],
				$declaration['important_end'] - $declaration['important_start'],
				'',
				false
			);
		}

		if ( null === $updated ) {
			return false;
		}

		$this->apply_update( $updated, $this->current_declaration );

		return true;
	}

	/**
	 * Removes the current declaration from the style attribute value.
	 *
	 * Only the current declaration is removed. Other declarations with the same
	 * property name remain in place.
	 *
	 * Each call parses the whole style text again, so removing most of the
	 * declarations of a long style one at a time is slow. To keep some
	 * declarations, join the {@see WP_HTML_Style_Attribute_Processor::serialize_declaration()}
	 * output of the ones to keep instead.
	 *
	 * @since 7.3.0
	 *
	 * @return bool Whether the current declaration was removed.
	 */
	public function remove_declaration(): bool {
		$declaration = $this->get_current_declaration();
		if ( null === $declaration ) {
			return false;
		}

		$remove_start = $this->get_offset_before_preceding_whitespace( $declaration['start'] );
		$remove_end   = $this->get_offset_after_following_whitespace( $declaration['after'] );
		$replacement  = ( $remove_start > 0 && $remove_end < strlen( $this->style ) ) ? ' ' : '';

		$updated = $this->verify_declaration_removal( $this->current_declaration, $remove_start, $remove_end - $remove_start, $replacement );
		if ( null === $updated ) {
			return false;
		}

		$this->apply_update( $updated, $this->current_declaration - 1, true );

		return true;
	}

	/**
	 * Appends a declaration to the style attribute value.
	 *
	 * Appending does not remove or replace existing declarations with the same
	 * property name. The declaration is written as serialize_declaration()
	 * writes it: the name is escaped as an identifier and the value is
	 * written from its tokens.
	 *
	 * @since 7.3.0
	 *
	 * @param string $property_name CSS property name.
	 * @param string $value         CSS declaration value, without the property name.
	 * @param bool   $important     Optional. Whether the declaration should be important. Default false.
	 * @return bool Whether the declaration was appended.
	 */
	public function append_declaration( string $property_name, string $value, bool $important = false ): bool {
		if (
			'' === trim( $value, self::WHITESPACE ) ||
			! $this->is_valid_property_name( $property_name ) ||
			! $this->is_valid_declaration_value( $value )
		) {
			return false;
		}

		$this->ensure_parsed();

		$old_declaration_count = count( $this->declarations );
		$trimmed_style         = rtrim( $this->style, self::WHITESPACE );
		$insert_at             = strlen( $trimmed_style );
		$repair_at             = strlen( $this->style );
		$eof_repair            = $this->get_eof_repair( $repair_at );
		if ( null === $eof_repair ) {
			return false;
		}

		$separator        = $this->get_append_separator( $insert_at );
		$name             = $this->is_custom_property_name( $property_name ) ? $property_name : strtolower( $property_name );
		$declaration_text = $this->format_declaration( WP_CSS_Token_Serializer::escape_ident( $name ), $this->serialize_value( $value ), $important );
		$parsed_value     = $this->get_parsed_declaration_value( $declaration_text );
		if ( null === $parsed_value ) {
			return false;
		}

		$updated = $this->verify_append( $repair_at, $eof_repair, $separator, $declaration_text, $old_declaration_count, $name, $parsed_value, $important );
		if ( null === $updated ) {
			return false;
		}

		$append_after_exhausted_cursor = $this->current_declaration >= $old_declaration_count;
		$current_after_append          = $append_after_exhausted_cursor
			? $old_declaration_count - 1
			: $this->current_declaration;

		$this->apply_update(
			$updated,
			$current_after_append,
			$this->current_declaration_removed || $append_after_exhausted_cursor
		);

		return true;
	}

	/**
	 * Returns the updated decoded CSS text value for the style attribute.
	 *
	 * @since 7.3.0
	 *
	 * @return string Updated decoded CSS text value for the style attribute.
	 */
	public function get_updated_style(): string {
		return $this->style;
	}

	/**
	 * Parses CSS tokens and declarations from the style attribute value.
	 *
	 * @since 7.3.0
	 * @ignore
	 */
	private function parse(): void {
		$this->tokens       = array();
		$this->declarations = array();

		$processor = new WP_CSS_Token_Processor( $this->style );

		while ( $processor->next_token() ) {
			$this->tokens[] = $processor->get_token();
		}

		$this->parse_declaration_list();
		$this->parsed = true;
	}

	/**
	 * Lazily parses the style attribute value when declaration access needs it.
	 *
	 * @since 7.3.0
	 * @ignore
	 */
	private function ensure_parsed(): void {
		if ( $this->parsed ) {
			return;
		}

		$this->parse();
	}

	/**
	 * Parses the token list according to the CSS declaration-list shape.
	 *
	 * @since 7.3.0
	 * @ignore
	 */
	private function parse_declaration_list(): void {
		$index = 0;
		$count = count( $this->tokens );

		while ( $index < $count ) {
			$index = $this->skip_ignored_tokens( $index );

			while ( $index < $count && WP_CSS_Token_Processor::TOKEN_SEMICOLON === $this->tokens[ $index ]->get_type() ) {
				++$index;
				$index = $this->skip_ignored_tokens( $index );
			}

			if ( $index >= $count ) {
				break;
			}

			if ( WP_CSS_Token_Processor::TOKEN_AT_KEYWORD === $this->tokens[ $index ]->get_type() ) {
				$index = $this->consume_at_rule( $index );
				continue;
			}

			list( $declaration_end, $after_declaration, $has_semicolon ) = $this->consume_declaration_segment( $index );
			$declaration = $this->parse_declaration_segment( $index, $declaration_end, $after_declaration );

			if ( null !== $declaration ) {
				$this->declarations[] = $declaration;
			}

			$index = $declaration_end + ( $has_semicolon ? 1 : 0 );
		}
	}

	/**
	 * Consumes an at-rule and returns the token index after it.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $index At-keyword token index.
	 * @return int Token index after the at-rule.
	 */
	private function consume_at_rule( int $index ): int {
		$count = count( $this->tokens );
		++$index;

		while ( $index < $count ) {
			$token = $this->tokens[ $index ];

			if ( WP_CSS_Token_Processor::TOKEN_SEMICOLON === $token->get_type() ) {
				return $index + 1;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token->get_type() ) {
				return $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
			}

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			++$index;
		}

		return $count;
	}

	/**
	 * Consumes a simple block.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int    $index        Token index for the opening token.
	 * @param string $closing_type Expected closing token type.
	 * @return int Token index after the block.
	 */
	private function consume_simple_block( int $index, string $closing_type ): int {
		$count = count( $this->tokens );
		++$index;

		while ( $index < $count ) {
			$token = $this->tokens[ $index ];

			if ( $closing_type === $token->get_type() ) {
				return $index + 1;
			}

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
				continue;
			}

			++$index;
		}

		return $count;
	}

	/**
	 * Consumes a declaration candidate up to the next top-level semicolon or EOF.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $index Starting token index.
	 * @return array{int,int,bool} End token index, byte offset after candidate, and whether it ended in a semicolon.
	 */
	private function consume_declaration_segment( int $index ): array {
		$count = count( $this->tokens );

		while ( $index < $count ) {
			$token = $this->tokens[ $index ];

			if ( WP_CSS_Token_Processor::TOKEN_SEMICOLON === $token->get_type() ) {
				return array( $index, self::get_token_end( $token ), true );
			}

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
				continue;
			}

			++$index;
		}

		$after = empty( $this->tokens ) ? strlen( $this->style ) : self::get_token_end( $this->tokens[ $count - 1 ] );
		return array( $count, $after, false );
	}

	/**
	 * Parses a declaration from a token segment.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $start_index First token index in the segment.
	 * @param int $end_index   Token index after the segment.
	 * @param int $after       Byte offset after the declaration.
	 * @return array{name:string, start:int, after:int, value_start:int, value_end:int, value_tokens:array{int,int}, important:bool, important_start:int|null, important_end:int|null}|null
	 */
	private function parse_declaration_segment( int $start_index, int $end_index, int $after ): ?array {
		$index = $this->skip_ignored_tokens( $start_index, $end_index );
		if ( $index >= $end_index || WP_CSS_Token_Processor::TOKEN_IDENT !== $this->tokens[ $index ]->get_type() ) {
			return null;
		}

		$name_token = $this->tokens[ $index ];
		$name       = $name_token->get_value();
		if ( null === $name ) {
			return null;
		}

		$name = $this->is_custom_property_name( $name ) ? $name : strtolower( $name );

		++$index;
		$index = $this->skip_ignored_tokens( $index, $end_index );

		if ( $index >= $end_index || WP_CSS_Token_Processor::TOKEN_COLON !== $this->tokens[ $index ]->get_type() ) {
			return null;
		}

		$colon_token       = $this->tokens[ $index ];
		$value_start_index = $this->skip_ignored_tokens( $index + 1, $end_index );
		$value_end_index   = $this->trim_ignored_tokens( $value_start_index, $end_index );
		$important         = false;
		$important_start   = null;
		$important_end     = null;
		$top_level_tokens  = $this->get_top_level_non_ignored_token_indexes( $value_start_index, $value_end_index );
		$top_level_count   = count( $top_level_tokens );
		$last_value_index  = $top_level_count > 0 ? $top_level_tokens[ $top_level_count - 1 ] : null;
		$before_last_index = $top_level_count > 1 ? $top_level_tokens[ $top_level_count - 2 ] : null;

		if (
			null !== $last_value_index &&
			null !== $before_last_index &&
			WP_CSS_Token_Processor::TOKEN_IDENT === $this->tokens[ $last_value_index ]->get_type() &&
			0 === strcasecmp( 'important', (string) $this->tokens[ $last_value_index ]->get_value() ) &&
			WP_CSS_Token_Processor::TOKEN_DELIM === $this->tokens[ $before_last_index ]->get_type() &&
			'!' === $this->tokens[ $before_last_index ]->get_value()
		) {
			$important       = true;
			$important_start = $this->tokens[ $before_last_index ]->get_start();
			$important_end   = self::get_token_end( $this->tokens[ $last_value_index ] );
			$value_end_index = $this->trim_ignored_tokens( $value_start_index, $before_last_index );
		}

		if ( $value_start_index >= $value_end_index ) {
			$value_start = self::get_token_end( $colon_token );
			$value_end   = self::get_token_end( $colon_token );
		} else {
			$value_start = $this->tokens[ $value_start_index ]->get_start();
			$value_end   = self::get_token_end( $this->tokens[ $value_end_index - 1 ] );
		}

		if ( ! $this->is_parsed_value_valid( $value_start_index, $value_end_index, $this->is_custom_property_name( $name ) ) ) {
			return null;
		}

		return array(
			'name'            => $name,
			'start'           => $name_token->get_start(),
			'after'           => $after,
			'value_start'     => $value_start,
			'value_end'       => $value_end,
			'value_tokens'    => array( $value_start_index, $value_end_index ),
			'important'       => $important,
			'important_start' => $important_start,
			'important_end'   => $important_end,
		);
	}

	/**
	 * Gets top-level non-ignored token indexes from a token range.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $start Start token index.
	 * @param int $end   End token index.
	 * @return int[] Top-level non-ignored token indexes.
	 */
	private function get_top_level_non_ignored_token_indexes( int $start, int $end ): array {
		$top_level_tokens = array();
		$index            = $start;

		while ( $index < $end ) {
			$token = $this->tokens[ $index ];

			if ( $this->is_ignored_token( $token ) ) {
				++$index;
				continue;
			}

			$top_level_tokens[] = $index;

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
				continue;
			}

			++$index;
		}

		return $top_level_tokens;
	}

	/**
	 * Skips ignored CSS tokens.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int      $index Token index.
	 * @param int|null $end   Optional token index at which to stop.
	 * @return int Token index after ignored tokens.
	 */
	private function skip_ignored_tokens( int $index, ?int $end = null ): int {
		$end = null === $end ? count( $this->tokens ) : $end;

		while ( $index < $end && $this->is_ignored_token( $this->tokens[ $index ] ) ) {
			++$index;
		}

		return $index;
	}

	/**
	 * Trims ignored CSS tokens from the end of a token range.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $start Start token index.
	 * @param int $end   End token index.
	 * @return int Trimmed end token index.
	 */
	private function trim_ignored_tokens( int $start, int $end ): int {
		while ( $end > $start && $this->is_ignored_token( $this->tokens[ $end - 1 ] ) ) {
			--$end;
		}

		return $end;
	}

	/**
	 * Checks whether a token is ignored by declaration-list grammar.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param WP_CSS_Token $token Token.
	 * @return bool Whether the token is ignored.
	 */
	private function is_ignored_token( WP_CSS_Token $token ): bool {
		return (
			WP_CSS_Token_Processor::TOKEN_WHITESPACE === $token->get_type() ||
			WP_CSS_Token_Processor::TOKEN_COMMENT === $token->get_type()
		);
	}

	/**
	 * Gets the byte offset after a token.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param WP_CSS_Token $token Token.
	 * @return int Byte offset after the token.
	 */
	private static function get_token_end( WP_CSS_Token $token ): int {
		return $token->get_start() + $token->get_length();
	}

	/**
	 * Gets the byte offset before contiguous whitespace ending at an offset.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $offset Byte offset.
	 * @return int Offset before preceding whitespace.
	 */
	private function get_offset_before_preceding_whitespace( int $offset ): int {
		while ( $offset > 0 && false !== strpos( self::WHITESPACE, $this->style[ $offset - 1 ] ) ) {
			--$offset;
		}

		return $offset;
	}

	/**
	 * Gets the byte offset after contiguous whitespace beginning at an offset.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $offset Byte offset.
	 * @return int Offset after following whitespace.
	 */
	private function get_offset_after_following_whitespace( int $offset ): int {
		$length = strlen( $this->style );

		while ( $offset < $length && false !== strpos( self::WHITESPACE, $this->style[ $offset ] ) ) {
			++$offset;
		}

		return $offset;
	}

	/**
	 * Gets the current declaration metadata.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @return array{name:string, start:int, after:int, value_start:int, value_end:int, value_tokens:array{int,int}, important:bool, important_start:int|null, important_end:int|null}|null
	 */
	private function get_current_declaration(): ?array {
		if (
			$this->current_declaration_removed ||
			$this->current_declaration < 0 ||
			! isset( $this->declarations[ $this->current_declaration ] )
		) {
			return null;
		}

		return $this->declarations[ $this->current_declaration ];
	}

	/**
	 * Parses the style with a replacement and checks that it preserves the current declaration's structure.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param array{name:string, start:int, after:int, value_start:int, value_end:int, value_tokens:array{int,int}, important:bool, important_start:int|null, important_end:int|null} $declaration        Current declaration metadata.
	 * @param int                                                                                                                                                                                                                           $start              Byte offset at which to start the replacement.
	 * @param int                                                                                                                                                                                                                           $length             Number of bytes to replace.
	 * @param string                                                                                                                                                                                                                        $text               Replacement text.
	 * @param bool                                                                                                                                                                                                                          $expected_important Expected important flag after replacement.
	 * @param string|null                                                                                                                                                                                                                   $expected_value     Optional expected value source after replacement.
	 * @return self|null Parsed processor for the replaced style, or null when the replacement is not safe.
	 */
	private function verify_current_declaration_replacement( array $declaration, int $start, int $length, string $text, bool $expected_important, ?string $expected_value = null ): ?self {
		$candidate_style = substr( $this->style, 0, $start ) . $text . substr( $this->style, $start + $length );
		$candidate       = new self( $candidate_style );
		$candidate->ensure_parsed();

		if (
			count( $candidate->declarations ) !== count( $this->declarations ) ||
			! isset( $candidate->declarations[ $this->current_declaration ] )
		) {
			return null;
		}

		$updated_declaration = $candidate->declarations[ $this->current_declaration ];
		if (
			$updated_declaration['name'] !== $declaration['name'] ||
			$updated_declaration['important'] !== $expected_important
		) {
			return null;
		}

		$old_value = trim(
			substr( $this->style, $declaration['value_start'], $declaration['value_end'] - $declaration['value_start'] ),
			self::WHITESPACE
		);
		$new_value = trim(
			substr( $candidate_style, $updated_declaration['value_start'], $updated_declaration['value_end'] - $updated_declaration['value_start'] ),
			self::WHITESPACE
		);

		$is_safe = null === $expected_value ? $old_value === $new_value : $expected_value === $new_value;

		return $is_safe ? $candidate : null;
	}

	/**
	 * Parses the style with a declaration removed and checks that the remaining structure is preserved.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int    $removed_declaration Removed declaration index.
	 * @param int    $start               Byte offset at which to start the replacement.
	 * @param int    $length              Number of bytes to replace.
	 * @param string $text                Replacement text.
	 * @return self|null Parsed processor for the style without the declaration, or null when the removal is not safe.
	 */
	private function verify_declaration_removal( int $removed_declaration, int $start, int $length, string $text ): ?self {
		$candidate_style = substr( $this->style, 0, $start ) . $text . substr( $this->style, $start + $length );
		$candidate       = new self( $candidate_style );
		$candidate->ensure_parsed();

		if ( count( $candidate->declarations ) !== count( $this->declarations ) - 1 ) {
			return null;
		}

		$candidate_index = 0;
		foreach ( $this->declarations as $index => $declaration ) {
			if ( $removed_declaration === $index ) {
				continue;
			}

			if ( ! isset( $candidate->declarations[ $candidate_index ] ) ) {
				return null;
			}

			$candidate_declaration = $candidate->declarations[ $candidate_index ];
			$value                 = trim(
				substr( $this->style, $declaration['value_start'], $declaration['value_end'] - $declaration['value_start'] ),
				self::WHITESPACE
			);
			$candidate_value       = trim(
				substr( $candidate_style, $candidate_declaration['value_start'], $candidate_declaration['value_end'] - $candidate_declaration['value_start'] ),
				self::WHITESPACE
			);

			if (
				$candidate_declaration['name'] !== $declaration['name'] ||
				$candidate_declaration['important'] !== $declaration['important'] ||
				$candidate_value !== $value
			) {
				return null;
			}

			++$candidate_index;
		}

		return $candidate;
	}

	/**
	 * Gets the parsed value source from a serialized declaration.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param string $declaration_text Serialized declaration text.
	 * @return string|null Parsed declaration value, or null if parsing failed.
	 */
	private function get_parsed_declaration_value( string $declaration_text ): ?string {
		$processor = new self( $declaration_text );
		$processor->ensure_parsed();

		if ( 1 !== count( $processor->declarations ) ) {
			return null;
		}

		$declaration = $processor->declarations[0];
		return trim(
			substr( $declaration_text, $declaration['value_start'], $declaration['value_end'] - $declaration['value_start'] ),
			self::WHITESPACE
		);
	}

	/**
	 * Replaces the style text, tokens and declarations with those of a verified update.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param self $updated                     Parsed processor for the updated style.
	 * @param int  $current_declaration         Declaration index to keep before the next cursor advance.
	 * @param bool $current_declaration_removed Optional. Whether the current declaration was removed.
	 */
	private function apply_update( self $updated, int $current_declaration, bool $current_declaration_removed = false ): void {
		$this->style        = $updated->style;
		$this->tokens       = $updated->tokens;
		$this->declarations = $updated->declarations;
		$this->parsed       = true;

		$this->current_declaration         = $current_declaration;
		$this->current_declaration_removed = $current_declaration_removed;

		if ( $this->current_declaration >= count( $this->declarations ) ) {
			$this->current_declaration = count( $this->declarations );
		}
	}

	/**
	 * Gets the separator to use before an appended declaration.
	 *
	 * Comments and whitespace are CSS parser trivia. They do not determine
	 * whether the existing declaration list needs a semicolon before appending.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $insert_at Byte offset at which the declaration will be inserted.
	 * @return string Separator text.
	 */
	private function get_append_separator( int $insert_at ): string {
		$last_token = $this->get_last_top_level_non_ignored_token_before( $insert_at );

		if ( null === $last_token ) {
			return $insert_at > 0 ? ' ' : '';
		}

		return WP_CSS_Token_Processor::TOKEN_SEMICOLON === $last_token->get_type() ? ' ' : '; ';
	}

	/**
	 * Gets the last top-level non-ignored token before a byte offset.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $insert_at Byte offset before which to scan.
	 * @return WP_CSS_Token|null Token, or null when there is none.
	 */
	private function get_last_top_level_non_ignored_token_before( int $insert_at ): ?WP_CSS_Token {
		$count      = count( $this->tokens );
		$index      = 0;
		$last_token = null;

		while ( $index < $count ) {
			$token = $this->tokens[ $index ];

			if ( $token->get_start() >= $insert_at ) {
				break;
			}

			if ( $this->is_ignored_token( $token ) ) {
				++$index;
				continue;
			}

			$last_token = $token;

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token->get_type() ) {
				$index = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
				continue;
			}

			++$index;
		}

		return $last_token;
	}

	/**
	 * Gets required closing delimiters before appending at EOF.
	 *
	 * CSS closes unterminated strings, comments, functions and simple blocks at
	 * EOF. Appending must materialize those closers before adding a new
	 * declaration.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int $insert_at Byte offset at which the declaration will be inserted.
	 * @return string|null Closing delimiters, or null when repair cannot be precise.
	 */
	private function get_eof_repair( int $insert_at ): ?string {
		$stack        = array();
		$token_closer = '';

		foreach ( $this->tokens as $token ) {
			if ( $token->get_start() >= $insert_at ) {
				break;
			}

			if ( self::get_token_end( $token ) > $insert_at ) {
				return null;
			}

			switch ( $token->get_type() ) {
				case WP_CSS_Token_Processor::TOKEN_BAD_STRING:
				case WP_CSS_Token_Processor::TOKEN_BAD_URL:
					return null;

				case WP_CSS_Token_Processor::TOKEN_COMMENT:
					if ( ! $token->is_terminated() ) {
						$token_closer = '*/';
					}
					break;

				case WP_CSS_Token_Processor::TOKEN_STRING:
					if ( ! $token->is_terminated() ) {
						// The string ends with the quote it starts with.
						$token_closer = $this->style[ $token->get_start() ];
					}
					break;

				case WP_CSS_Token_Processor::TOKEN_FUNCTION:
				case WP_CSS_Token_Processor::TOKEN_LEFT_PAREN:
					$stack[] = ')';
					break;

				case WP_CSS_Token_Processor::TOKEN_URL:
					if ( ! $token->is_terminated() ) {
						$stack[] = ')';
					}
					break;

				case WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET:
					$stack[] = ']';
					break;

				case WP_CSS_Token_Processor::TOKEN_LEFT_BRACE:
					$stack[] = '}';
					break;

				case WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN:
					if ( ! empty( $stack ) && ')' === end( $stack ) ) {
						array_pop( $stack );
					}
					break;

				case WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET:
					if ( ! empty( $stack ) && ']' === end( $stack ) ) {
						array_pop( $stack );
					}
					break;

				case WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE:
					if ( ! empty( $stack ) && '}' === end( $stack ) ) {
						array_pop( $stack );
					}
					break;
			}
		}

		return $token_closer . implode( '', array_reverse( $stack ) );
	}

	/**
	 * Parses the style with a declaration appended and checks that it parses as a new declaration.
	 *
	 * Malformed existing declarations can leave the append point inside an
	 * unclosed component value. In that case preserving the original text and
	 * appending a top-level declaration are incompatible.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int    $insert_at             Byte offset at which the declaration will be inserted.
	 * @param string $eof_repair            EOF repair text.
	 * @param string $separator             Separator text.
	 * @param string $declaration_text      Serialized declaration text.
	 * @param int    $old_declaration_count Number of declarations before appending.
	 * @param string $expected_name          Expected parsed property name.
	 * @param string $expected_value         Expected parsed value source.
	 * @param bool   $expected_important     Expected important flag.
	 * @return self|null Parsed processor for the style with the declaration, or null when it does not parse as a new declaration.
	 */
	private function verify_append( int $insert_at, string $eof_repair, string $separator, string $declaration_text, int $old_declaration_count, string $expected_name, string $expected_value, bool $expected_important ): ?self {
		$expected_start  = $insert_at + strlen( $eof_repair ) + strlen( $separator );
		$expected_after  = $expected_start + strlen( $declaration_text );
		$candidate_style = substr( $this->style, 0, $insert_at ) . $eof_repair . $separator . $declaration_text . substr( $this->style, $insert_at );
		$candidate       = new self( $candidate_style );
		$candidate->ensure_parsed();

		if ( count( $candidate->declarations ) !== $old_declaration_count + 1 ) {
			return null;
		}

		foreach ( $candidate->declarations as $declaration ) {
			if ( $expected_start === $declaration['start'] && $expected_after === $declaration['after'] ) {
				$parsed_value = trim(
					substr( $candidate_style, $declaration['value_start'], $declaration['value_end'] - $declaration['value_start'] ),
					self::WHITESPACE
				);

				$is_parseable = (
					$expected_name === $declaration['name'] &&
					$expected_value === $parsed_value &&
					$expected_important === $declaration['important']
				);

				return $is_parseable ? $candidate : null;
			}
		}

		return null;
	}

	/**
	 * Formats a declaration as `name: value;` or `name: value !important;`.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param string $property_name CSS property name text.
	 * @param string $value         CSS declaration value text.
	 * @param bool   $important     Whether to append !important.
	 * @return string Declaration text.
	 */
	private function format_declaration( string $property_name, string $value, bool $important ): string {
		return $property_name . ': ' . $value . ( $important ? ' !important' : '' ) . ';';
	}

	/**
	 * Writes a declaration value from its tokens.
	 *
	 * Whitespace and comments before and after the value are removed, and the
	 * rest is written as serialize_declaration() writes a value.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param string $value CSS declaration value.
	 * @return string Value text.
	 */
	private function serialize_value( string $value ): string {
		$processor = new WP_CSS_Token_Processor( $value );
		$tokens    = array();
		while ( $processor->next_token() ) {
			$tokens[] = $processor->get_token();
		}

		$start = 0;
		$end   = count( $tokens );
		while ( $start < $end && $this->is_ignored_token( $tokens[ $start ] ) ) {
			++$start;
		}
		while ( $end > $start && $this->is_ignored_token( $tokens[ $end - 1 ] ) ) {
			--$end;
		}

		return WP_CSS_Token_Serializer::serialize( array_slice( $tokens, $start, $end - $start ) );
	}

	/**
	 * Checks whether two property names match.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param string $actual Property name from a declaration.
	 * @param string $query  Queried property name.
	 * @return bool Whether the property names match.
	 */
	private function matches_property_name( string $actual, string $query ): bool {
		if ( $this->is_custom_property_name( $actual ) || $this->is_custom_property_name( $query ) ) {
			return $actual === $query;
		}

		return 0 === strcasecmp( $actual, $query );
	}

	/**
	 * Checks whether CSS keeps a parsed declaration value, excluding its priority.
	 *
	 * CSS ignores a declaration whose value contains a bad string, a bad URL, an
	 * unmatched closing token, or a top-level `!`. A `{}` block must be the whole
	 * value unless the property is a custom property. Blocks left open at EOF are
	 * closed by CSS and are valid.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param int  $start              First value token index.
	 * @param int  $end                Token index after the value.
	 * @param bool $is_custom_property Whether the declaration is a custom property.
	 * @return bool Whether CSS keeps the value.
	 */
	private function is_parsed_value_valid( int $start, int $end, bool $is_custom_property ): bool {
		$stack               = array();
		$has_top_level_block = false;
		$has_other_top_level = false;

		for ( $index = $start; $index < $end; $index++ ) {
			$token = $this->tokens[ $index ];
			$type  = $token->get_type();

			if ( empty( $stack ) && ! $this->is_ignored_token( $token ) ) {
				if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $type ) {
					$has_top_level_block = true;
				} else {
					$has_other_top_level = true;
				}

				if ( WP_CSS_Token_Processor::TOKEN_DELIM === $type && '!' === $token->get_value() ) {
					return false;
				}
			}

			switch ( $type ) {
				case WP_CSS_Token_Processor::TOKEN_BAD_STRING:
				case WP_CSS_Token_Processor::TOKEN_BAD_URL:
					return false;

				case WP_CSS_Token_Processor::TOKEN_FUNCTION:
				case WP_CSS_Token_Processor::TOKEN_LEFT_PAREN:
					$stack[] = WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN;
					break;

				case WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET:
					$stack[] = WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET;
					break;

				case WP_CSS_Token_Processor::TOKEN_LEFT_BRACE:
					$stack[] = WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE;
					break;

				case WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN:
				case WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET:
				case WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE:
					if ( empty( $stack ) || array_pop( $stack ) !== $type ) {
						return false;
					}
					break;
			}
		}

		return $is_custom_property || ! ( $has_top_level_block && $has_other_top_level );
	}

	/**
	 * Checks whether a property name is a custom property.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param string $property_name Property name.
	 * @return bool Whether this is a custom property name.
	 */
	private function is_custom_property_name( string $property_name ): bool {
		return strlen( $property_name ) >= 2 && '--' === substr( $property_name, 0, 2 );
	}

	/**
	 * Checks whether a CSS property name is syntactically valid.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param string $property_name CSS property name.
	 * @return bool Whether the property name is valid.
	 */
	private function is_valid_property_name( string $property_name ): bool {
		if (
			'' === $property_name ||
			false !== strpos( $property_name, '\\' ) ||
			'--' === $property_name
		) {
			return false;
		}

		$processor = new WP_CSS_Token_Processor( $property_name );
		if ( ! $processor->next_token() ) {
			return false;
		}

		if (
			WP_CSS_Token_Processor::TOKEN_IDENT !== $processor->get_token_type() ||
			$processor->get_token_start() !== 0 ||
			$processor->get_token_length() !== strlen( $property_name )
		) {
			return false;
		}

		if ( $processor->next_token() ) {
			return false;
		}

		$sentinel_processor = new WP_CSS_Token_Processor( $property_name . ': sentinel' );
		if ( ! $sentinel_processor->next_token() ) {
			return false;
		}

		if (
			WP_CSS_Token_Processor::TOKEN_IDENT !== $sentinel_processor->get_token_type() ||
			$sentinel_processor->get_token_start() !== 0 ||
			$sentinel_processor->get_token_length() !== strlen( $property_name )
		) {
			return false;
		}

		return (
			$sentinel_processor->next_token() &&
			WP_CSS_Token_Processor::TOKEN_COLON === $sentinel_processor->get_token_type()
		);
	}

	/**
	 * Checks whether a CSS declaration value is syntactically safe to insert.
	 *
	 * @since 7.3.0
	 * @ignore
	 *
	 * @param string $value CSS declaration value.
	 * @return bool Whether the value is valid.
	 */
	private function is_valid_declaration_value( string $value ): bool {
		if ( '' === trim( $value, self::WHITESPACE ) ) {
			return false;
		}

		$sentinel_property = '--wp-style-attribute-processor-sentinel';
		$processor         = new WP_CSS_Token_Processor( $value . ';' . $sentinel_property . ':1' );

		$stack            = array();
		$top_level_tokens = array();
		$found_sentinel   = false;

		while ( $processor->next_token() ) {
			$type = $processor->get_token_type();

			if ( empty( $stack ) && WP_CSS_Token_Processor::TOKEN_WHITESPACE !== $type && WP_CSS_Token_Processor::TOKEN_COMMENT !== $type ) {
				if ( WP_CSS_Token_Processor::TOKEN_SEMICOLON === $type ) {
					if ( strlen( $value ) !== $processor->get_token_start() ) {
						return false;
					}

					$found_sentinel = true;
					break;
				}

				$top_level_tokens[] = array(
					'type'  => $type,
					'value' => $processor->get_token()->get_value(),
				);
			}

			switch ( $type ) {
				case WP_CSS_Token_Processor::TOKEN_SEMICOLON:
					if ( empty( $stack ) ) {
						return false;
					}
					break;

				case WP_CSS_Token_Processor::TOKEN_BAD_STRING:
				case WP_CSS_Token_Processor::TOKEN_BAD_URL:
					return false;

				case WP_CSS_Token_Processor::TOKEN_FUNCTION:
				case WP_CSS_Token_Processor::TOKEN_LEFT_PAREN:
					$stack[] = WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN;
					break;

				case WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET:
					$stack[] = WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET;
					break;

				case WP_CSS_Token_Processor::TOKEN_LEFT_BRACE:
					$stack[] = WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE;
					break;

				case WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN:
				case WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET:
				case WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE:
					if ( empty( $stack ) || array_pop( $stack ) !== $processor->get_token_type() ) {
						return false;
					}
					break;
			}
		}

		if ( ! $found_sentinel || ! empty( $stack ) ) {
			return false;
		}

		if ( empty( $top_level_tokens ) ) {
			return false;
		}

		for ( $i = 1; $i < count( $top_level_tokens ); $i++ ) {
			$token        = $top_level_tokens[ $i ];
			$before_token = $top_level_tokens[ $i - 1 ];

			if (
				WP_CSS_Token_Processor::TOKEN_IDENT === $token['type'] &&
				0 === strcasecmp( 'important', (string) $token['value'] ) &&
				WP_CSS_Token_Processor::TOKEN_DELIM === $before_token['type'] &&
				'!' === $before_token['value']
			) {
				return false;
			}
		}

		return true;
	}
}
