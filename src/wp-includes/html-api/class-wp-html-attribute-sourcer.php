<?php

/*
 * Need:
 *  - select( $tags, $selector_sequence )
 *  - select_child( $tags_at_outer_tag, $selector_sequence )
 *  - select_descendant( $tags_at_outer_tag, $selector_sequence )
 *  - select_adjacent_sibling( $tags_at_start_tag, $selector_sequence )
 *  - select_general_sibling( $tags_at_start_tag, $selector_sequence )
 *  - close_n_levels( $tags_at_depth, $n_levels )
 *
 * @TODO:
 *  - [ ] Handle multiple joined constraints for classes and attributes
 *        e.g. ".locale-en-US.localized[data-translation-id][data-translate]"
 *  - [ ] Handle comma-separated selector sequences; apparently we only grab the first right now
 */

/**
 * Existing Core Selectors
 *
 *     .blocks-gallery-caption
 *     .blocks-gallery-item
 *     .blocks-gallery-item__caption
 *     .book-author
 *     .message
 *     a
 *     a[download]
 *     audio
 *     blockquote
 *     cite
 *     code
 *     div
 *     figcaption
 *     figure > a
 *     figure a
 *     figure img
 *     figure video,figure img
 *     h1,h2,h3,h4,h5,h6
 *     img
 *     li
 *     ol,ul
 *     p
 *     pre
 *     tbody tr
 *     td,th
 *     tfoot tr
 *     thead tr
 *     video
 */

/*
 * @see PHP docs for array_is_list: user-contributed polyfill
 */
if ( ! function_exists( 'array_is_list' ) ) {
	function array_is_list( $array ) {
		$i = 0;

		foreach ( $array as $k => $v ) {
			if ( $k !== $i++ ) {
				return false;
			}
		}

		return true;
	}
}

class WP_HTML_Attribute_Sourcer {
	/**
	 * Attributes definitions, typically from `block.json`.
	 *
	 * @see WP_Block_Type_Registry
	 *
	 * @var mixed|null
	 */
	public $attribute_definitions;

	/**
	 * Source HTML containing embedded attributes.
	 *
	 * @var mixed|null
	 */
	private $html;

	public function __construct( $attribute_definitions = null, $html = null ) {
		$this->attribute_definitions = $attribute_definitions;
		$this->html = $html;
	}

	public function source_attributes() {
		$attributes = [];
		$unparsed = [];

		foreach ( $this->attribute_definitions as $name => $definition ) {
			$sourcer = self::parse_definition( $definition );
			switch ( $sourcer ) {
				case null:
				case 'not-sourced':
				case 'unsupported':
					$unparsed[] = $name;
					continue 2;

				case 'inner-html':
					$attributes[ $name ] = $this->html;
					continue 2;
			}

			$tags = self::select( $sourcer['selector'], $this->html );
			if ( ! $tags ) {
				$attributes[ $name ] = null;
				continue;
			}

			switch ( $sourcer['type'] ) {
				case 'html':
					$attributes[ $name ] = $tags->get_content_inside_balanced_tags();
					continue 2;

				case 'attribute':
					$attributes[ $name ] = $tags->get_attribute( $sourcer['attribute'] );
					continue 2;
			}
		}

		return array(
			'attributes' => $attributes,
			'unparsed' => $unparsed
		);
	}

	public static function select_match( $tags, $s ) {
		if ( ! empty( $s['tag_name'] ) && strtoupper( $s['tag_name'] ) !== $tags->get_tag() ) {
			return null;
		}

		if ( ! empty( $s['class_names'] ) ) {
			$classes = $tags->get_attribute( 'class' );
			if ( null === $classes ) {
				return null;
			}

			foreach ( $s['class_names'] as $class_name ) {
				if ( ! preg_match( "~\b{$class_name}\b~", $classes ) ) {
					return null;
				}
			}
		}

		if ( isset( $s['hash'] ) && $s['identifier'] !== $tags->get_attribute( 'id' ) ) {
			return null;
		}

		if ( isset( $s['has_attribute'] ) && null === $tags->get_attribute( $s['has_attribute'] ) ) {
			return null;
		}

		return $tags;
	}

	/**
	 * @TODO: This needs to be able to continue to the next match
	 *        Pass in $tags? Pass in a bookmark?
	 */
	public static function select( $selectors, $html ) {
		$selector_index = 0;

		selector_choice:
		$tags = new WP_HTML_Processor( $html );
		$outer_state = $tags->new_state();

		$next = $selectors[ $selector_index ];

		loop:
		while ( $tags->balanced_next( $outer_state ) ) {
			if ( ! self::select_match( $tags, $next ) ) {
				continue;
			}

			if ( ! isset( $next['then'] ) ) {
				return $tags;
			}

			inner_loop:
			$prev = $next;
			$next = $next['then'];

			$inner_state = $tags->new_state();
			switch ( $next['combinator'] ) {
				/*
				 * Adjacent sibling must be the immediately-following
				 * element which shares the same parent.
				 */
				case '+':
					/*
					 * If we have opened a tag we need to continue scanning past all of its children.
					 * `balanced_next()` will end up on the closing tag, so if we don't have any
					 * children, or no closing tag, we need to skip this because `balanced_tag()`
					 * would end up in those cases on the sibling element.
					 */
					if ( ! WP_HTML_Processor::is_html_void_element( $tags->get_tag() ) ) {
						while ( $tags->balanced_next( $inner_state ) ) {
							continue;
						}
					}

					if ( $tags->balanced_next( $outer_state ) && self::select_match( $tags, $next ) ) {
						if ( ! isset( $next['then'] ) ) {
							return $tags;
						}
						goto inner_loop;
					}

					$next = $prev;
					break;

				// Child combinator
				case '>':
					$inner_state->match_depth = 1;
					// Intentional fallthrough
				// Descendant combinator
				case ' ':
					/*
					 * This match has to be a child of the matched tag,
					 * and the matched tag has to be its parent for the
					 * case of the child combinator.
					 */
					while ( $tags->balanced_next( $inner_state ) ) {
						if ( self::select_match( $tags, $next ) ) {
							if ( ! isset( $next['then'] ) ) {
								return $tags;
							}

							goto inner_loop;
						}
					}

					$next = $prev;
					goto loop;
			}
		}

		if ( ++$selector_index < count( $selectors ) ) {
			goto selector_choice;
		}

		return false;
	}

	public static function parse_definition( $definition ) {
		if ( empty( $definition['source'] ) ) {
			return 'not-sourced';
		}

		$source = $definition['source'];
		if ( 'html' !== $source && 'attribute' !== $source ) {
			return 'unsupported';
		}

		if ( 'attribute' === $source && empty( $definition['selector'] ) ) {
			return null;
		}

		if ( 'html' === $source && empty( $definition['selector'] ) ) {
			return 'inner-html';
		}

		$selectors = self::parse_full_selector( $definition['selector'] );
		if ( null === $selectors ) {
			return 'unsupported';
		}

		if ( 'html' === $source ) {
			return array( 'type' => 'html', 'selector' => $selectors );
		}

		$attribute = self::parse_attribute( $definition['attribute'] );
		if ( null === $attribute ) {
			return null;
		}

		return array( 'type' => 'attribute', 'selector' => $selectors, 'attribute' => $attribute );
	}

	public static function parse_full_selector( $s ) {
		$selectors = [];
		$at = 0;

		while ( $at < strlen( $s ) ) {
			$at += strspn( $s, " \f\n\r\t", $at );

			list( $selector, $next_at ) = self::parse_selector( $s, $at );
			if ( null === $selector ) {
				return null;
			}

			$selectors[] = $selector;
			$at = $next_at;

			if ( $at < strlen( $s ) && ',' !== $s[ $at ] ) {
				return null;
			}
			$at++;
		}

		return $selectors;
	}

	public static function parse_selector( $s, $at = 0, $selector = [] ) {
		$is_first = true;

		while ( $at < strlen( $s ) && ',' !== $s[ $at ] ) {
			/*
			 * Descendant combinators are harder to discover because we
			 * always have to skip whitespace, but that whitespace could
			 * be the combinator if we don't approach anything else first.
			 */
			$ws_length = strspn( $s, " \f\n\r\t", $at );
			$at += $ws_length;

			if ( !$is_first && $ws_length > 0 && 0 === strspn( $s[ $at ], '>+~' ) ) {
				$at--;
				$s[ $at ] = ' ';
			}
			$is_first = false;

			switch ( $s[ $at ] ) {
				case '>':
				case '+':
				case '~':
				case ' ':
					$combinator = $s[ $at ];
					$at++;
					$at += strspn( $s, " \f\n\r\t", $at );
					$inner = self::parse_selector( $s, $at );
					if ( null === $inner ) {
						return null;
					}
					list( $inner_selector, $next_at ) = $inner;
					$inner_selector['combinator'] = $combinator;
					$selector['then'] = $inner_selector;
					$at = $next_at;
					break;

				case '.':
					$at++;
					$class_name = self::parse_css_identifier( $s, $at );
					if ( null === $class_name ) {
						return null;
					}

					if ( ! isset( $selector['class_names'] ) ) {
						$selector['class_names'] = array();
					}
					$selector['class_names'][] = $class_name;
					$at += strlen( $class_name );
					break;

				case '#':
					$at++;
					// @TODO: Hashes don't have to start with `nmstart` so this might reject valid hash names.
					$element_id = self::parse_css_identifier( $s, $at );
					if ( null === $element_id ) {
						return null;
					}

					$selector['hash'] = $element_id;
					$at += strlen( $element_id );
					break;

				case '[':
					/*
					 * Only current support is for checking of presence of attributes
					 * with a very-limited subset of allowable names, not whether the
					 * attribute conforms to a given value or is allowed in HTML.
					 */
					$at++;
					$inside_length = strspn( $s, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-", $at );
					if ( ']' !== $s[ $at + $inside_length ] ) {
						return null;
					}

					$attribute = substr( $s, $at, $inside_length );
					$at += $inside_length + 1;

					$selector['has_attribute'] = $attribute;
					break;

				default:
					$tag_name = self::parse_css_identifier( $s, $at );
					if ( null === $tag_name ) {
						return null;
					}

					$selector['tag_name'] = $tag_name;
					$at += strlen( $tag_name );
			}
		}

		return [ $selector, $at ];
	}

	/**
	 * Parses CSS identifier; currently limited to ASCII identifiers.
	 *
	 * Example:
	 * ```
	 *     'div' === parse_css_identifier( 'div > img' );
	 * ```
	 *
	 * Grammar:
	 * ```
	 *     ident        -?{nmstart}{nmchar}*
	 *     nmstart      [_a-z]|{nonascii}|{escape}
	 *     nmchar       [_a-z0-9-]|{nonascii}|{escape}
	 *     nonascii     [\240-\377]
	 *     escape       {unicode}|\\[^\r\n\f0-9a-f]
	 *     unicode      \\{h}{1,6}(\r\n|[ \t\r\n\f])?
	 *     h            [0-9a-f]
	 * ```
	 *
	 * @TODO: Add support for the proper syntax
	 *
	 * @see https://www.w3.org/TR/CSS21/grammar.html
	 *
	 * @param $s
	 * @return false|string|null
	 */
	public static function parse_css_identifier( $s, $at = 0 ) {
		$budget = 1000;
		$started_at = $at;

		$starting_chars = strspn( $s, '_-abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $at );
		if ( 0 === $starting_chars ) {
			return null;
		}
		$at += $starting_chars;

		while ( $at < strlen( $s ) && $budget-- > 0 ) {
			$chars = strspn( $s, '_-abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $at );

			if ( 0 === $chars ) {
				break;
			}

			$at += $chars;
		}

		if ( $budget < 0 ) {
			return null;
		}

		return substr( $s, $started_at, $at - $started_at );
	}

	public static function parse_attribute( $s ) {
		$unallowed_characters_match = preg_match(
			'~[' .
			// Syntax-like characters.
			'"\'>&</ =' .
			// Control characters.
			'\x{00}-\x{1F}' .
			// HTML noncharacters.
			'\x{FDD0}-\x{FDEF}' .
			'\x{FFFE}\x{FFFF}\x{1FFFE}\x{1FFFF}\x{2FFFE}\x{2FFFF}\x{3FFFE}\x{3FFFF}' .
			'\x{4FFFE}\x{4FFFF}\x{5FFFE}\x{5FFFF}\x{6FFFE}\x{6FFFF}\x{7FFFE}\x{7FFFF}' .
			'\x{8FFFE}\x{8FFFF}\x{9FFFE}\x{9FFFF}\x{AFFFE}\x{AFFFF}\x{BFFFE}\x{BFFFF}' .
			'\x{CFFFE}\x{CFFFF}\x{DFFFE}\x{DFFFF}\x{EFFFE}\x{EFFFF}\x{FFFFE}\x{FFFFF}' .
			'\x{10FFFE}\x{10FFFF}' .
			']~Ssu',
			$s
		);

		return $unallowed_characters_match ? null : $s;
	}
}
