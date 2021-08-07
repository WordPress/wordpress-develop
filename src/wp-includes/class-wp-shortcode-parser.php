<?php
/**
 * Shortcode API: WP_Shortcode_Parser class
 *
 * @package WordPress
 * @since x.x.x
 */

/**
 * A collection of methods for parsing and executing shortcodes in content.
 *
 * @since x.x.x
 */
class WP_Shortcode_Parser {
	/**
	 * The content being parsed.
	 *
	 * @since x.x.x
	 *
	 * @var string $content
	 */
	private $content;

	/**
	 * The current state of the parser.
	 *
	 * @since x.x.x
	 *
	 * @var int $state One of the `SHORTCODE_PARSE_STATE_*` constants listed below.
	 */
	private $state;

	/**
	 * The current position of the parsing cursor in the content.
	 *
	 * @since x.x.x
	 *
	 * @var int $cursor_position
	 */
	private $cursor_position;

	/**
	 * The stack of unprocessed shortcodes.
	 *
	 * As shortcodes are opened, they are placed on the stack, and as they're
	 * closed, they're processed and removed.
	 *
	 * @since x.x.x
	 *
	 * @var array $stack
	 */
	private $stack;

	/**
	 * A list of all registered shortcode tag names.
	 *
	 * @since x.x.x
	 *
	 * @var string[] $tagnames
	 */
	private $tagnames;

	/**
	 * The shortcode currently being parsed.
	 *
	 * As a shortcode is being parsed, it is stored here. If a new shortcode is
	 * found before parsing is complete, this shortcode is moved to the stack,
	 * and the new shortcode is stored here.
	 *
	 * @since x.x.x
	 *
	 * @var array $current_shortcode {
	 *     @type string $full_tag              The content that makes up this tag, from the opening
	 *                                         bracket to the closing bracket.
	 *     @type string $extra_opening_bracket Either the character '[' or an empty string,
	 *                                         depending on whether there was an extra opening
	 *                                         bracket for this shortcode. Essentially a flag.
	 *     @type string $tag_slug              The shortcode tag slug.
	 *     @type string $atts_and_values       The (unparsed) part of the shortcode tag that contains
	 *                                         attributes and their optional values.
	 *     @type string $self_closing_slash    Like $extra_opening_bracket, this is either the
	 *                                         slash used to self-close the shortcode tag, or an
	 *                                         empty string.
	 *     @type int $cursor_position          The cursor position where this shortcode began.
	 * }
	 */
	private $current_shortcode;

	/**
	 * The default parsing state -- the cursor is not in a shortcode tag or
	 * shortcode content or a quoted string in a shortcode attribute value.
	 *
	 * @since x.x.x
	 *
	 * @var int $SHORTCODE_PARSE_STATE_DEFAULT
	 */
	const SHORTCODE_PARSE_STATE_DEFAULT = 0;

	/**
	 * The cursor is inside the shortcode tag, past the shortcode tag slug.
	 *
	 * @since x.x.x
	 *
	 * @var int $SHORTCODE_PARSE_STATE_IN_TAG
	 */
	const SHORTCODE_PARSE_STATE_IN_TAG = 1;

	/**
	 * The cursor is in the content of the shortcode -- past the opening tag
	 * but not yet to the closing tag.
	 *
	 * @since x.x.x
	 *
	 * @var int $SHORTCODE_PARSE_STATE_IN_CONTENT
	 */
	const SHORTCODE_PARSE_STATE_IN_CONTENT = 2;

	/**
	 * The cursor is inside of a quoted string in the shortcode tag.
	 *
	 * @since x.x.x
	 *
	 * @var int $SHORTCODE_PARSE_STATE_IN_QUOTED_STRING
	 */
	const SHORTCODE_PARSE_STATE_IN_QUOTED_STRING = 3;

	/**
	 * Store the content and tag names for later use.
	 *
	 * @since x.x.x
	 *
	 * @param string   $content The HTML content to parse for shortcodes.
	 * @param string[] $tagnames An array of string shortcode tag names.
	 */
	public function __construct( $content, $tagnames ) {
		$this->content  = $content;
		$this->tagnames = $tagnames;
	}

	/**
	 * Parse shortcodes in content and replace them with the output that their
	 * handler functions generate.
	 *
	 * @since x.x.x
	 *
	 * @return string The content with shortcodes replaced by their output.
	 */
	public function parse() {
		$this->stack = array();

		/*
		 * A regular expression that checks whether a string appears to begin
		 * with a tag for a registered shortcode.
		 */
		$registered_shortcode_regex = '/^(?P<extra_opening_bracket>\\[?)(?P<opening_bracket>\\[)(?P<tag_slug>' . join( '|', array_map( 'preg_quote', $this->tagnames ) ) . ')(?![\\w-])/u';

		$this->cursor_position = 0;

		// Save some parsing time by starting a few characters before the first bracket.
		$this->forward_cursor_to_next_bracket();

		$this->state = self::SHORTCODE_PARSE_STATE_DEFAULT;

		$is_escaped = false;
		$delimiter  = null;

		while ( $this->cursor_position < strlen( $this->content ) ) {
			$char = substr( $this->content, $this->cursor_position, 1 );

			$found_escape_character = false;

			switch ( $this->state ) {
				case self::SHORTCODE_PARSE_STATE_DEFAULT:
				case self::SHORTCODE_PARSE_STATE_IN_CONTENT:
					if ( ! $is_escaped && '[' === $char && preg_match( $registered_shortcode_regex, substr( $this->content, $this->cursor_position ), $m ) ) {
						if ( $this->current_shortcode ) {
							$this->stack[] = $this->current_shortcode;
						}

						// We have found the beginning of a shortcode.
						$this->current_shortcode = array(
							'full_tag'              => $m[0],
							'extra_opening_bracket' => $m['extra_opening_bracket'],
							'tag_slug'              => $m['tag_slug'],
							'atts_and_values'       => '',
							'self_closing_slash'    => '',
							'inner_content'         => '',
							'extra_closing_bracket' => '',
							'cursor_position'       => $this->cursor_position,
						);

						$this->cursor_position += strlen( $m[0] );

						// Move back one so it's as if we just processed the last character of the shortcode slug.
						$this->cursor_position--;

						$this->state = self::SHORTCODE_PARSE_STATE_IN_TAG;
					} elseif ( self::SHORTCODE_PARSE_STATE_IN_CONTENT === $this->state ) {
						$this->current_shortcode['full_tag'] .= $char;

						if ( '[' === $char ) {
							// Check whether it's a closing tag of any currently open shortcode.
							$rest_of_closing_tag = '/' . $this->current_shortcode['tag_slug'] . ']';

							if ( substr( $this->content, $this->cursor_position + 1, strlen( $rest_of_closing_tag ) ) === $rest_of_closing_tag ) {
								// The end of this shortcode.

								$this->current_shortcode['full_tag'] .= $rest_of_closing_tag;

								// Move the cursor to the end of the closing tag.
								$this->cursor_position += strlen( $rest_of_closing_tag );

								if ( $this->current_shortcode['extra_opening_bracket'] ) {
									if ( ']' === substr( $this->content, $this->cursor_position + 1, 1 ) ) {
										$this->current_shortcode['full_tag']             .= ']';
										$this->current_shortcode['extra_closing_bracket'] = ']';
										$this->cursor_position++;
									} else {
										// If there was an extra opening bracket but not an extra closing bracket, ignore the extra opening bracket.

										$this->current_shortcode['full_tag']              = substr( $this->current_shortcode['full_tag'], 1 );
										$this->current_shortcode['extra_opening_bracket'] = '';

										// We initially thought it had an extra opening bracket, but it doesn't so it started one character later than we thought.
										$this->current_shortcode['cursor_position'] += 1;
									}
								}

								$this->process_current_shortcode();
							} else {
								$found_matching_shortcode = false;

								for ( $stack_index = count( $this->stack ) - 1; $stack_index >= 0; $stack_index-- ) {
									$rest_of_closing_tag = '/' . $this->stack[ $stack_index ]['tag_slug'] . ']';

									if ( substr( $this->content, $this->cursor_position + 1, strlen( $rest_of_closing_tag ) ) === $rest_of_closing_tag ) {
										// Yes, it closes this one.
										$found_matching_shortcode = true;

										/*
										 * We already saved the bracket as part of the full tag, expecting that the
										 * closing tag would be for the current shortcode. It's not, so remove it.
										 */
										$this->current_shortcode['full_tag'] = substr( $this->current_shortcode['full_tag'], 0, -1 );

										// This means that the "current" shortcode and any others above this one on the stack need to be closed out, because they are self-closing.
										do {
											$this->current_shortcode['full_tag'] = substr( $this->current_shortcode['full_tag'], 0, -1 * strlen( $this->current_shortcode['inner_content'] ) );

											// And there is no inner content.
											$this->current_shortcode['inner_content'] = '';

											$this->process_current_shortcode(); // This sets $current_shortcode using the top stack item, so we don't need to do it.
										} while ( count( $this->stack ) > $stack_index + 1 );

										/*
										 * At this point, the shortcode that is being closed right now is $this->current_shortcode.
										 * The easiest way to process this without duplicating code is to reprocess the current
										 * character with the new stack and current shortcode, so the section above will get
										 * triggered, since the closing tag will be for the current shortcode.
										 */

										continue 3;
									}
								}

								if ( ! $found_matching_shortcode ) {
									$this->current_shortcode['inner_content'] .= $char;
								}
							}
						} else {
							$this->current_shortcode['inner_content'] .= $char;
						}
					}

					break;
				case self::SHORTCODE_PARSE_STATE_IN_TAG:
					$this->current_shortcode['full_tag'] .= $char;

					if ( '/' === $char && substr( $this->content, $this->cursor_position + 1, 1 ) === ']' ) {
						// The shortcode is over.
						$this->current_shortcode['self_closing_slash'] = '/';
						$this->current_shortcode['full_tag']          .= ']';
						$this->cursor_position++;

						// If the shortcode had an extra opening bracket but doesn't have an extra closing bracket, ignore the extra opening bracket.

						if ( $this->current_shortcode['extra_opening_bracket'] ) {
							if ( ']' === substr( $this->content, $this->cursor_position + 1, 1 ) ) {
								$this->current_shortcode['extra_closing_bracket'] = ']';
								$this->current_shortcode['full_tag']             .= ']';
								$this->cursor_position++;
							} else {
								$this->current_shortcode['full_tag']              = substr( $this->current_shortcode['full_tag'], 1 );
								$this->current_shortcode['extra_opening_bracket'] = '';

								/*
								 * We initially thought it had an extra opening bracket, but it doesn't,
								 * so it started one character later than we thought.
								 */
								$this->current_shortcode['cursor_position'] += 1;
							}
						}

						$this->process_current_shortcode();

						break;
					} elseif ( ']' === $char ) {
						if ( $this->current_shortcode['extra_opening_bracket'] ) {
							/*
							 * This makes the assumption that this shortcode is closed as soon as the double brackets are found:
							 *
							 * [[my-shortcode]][/my-shortcode]]
							 *
							 * But in theory, this could just be a shortcode with the content "]".
							 */

							if ( ']' === substr( $this->content, $this->cursor_position + 1, 1 ) ) {
								$this->current_shortcode['extra_closing_bracket'] = ']';
								$this->current_shortcode['full_tag']             .= ']';
								$this->cursor_position++;

								$this->process_current_shortcode();
								break;
							} else {
								// There was not an extra closing bracket.
							}
						}

						if ( false === strpos( substr( $this->content, $this->cursor_position ), '[/' . $this->current_shortcode['tag_slug'] . ']' ) ) {
							// If there's no closing tag, it's a self-enclosed shortcode, and we're done with it.
							$this->process_current_shortcode();
						} else {
							$this->state = self::SHORTCODE_PARSE_STATE_IN_CONTENT;

							$current_cursor_position = $this->cursor_position;
							$this->forward_cursor_to_next_bracket();

							if ( $this->cursor_position !== $current_cursor_position ) {
								// The +1 is because the character at $current_cursor_position has already been recorded.
								$skipped_content = substr( $this->content, $current_cursor_position + 1, $this->cursor_position - $current_cursor_position );

								$this->current_shortcode['inner_content'] .= $skipped_content;
								$this->current_shortcode['full_tag']      .= $skipped_content;
							}
						}
					} else {
						$this->current_shortcode['atts_and_values'] .= $char;

						if ( '"' === $char || "'" === $char ) {
							$this->state = self::SHORTCODE_PARSE_STATE_IN_QUOTED_STRING;
							$delimiter   = $char;
						} else {
							// Nothing to do.
						}
					}

					break;
				case self::SHORTCODE_PARSE_STATE_IN_QUOTED_STRING:
					$this->current_shortcode['full_tag']        .= $char;
					$this->current_shortcode['atts_and_values'] .= $char;

					if ( $is_escaped ) {
						// Nothing to do. This is just an escaped character to be taken literally.
					} else {
						// Not escaped.
						if ( '\\' === $char ) {
							// The next character is escaped.
							$found_escape_character = true;
						} elseif ( $char === $delimiter ) {
							$this->state = self::SHORTCODE_PARSE_STATE_IN_TAG;
							$delimiter   = null;
						}
					}

					break;
			}

			// Is the next character escaped?
			if ( $found_escape_character ) {
				$is_escaped = true;
			} else {
				// If we didn't find an escape character here, then no.
				$is_escaped = false;
			}

			$this->cursor_position++;
		}

		if ( self::SHORTCODE_PARSE_STATE_IN_QUOTED_STRING === $this->state ) {
			/*
			 * example: This is my content [footag foo=" [bartag]
			 * Should it be reprocessed in order to convert [bartag] or is this considered malformed?
			 */
		}

		if ( self::SHORTCODE_PARSE_STATE_IN_TAG === $this->state ) {
			/*
			 * example: This is my content [footag foo="abc" bar="def" [bartag]
			 * Should it be reprocessed in order to convert [bartag] or is this considered malformed?
			 */
		}

		if ( $this->current_shortcode ) {
			/*
			 * If we end with shortcodes still on the stack, then there was a situation like this:
			 *
			 * [footag] [bartag] [baztag] [footag]content[/footag]
			 *
			 * i.e., a scenario where the parser was unsure whether the first [footag] was self-closing or not.
			 *
			 * By this point, $content will be in this format:
			 *
			 * [footag] bartag-output baztag-output footag-content-output
			 *
			 * so we need to back up and process the still-stored shortcodes as unclosed.
			 *
			 * An extreme version of this would look like:
			 *
			 * [footag] [footag] [footag] [footag] [footag] [footag] [footag] [footag] ... [footag][/footag]
			 *
			 * where the last tag would be the only one processed normally above and there would be n-1 [footag]s still on the stack.
			 */
			while ( $this->current_shortcode ) {
				// What we thought was part of this tag was just regular content.
				$this->current_shortcode['full_tag'] = substr( $this->current_shortcode['full_tag'], 0, -1 * strlen( $this->current_shortcode['inner_content'] ) );

				// And there is no inner content.
				$this->current_shortcode['inner_content'] = '';

				$this->process_current_shortcode(); // This sets $current_shortcode, so we don't need to do it.
			}
		}

		return $this->content;
	}

	/**
	 * Create an argument to pass to do_shortcode_tag.
	 *
	 * The format of this argument was determined by the capture groups of the
	 * regular expression that used to be used to parse shortcodes out of content.
	 *
	 * @since x.x.x
	 *
	 * @param array $shortcode An associative array comprising data about a shortcode in the text.
	 * @return array A numerically-indexed array of the shortcode data ready for do_shortcode_tag().
	 */
	private function shortcode_argument( $shortcode ) {
		return array(
			$shortcode['full_tag'],
			$shortcode['extra_opening_bracket'],
			$shortcode['tag_slug'],
			$shortcode['atts_and_values'],
			$shortcode['self_closing_slash'],
			$shortcode['inner_content'],
			$shortcode['extra_closing_bracket'],
		);
	}

	/**
	 * Process the shortcode at the top of the stack.
	 *
	 * @since x.x.x
	 *
	 * The shortcode at the top of the stack is complete and can be processed.
	 * Process it and modify the enclosing shortcode as if the content was passed in
	 * with this shortcode already converted into HTML.
	 */
	private function process_current_shortcode() {
		$argument_for_do_shortcode_tag = $this->shortcode_argument( $this->current_shortcode );

		$shortcode_output = do_shortcode_tag( $argument_for_do_shortcode_tag );

		/*
		 * Replace based on position rather than find and replace, since this content is possible:
		 *
		 * Test 123 [some-shortcode] To use my shortcode, type [[some-shortcode]].
		 */
		$this->content =
			substr( $this->content, 0, $this->current_shortcode['cursor_position'] )
			. $shortcode_output
			. substr( $this->content, $this->current_shortcode['cursor_position'] + strlen( $this->current_shortcode['full_tag'] ) );

		/*
		 * Update the cursor position to the end of this shortcode's output.
		 * The -1 is because the position is incremented after this gets called to move it to the next character.
		 */
		$this->cursor_position = $this->current_shortcode['cursor_position'] + strlen( $shortcode_output ) - 1;

		// For any enclosing shortcode, its inner content needs to include the full output of this shortcode.
		if ( ! empty( $this->stack ) ) {
			$this->current_shortcode = array_pop( $this->stack );

			$this->current_shortcode['inner_content'] .= $shortcode_output;
			$this->current_shortcode['full_tag']      .= $shortcode_output;

			$this->state = self::SHORTCODE_PARSE_STATE_IN_CONTENT;

			$current_cursor_position = $this->cursor_position;
			$this->forward_cursor_to_next_bracket();

			if ( $this->cursor_position !== $current_cursor_position ) {
				/*
				 * The +1 is because the character at $current_cursor_position has already been recorded.
				 */
				$skipped_content = substr( $this->content, $current_cursor_position + 1, $this->cursor_position - $current_cursor_position );

				$this->current_shortcode['inner_content'] .= $skipped_content;
				$this->current_shortcode['full_tag']      .= $skipped_content;
			}
		} else {
			$this->current_shortcode = null;

			$this->state = self::SHORTCODE_PARSE_STATE_DEFAULT;

			// In the default state, we can skip over any content that couldn't be a shortcode, so let's move forward near the next bracket.
			$this->forward_cursor_to_next_bracket();
		}
	}

	/**
	 * Moves the parsing cursor to the next possible location that might
	 * include a shortcode.
	 *
	 * The specific location is directly before the next bracket or the end
	 * of the content if there is no next bracket.
	 *
	 * @since x.x.x
	 */
	private function forward_cursor_to_next_bracket() {
		/*
		 * The max() here is because $cursor_position can be -1 if a shortcode
		 * at the beginning of the content didn't have any output and reset the
		 * cursor back to the beginning. It's -1 instead of zero because it will
		 * be incremented later in the loop to set it to zero for the next iteration.
		 */
		$next_bracket_location = strpos( $this->content, '[', max( 0, $this->cursor_position ) );

		if ( false === $next_bracket_location ) {
			// There is no next bracket, so fast-forward to the end.
			$next_bracket_location = strlen( $this->content );
		}

		/*
		 * Again, the -1 is because this will be incremented before it is used,
		 * and we really want it to have a minimum value of zero.
		 */
		$this->cursor_position = max( -1, $next_bracket_location - 1 );
	}
}
