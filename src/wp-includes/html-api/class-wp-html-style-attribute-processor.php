<?php
/**
 * HTML API: WP_HTML_Style_Attribute_Processor class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since {WP_VERSION}
 */

/**
 * Core class used to inspect and modify CSS declarations in an HTML style attribute.
 *
 * The processor operates on decoded style attribute values. Values read from
 * {@see WP_HTML_Tag_Processor::get_attribute()} can be passed directly to this
 * class, and values returned by {@see WP_HTML_Style_Attribute_Processor::get_updated_style()}
 * can be passed back to {@see WP_HTML_Tag_Processor::set_attribute()}.
 *
 * Unlike associative-array based style helpers, this processor preserves the
 * declaration list model of CSS. Multiple declarations with the same property
 * name remain separate entries and can be inspected or changed individually.
 *
 * @since {WP_VERSION}
 */
class WP_HTML_Style_Attribute_Processor {
	/**
	 * CSS whitespace characters.
	 *
	 * @var string
	 */
	const WHITESPACE = " \t\n\r\f";

	/**
	 * Decoded style attribute value.
	 *
	 * @var string
	 */
	private $style;

	/**
	 * CSS token metadata.
	 *
	 * @var array<int, array{type:string, value:string|null, start:int, length:int, end:int}>
	 */
	private $tokens = array();

	/**
	 * Parsed declarations.
	 *
	 * @var array<int, array{name:string, raw_name:string, leading_start:int, start:int, after:int, trailing_end:int, value_start:int, value_end:int, important:bool}>
	 */
	private $declarations = array();

	/**
	 * Index of the current declaration, or -1 before the first declaration.
	 *
	 * @var int
	 */
	private $current_declaration = -1;

	/**
	 * Lexical updates to apply to the original style attribute value.
	 *
	 * @var array<int, array{start:int, length:int, text:string, declaration:int|null, order:int}>
	 */
	private $lexical_updates = array();

	/**
	 * Sequence counter used to keep lexical updates in queue order.
	 *
	 * @var int
	 */
	private $lexical_update_order = 0;

	/**
	 * Constructor.
	 *
	 * @param string|bool|null $style Decoded style attribute value.
	 */
	public function __construct( $style = '' ) {
		if ( null === $style || true === $style ) {
			$style = '';
		} elseif ( ! is_string( $style ) ) {
			$style = '';
		}

		$this->style = $style;
		$this->parse();
	}

	/**
	 * Moves to the next declaration in the style attribute value.
	 *
	 * When a property name is provided, normal CSS properties are matched
	 * ASCII-case-insensitively while custom properties are matched exactly.
	 *
	 * @param string|null $property_name Optional property name to match.
	 * @return bool Whether a matching declaration was found.
	 */
	public function next_declaration( ?string $property_name = null ): bool {
		for ( $i = $this->current_declaration + 1; $i < count( $this->declarations ); $i++ ) {
			if (
				null === $property_name ||
				$this->matches_property_name( $this->declarations[ $i ]['name'], $property_name )
			) {
				$this->current_declaration = $i;
				return true;
			}
		}

		$this->current_declaration = count( $this->declarations );
		return false;
	}

	/**
	 * Gets the current declaration's property name.
	 *
	 * @return string|null Property name, or null when not on a declaration.
	 */
	public function get_property_name(): ?string {
		$declaration = $this->get_current_declaration();
		return null === $declaration ? null : $declaration['name'];
	}

	/**
	 * Gets the current declaration's CSS value.
	 *
	 * The returned value does not include a trailing !important priority.
	 *
	 * @return string|null CSS value, or null when not on a declaration.
	 */
	public function get_value(): ?string {
		$declaration = $this->get_current_declaration();
		if ( null === $declaration ) {
			return null;
		}

		return trim(
			substr( $this->style, $declaration['value_start'], $declaration['value_end'] - $declaration['value_start'] ),
			self::WHITESPACE
		);
	}

	/**
	 * Indicates whether the current declaration has an !important priority.
	 *
	 * @return bool Whether the current declaration has an !important priority.
	 */
	public function is_important(): bool {
		$declaration = $this->get_current_declaration();
		return null !== $declaration && $declaration['important'];
	}

	/**
	 * Sets the value of the current declaration.
	 *
	 * @param string    $value     CSS declaration value, without the property name.
	 * @param bool|null $important Optional. Whether the declaration should be important.
	 *                             Defaults to preserving the current priority.
	 * @return bool Whether the current declaration was updated.
	 */
	public function set_value( string $value, ?bool $important = null ): bool {
		$declaration = $this->get_current_declaration();
		if ( null === $declaration || ! $this->is_valid_declaration_value( $value ) ) {
			return false;
		}

		$is_important = null === $important ? $declaration['important'] : $important;
		$text         = $this->serialize_declaration( $declaration['raw_name'], $value, $is_important );

		$this->queue_lexical_update(
			$declaration['start'],
			$declaration['after'] - $declaration['start'],
			$text,
			$this->current_declaration
		);

		return true;
	}

	/**
	 * Removes the current declaration from the style attribute value.
	 *
	 * Only the current declaration is removed. Other declarations with the same
	 * property name remain in place.
	 *
	 * @return bool Whether the current declaration was removed.
	 */
	public function remove_declaration(): bool {
		$declaration = $this->get_current_declaration();
		if ( null === $declaration ) {
			return false;
		}

		if ( '' === trim( substr( $this->style, 0, $declaration['leading_start'] ), self::WHITESPACE ) ) {
			$remove_start = $declaration['leading_start'];
			$remove_end   = $declaration['trailing_end'];
		} else {
			$remove_start = $declaration['leading_start'];
			$remove_end   = $declaration['after'];
		}

		$this->queue_lexical_update(
			$remove_start,
			$remove_end - $remove_start,
			'',
			$this->current_declaration
		);

		return true;
	}

	/**
	 * Appends a declaration to the style attribute value.
	 *
	 * Appending does not remove or replace existing declarations with the same
	 * property name.
	 *
	 * @param string $property_name CSS property name.
	 * @param string $value         CSS declaration value, without the property name.
	 * @param bool   $important     Optional. Whether the declaration should be important. Default false.
	 * @return bool Whether the declaration was appended.
	 */
	public function append_declaration( string $property_name, string $value, bool $important = false ): bool {
		if ( ! $this->is_valid_property_name( $property_name ) || ! $this->is_valid_declaration_value( $value ) ) {
			return false;
		}

		$trimmed_style = rtrim( $this->style, self::WHITESPACE );
		$insert_at     = strlen( $trimmed_style );
		$separator     = $this->has_queued_append_at( $insert_at ) ? ' ' : '';

		if ( '' === $separator && '' !== trim( $trimmed_style, self::WHITESPACE ) ) {
			$separator = ( ';' === substr( $trimmed_style, -1 ) ) ? ' ' : '; ';
		}

		$this->queue_lexical_update(
			$insert_at,
			0,
			$separator . $this->serialize_declaration( $property_name, $value, $important ),
			null
		);

		return true;
	}

	/**
	 * Returns the updated style attribute value.
	 *
	 * @return string Updated style attribute value.
	 */
	public function get_updated_style(): string {
		if ( empty( $this->lexical_updates ) ) {
			return $this->style;
		}

		usort(
			$this->lexical_updates,
			static function ( $a, $b ) {
				if ( $a['start'] !== $b['start'] ) {
					return $a['start'] - $b['start'];
				}

				return $a['order'] - $b['order'];
			}
		);

		$updates = $this->merge_overlapping_lexical_updates( $this->lexical_updates );

		$bytes_already_copied = 0;
		$output               = '';

		foreach ( $updates as $update ) {
			$output              .= substr( $this->style, $bytes_already_copied, $update['start'] - $bytes_already_copied );
			$output              .= $update['text'];
			$bytes_already_copied = $update['start'] + $update['length'];
		}

		$output .= substr( $this->style, $bytes_already_copied );

		return $output;
	}

	/**
	 * Parses CSS tokens and declarations from the style attribute value.
	 */
	private function parse(): void {
		$processor = WP_CSS_Token_Processor::create( $this->style );
		if ( null === $processor ) {
			return;
		}

		while ( $processor->next_token() ) {
			$start          = $processor->get_token_start();
			$length         = $processor->get_token_length();
			$this->tokens[] = array(
				'type'   => $processor->get_token_type(),
				'value'  => $processor->get_token_value(),
				'start'  => $start,
				'length' => $length,
				'end'    => $start + $length,
			);
		}

		$this->parse_declaration_list();
	}

	/**
	 * Parses the token list according to the CSS declaration-list shape.
	 */
	private function parse_declaration_list(): void {
		$index                 = 0;
		$count                 = count( $this->tokens );
		$previous_item_ends_at = 0;

		while ( $index < $count ) {
			$leading_start = $previous_item_ends_at;
			$index         = $this->skip_ignored_tokens( $index );

			while ( $index < $count && WP_CSS_Token_Processor::TOKEN_SEMICOLON === $this->tokens[ $index ]['type'] ) {
				$previous_item_ends_at = $this->tokens[ $index ]['end'];
				$leading_start         = $previous_item_ends_at;
				++$index;
				$index = $this->skip_ignored_tokens( $index );
			}

			if ( $index >= $count ) {
				break;
			}

			if ( WP_CSS_Token_Processor::TOKEN_AT_KEYWORD === $this->tokens[ $index ]['type'] ) {
				list( $index, $previous_item_ends_at ) = $this->consume_at_rule( $index );
				continue;
			}

			list( $declaration_end, $after_declaration, $has_semicolon ) = $this->consume_declaration_segment( $index );
			$declaration = $this->parse_declaration_segment( $leading_start, $index, $declaration_end, $after_declaration );

			if ( null !== $declaration ) {
				$declaration['trailing_end'] = $this->get_trailing_ignored_end( $declaration_end + ( $has_semicolon ? 1 : 0 ) );
				$this->declarations[]        = $declaration;
			}

			$index                 = $declaration_end + ( $has_semicolon ? 1 : 0 );
			$previous_item_ends_at = $after_declaration;
		}
	}

	/**
	 * Consumes an at-rule and returns the token index and byte offset after it.
	 *
	 * @param int $index At-keyword token index.
	 * @return array{int,int} Token index and byte offset after the at-rule.
	 */
	private function consume_at_rule( int $index ): array {
		$count = count( $this->tokens );
		++$index;

		while ( $index < $count ) {
			$token = $this->tokens[ $index ];

			if ( WP_CSS_Token_Processor::TOKEN_SEMICOLON === $token['type'] ) {
				return array( $index + 1, $token['end'] );
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token['type'] ) {
				return $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
			}

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			++$index;
		}

		$after = empty( $this->tokens ) ? strlen( $this->style ) : $this->tokens[ $count - 1 ]['end'];
		return array( $count, $after );
	}

	/**
	 * Consumes a simple block.
	 *
	 * @param int    $index        Token index for the opening token.
	 * @param string $closing_type Expected closing token type.
	 * @return array{int,int} Token index and byte offset after the block.
	 */
	private function consume_simple_block( int $index, string $closing_type ): array {
		$count = count( $this->tokens );
		++$index;

		while ( $index < $count ) {
			$token = $this->tokens[ $index ];

			if ( $closing_type === $token['type'] ) {
				return array( $index + 1, $token['end'] );
			}

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
				continue;
			}

			++$index;
		}

		$after = empty( $this->tokens ) ? strlen( $this->style ) : $this->tokens[ $count - 1 ]['end'];
		return array( $count, $after );
	}

	/**
	 * Consumes a declaration candidate up to the next top-level semicolon or EOF.
	 *
	 * @param int $index Starting token index.
	 * @return array{int,int,bool} End token index, byte offset after candidate, and whether it ended in a semicolon.
	 */
	private function consume_declaration_segment( int $index ): array {
		$count = count( $this->tokens );

		while ( $index < $count ) {
			$token = $this->tokens[ $index ];

			if ( WP_CSS_Token_Processor::TOKEN_SEMICOLON === $token['type'] ) {
				return array( $index, $token['end'], true );
			}

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
				continue;
			}

			++$index;
		}

		$after = empty( $this->tokens ) ? strlen( $this->style ) : $this->tokens[ $count - 1 ]['end'];
		return array( $count, $after, false );
	}

	/**
	 * Parses a declaration from a token segment.
	 *
	 * @param int $leading_start Byte offset before leading trivia.
	 * @param int $start_index   First token index in the segment.
	 * @param int $end_index     Token index after the segment.
	 * @param int $after         Byte offset after the declaration.
	 * @return array{name:string, raw_name:string, leading_start:int, start:int, after:int, trailing_end:int, value_start:int, value_end:int, important:bool}|null
	 */
	private function parse_declaration_segment( int $leading_start, int $start_index, int $end_index, int $after ): ?array {
		$index = $this->skip_ignored_tokens( $start_index, $end_index );
		if ( $index >= $end_index || WP_CSS_Token_Processor::TOKEN_IDENT !== $this->tokens[ $index ]['type'] ) {
			return null;
		}

		$name_token = $this->tokens[ $index ];
		$name       = $name_token['value'];
		if ( null === $name ) {
			return null;
		}

		++$index;
		$index = $this->skip_ignored_tokens( $index, $end_index );

		if ( $index >= $end_index || WP_CSS_Token_Processor::TOKEN_COLON !== $this->tokens[ $index ]['type'] ) {
			return null;
		}

		$colon_token       = $this->tokens[ $index ];
		$value_start_index = $this->skip_ignored_tokens( $index + 1, $end_index );
		$value_end_index   = $this->trim_ignored_tokens( $value_start_index, $end_index );
		$important         = false;
		$top_level_tokens  = $this->get_top_level_non_ignored_token_indexes( $value_start_index, $value_end_index );
		$top_level_count   = count( $top_level_tokens );
		$last_value_index  = $top_level_count > 0 ? $top_level_tokens[ $top_level_count - 1 ] : null;
		$before_last_index = $top_level_count > 1 ? $top_level_tokens[ $top_level_count - 2 ] : null;

		if (
			null !== $last_value_index &&
			null !== $before_last_index &&
			WP_CSS_Token_Processor::TOKEN_IDENT === $this->tokens[ $last_value_index ]['type'] &&
			0 === strcasecmp( 'important', (string) $this->tokens[ $last_value_index ]['value'] ) &&
			WP_CSS_Token_Processor::TOKEN_DELIM === $this->tokens[ $before_last_index ]['type'] &&
			'!' === $this->tokens[ $before_last_index ]['value']
		) {
			$important       = true;
			$value_end_index = $this->trim_ignored_tokens( $value_start_index, $before_last_index );
		}

		if ( $value_start_index >= $value_end_index ) {
			$value_start = $colon_token['end'];
			$value_end   = $colon_token['end'];
		} else {
			$value_start = $this->tokens[ $value_start_index ]['start'];
			$value_end   = $this->tokens[ $value_end_index - 1 ]['end'];
		}

		return array(
			'name'          => $name,
			'raw_name'      => substr( $this->style, $name_token['start'], $name_token['length'] ),
			'leading_start' => $leading_start,
			'start'         => $name_token['start'],
			'after'         => $after,
			'trailing_end'  => $after,
			'value_start'   => $value_start,
			'value_end'     => $value_end,
			'important'     => $important,
		);
	}

	/**
	 * Gets top-level non-ignored token indexes from a token range.
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

			if ( WP_CSS_Token_Processor::TOKEN_FUNCTION === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_PAREN === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET );
				continue;
			}

			if ( WP_CSS_Token_Processor::TOKEN_LEFT_BRACE === $token['type'] ) {
				list( $index ) = $this->consume_simple_block( $index, WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE );
				continue;
			}

			++$index;
		}

		return $top_level_tokens;
	}

	/**
	 * Skips ignored CSS tokens.
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
	 * Gets the byte offset after ignored tokens beginning at a token index.
	 *
	 * @param int $index Token index.
	 * @return int Byte offset after ignored tokens.
	 */
	private function get_trailing_ignored_end( int $index ): int {
		$end   = $index > 0 && isset( $this->tokens[ $index - 1 ] ) ? $this->tokens[ $index - 1 ]['end'] : 0;
		$count = count( $this->tokens );

		while ( $index < $count && $this->is_ignored_token( $this->tokens[ $index ] ) ) {
			$end = $this->tokens[ $index ]['end'];
			++$index;
		}

		return $end;
	}

	/**
	 * Checks whether a token is ignored by declaration-list grammar.
	 *
	 * @param array{type:string, value:string|null, start:int, length:int, end:int} $token Token metadata.
	 * @return bool Whether the token is ignored.
	 */
	private function is_ignored_token( array $token ): bool {
		return (
			WP_CSS_Token_Processor::TOKEN_WHITESPACE === $token['type'] ||
			WP_CSS_Token_Processor::TOKEN_COMMENT === $token['type']
		);
	}

	/**
	 * Gets the current declaration metadata.
	 *
	 * @return array{name:string, raw_name:string, leading_start:int, start:int, after:int, trailing_end:int, value_start:int, value_end:int, important:bool}|null
	 */
	private function get_current_declaration(): ?array {
		if ( $this->current_declaration < 0 || ! isset( $this->declarations[ $this->current_declaration ] ) ) {
			return null;
		}

		return $this->declarations[ $this->current_declaration ];
	}

	/**
	 * Queues a lexical update, replacing any earlier update for the same declaration.
	 *
	 * @param int      $start       Byte offset at which to start the update.
	 * @param int      $length      Number of bytes to replace.
	 * @param string   $text        Replacement text.
	 * @param int|null $declaration Declaration index associated with the update.
	 */
	private function queue_lexical_update( int $start, int $length, string $text, ?int $declaration ): void {
		if ( null !== $declaration ) {
			foreach ( $this->lexical_updates as $index => $update ) {
				if ( $update['declaration'] === $declaration ) {
					unset( $this->lexical_updates[ $index ] );
				}
			}
		}

		$this->lexical_updates[] = array(
			'start'       => $start,
			'length'      => $length,
			'text'        => $text,
			'declaration' => $declaration,
			'order'       => $this->lexical_update_order++,
		);
	}

	/**
	 * Checks whether an append has already been queued at a byte offset.
	 *
	 * @param int $insert_at Byte offset.
	 * @return bool Whether an append has already been queued at the offset.
	 */
	private function has_queued_append_at( int $insert_at ): bool {
		foreach ( $this->lexical_updates as $update ) {
			if ( null === $update['declaration'] && $insert_at === $update['start'] && 0 === $update['length'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Merges overlapping lexical updates into non-overlapping replacements.
	 *
	 * Adjacent declaration removals may share separator whitespace, and appends
	 * may be queued inside a range removed from the original style. This method
	 * collapses those updates while preserving the requested replacement text.
	 *
	 * @param array<int, array{start:int, length:int, text:string, declaration:int|null, order:int}> $updates Lexical updates.
	 * @return array<int, array{start:int, length:int, text:string, declaration:int|null, order:int}> Merged updates.
	 */
	private function merge_overlapping_lexical_updates( array $updates ): array {
		$merged = array();

		foreach ( $updates as $update ) {
			if ( empty( $merged ) ) {
				$merged[] = $update;
				continue;
			}

			$last_index = count( $merged ) - 1;
			$last       = $merged[ $last_index ];
			$last_end   = $last['start'] + $last['length'];
			$update_end = $update['start'] + $update['length'];

			if ( $update['start'] > $last_end ) {
				$merged[] = $update;
				continue;
			}

			$text = $update['text'];
			if ( 0 === $last['start'] && '' === $last['text'] && 0 === $update['length'] ) {
				$text = ltrim( $text, ';' . self::WHITESPACE );
			}

			$merged[ $last_index ]['length'] = max( $last_end, $update_end ) - $last['start'];
			$merged[ $last_index ]['text']   = $last['text'] . $text;
		}

		foreach ( $merged as $index => $update ) {
			if ( 0 !== $update['start'] || '' !== $update['text'] ) {
				continue;
			}

			$end = $update['length'];
			while ( $end < strlen( $this->style ) && false !== strpos( self::WHITESPACE, $this->style[ $end ] ) ) {
				++$end;
			}

			$merged[ $index ]['length'] = $end;
		}

		return $merged;
	}

	/**
	 * Serializes a declaration.
	 *
	 * @param string $property_name CSS property name.
	 * @param string $value         CSS declaration value.
	 * @param bool   $important     Whether to append !important.
	 * @return string Serialized declaration.
	 */
	private function serialize_declaration( string $property_name, string $value, bool $important ): string {
		$value = trim( $value, self::WHITESPACE );
		return $property_name . ': ' . $value . ( $important ? ' !important' : '' ) . ';';
	}

	/**
	 * Checks whether two property names match.
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
	 * Checks whether a property name is a custom property.
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
	 * @param string $property_name CSS property name.
	 * @return bool Whether the property name is valid.
	 */
	private function is_valid_property_name( string $property_name ): bool {
		$processor = WP_CSS_Token_Processor::create( $property_name );
		if ( null === $processor || ! $processor->next_token() ) {
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

		$sentinel_processor = WP_CSS_Token_Processor::create( $property_name . ': sentinel' );
		if ( null === $sentinel_processor || ! $sentinel_processor->next_token() ) {
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
	 * @param string $value CSS declaration value.
	 * @return bool Whether the value is valid.
	 */
	private function is_valid_declaration_value( string $value ): bool {
		$sentinel_property = '--wp-style-attribute-processor-sentinel';
		$processor         = WP_CSS_Token_Processor::create( $value . ';' . $sentinel_property . ':1' );
		if ( null === $processor ) {
			return false;
		}

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
					'value' => $processor->get_token_value(),
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

		$top_level_count = count( $top_level_tokens );
		if ( $top_level_count < 2 ) {
			return true;
		}

		$last_value_token  = $top_level_tokens[ $top_level_count - 1 ];
		$before_last_token = $top_level_tokens[ $top_level_count - 2 ];

		return ! (
			WP_CSS_Token_Processor::TOKEN_IDENT === $last_value_token['type'] &&
			0 === strcasecmp( 'important', (string) $last_value_token['value'] ) &&
			WP_CSS_Token_Processor::TOKEN_DELIM === $before_last_token['type'] &&
			'!' === $before_last_token['value']
		);
	}
}
