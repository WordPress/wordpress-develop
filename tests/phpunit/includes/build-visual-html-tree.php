<?php

/* phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped */

/**
 * Generates representation of the semantic HTML tree structure.
 *
 * This is inspired by the representation used by the HTML5lib tests. It's been extended here for
 * blocks to render the semantic structure of blocks and their attributes.
 * The order of attributes and class names is normalized both for HTML tags and blocks,
 * as is the whitespace in HTML tags' style attribute.
 *
 * For example, consider the following block markup:
 *
 *     <!-- wp:separator {"className":"is-style-default has-custom-classname","style":{"spacing":{"margin":{"top":"50px","bottom":"50px"}}},"backgroundColor":"accent-1"} -->
 *         <hr class="wp-block-separator is-style-default has-custom-classname" style="margin-top: 50px; margin-bottom: 50px" />
 *     <!-- /wp:separator -->
 *
 * This will be represented as:
 *
 *     BLOCK["core/separator"]
 *       {
 *         "backgroundColor": "accent-1",
 *         "className": "has-custom-classname is-style-default",
 *         "style": {
 *           "spacing": {
 *             "margin": {
 *               "top": "50px",
 *               "bottom": "50px"
 *             }
 *           }
 *         }
 *       }
 *       <hr>
 *         class="has-custom-classname is-style-default wp-block-separator"
 *         style="margin-top:50px;margin-bottom:50px;"
 *
 *
 * @see https://github.com/WordPress/wordpress-develop/blob/trunk/tests/phpunit/data/html5lib-tests/tree-construction/README.md
 *
 * @since 6.9.0
 *
 * @throws WP_HTML_Unsupported_Exception|Exception If the markup could not be parsed.
 *
 * @param string      $html             Given test HTML.
 * @param string|null $fragment_context Context element in which to parse HTML, such as BODY or SVG.
 * @return string Tree structure of parsed HTML, if supported.
 */
function build_visual_html_tree( string $html, ?string $fragment_context ): string {
	$processor = $fragment_context
		? WP_HTML_Processor::create_fragment( $html, $fragment_context )
		: WP_HTML_Processor::create_full_parser( $html );
	if ( null === $processor ) {
		throw new Exception( 'Could not create a parser.' );
	}
	$tree_indent = '  ';

	$output       = '';
	$indent_level = 0;
	$was_text     = null;
	$text_node    = '';

	$block_context = array();

	while ( $processor->next_token() ) {
		if ( null !== $processor->get_last_error() ) {
			break;
		}

		$token_name = $processor->get_token_name();
		$token_type = $processor->get_token_type();
		$is_closer  = $processor->is_tag_closer();

		if ( $was_text && '#text' !== $token_name ) {
			if ( '' !== $text_node ) {
				$output .= "{$text_node}\"\n";
			}
			$was_text  = false;
			$text_node = '';
		}

		switch ( $token_type ) {
			case '#doctype':
				$doctype = $processor->get_doctype_info();
				$output .= "<!DOCTYPE {$doctype->name}";
				if ( null !== $doctype->public_identifier || null !== $doctype->system_identifier ) {
					$output .= " \"{$doctype->public_identifier}\" \"{$doctype->system_identifier}\"";
				}
				$output .= ">\n";
				break;

			case '#tag':
				$namespace = $processor->get_namespace();
				$tag_name  = 'html' === $namespace
					? strtolower( $processor->get_tag() )
					: "{$namespace} {$processor->get_qualified_tag_name()}";

				if ( $is_closer ) {
					--$indent_level;

					if ( 'html' === $namespace && 'TEMPLATE' === $token_name ) {
						--$indent_level;
					}

					break;
				}

				$tag_indent = $indent_level;

				if ( $processor->expects_closer() ) {
					++$indent_level;
				}

				$output .= str_repeat( $tree_indent, $tag_indent ) . "<{$tag_name}>\n";

				$attribute_names = $processor->get_attribute_names_with_prefix( '' );
				if ( $attribute_names ) {
					$sorted_attributes = array();
					foreach ( $attribute_names as $attribute_name ) {
						$sorted_attributes[ $attribute_name ] = $processor->get_qualified_attribute_name( $attribute_name );
					}

					/*
					 * Sorts attributes to match html5lib sort order.
					 *
					 *  - First comes normal HTML attributes.
					 *  - Then come adjusted foreign attributes; these have spaces in their names.
					 *  - Finally come non-adjusted foreign attributes; these have a colon in their names.
					 *
					 * Example:
					 *
					 *       From: <math xlink:author definitionurl xlink:title xlink:show>
					 *     Sorted: 'definitionURL', 'xlink show', 'xlink title', 'xlink:author'
					 */
					uasort(
						$sorted_attributes,
						static function ( $a, $b ) {
							$a_has_ns = str_contains( $a, ':' );
							$b_has_ns = str_contains( $b, ':' );

							// Attributes with `:` should follow all other attributes.
							if ( $a_has_ns !== $b_has_ns ) {
								return $a_has_ns ? 1 : -1;
							}

							$a_has_sp = str_contains( $a, ' ' );
							$b_has_sp = str_contains( $b, ' ' );

							// Attributes with a namespace ' ' should come after those without.
							if ( $a_has_sp !== $b_has_sp ) {
								return $a_has_sp ? 1 : -1;
							}

							return $a <=> $b;
						}
					);

					foreach ( $sorted_attributes as $attribute_name => $display_name ) {
						$val = $processor->get_attribute( $attribute_name );
						/*
						 * Attributes with no value are `true` with the HTML API,
						 * we use the empty string value in the tree structure.
						 */
						if ( true === $val ) {
							$val = '';
						} elseif ( 'class' === $attribute_name ) {
							$class_names = iterator_to_array( $processor->class_list() );
							sort( $class_names, SORT_STRING );
							$val = implode( ' ', $class_names );
						} elseif ( 'style' === $attribute_name ) {
							$normalized_style = '';
							foreach ( explode( ';', $val ) as $style ) {
								if ( empty( trim( $style ) ) ) {
									continue;
								}
								list( $style_key, $style_val ) = explode( ':', $style );

								$style_key = trim( $style_key );
								$style_val = trim( $style_val );

								$normalized_style .= "{$style_key}:{$style_val};";
							}
							$val = $normalized_style;
						}
						$output .= str_repeat( $tree_indent, $tag_indent + 1 ) . "{$display_name}=\"{$val}\"\n";
					}
				}

				// Self-contained tags contain their inner contents as modifiable text.
				$modifiable_text = $processor->get_modifiable_text();
				if ( '' !== $modifiable_text ) {
					$output .= str_repeat( $tree_indent, $tag_indent + 1 ) . "\"{$modifiable_text}\"\n";
				}

				if ( 'html' === $namespace && 'TEMPLATE' === $token_name ) {
					$output .= str_repeat( $tree_indent, $indent_level ) . "content\n";
					++$indent_level;
				}

				break;

			case '#cdata-section':
			case '#text':
				$text_content = $processor->get_modifiable_text();
				if ( '' === trim( $text_content, " \f\t\r\n" ) ) {
					break;
				}
				$was_text = true;
				if ( '' === $text_node ) {
					$text_node .= str_repeat( $tree_indent, $indent_level ) . '"';
				}
				$text_node .= $text_content;
				break;

			case '#funky-comment':
				// Comments must be "<" then "!-- " then the data then " -->".
				$output .= str_repeat( $tree_indent, $indent_level ) . "<!-- {$processor->get_modifiable_text()} -->\n";
				break;

			case '#comment':
				// Comments must be "<" then "!--" then the data then "-->".
				$comment = "<!--{$processor->get_full_comment_text()}-->";

				// Maybe the comment is a block delimiter.
				$parser           = new WP_Block_Parser();
				$parser->document = $comment;
				$parser->offset   = 0;
				list( $delimiter_type, $block_name, $block_attrs, $start_offset, $token_length ) = $parser->next_token();

				switch ( $delimiter_type ) {
					case 'block-opener':
					case 'void-block':
						$output .= str_repeat( $tree_indent, $indent_level ) . "BLOCK[\"{$block_name}\"]\n";

						if ( 'block-opener' === $delimiter_type ) {
							$block_context[] = $block_name;
							++$indent_level;
						}

						// If they're no attributes, we're done here.
						if ( empty( $block_attrs ) ) {
							break;
						}

						// Normalize attribute order.
						ksort( $block_attrs, SORT_STRING );

						if ( isset( $block_attrs['className'] ) ) {
							// Normalize class name order (and de-duplicate), as we need to be tolerant of different orders.
							// (Style attributes don't need this treatment, as they are parsed into a nested array.)
							$block_class_processor = new WP_HTML_Tag_Processor( '<div>' );
							$block_class_processor->next_token();
							$block_class_processor->set_attribute( 'class', $block_attrs['className'] );
							$class_names = iterator_to_array( $block_class_processor->class_list() );
							sort( $class_names, SORT_STRING );
							$block_attrs['className'] = implode( ' ', $class_names );
						}

						$block_attrs = json_encode( $block_attrs, JSON_PRETTY_PRINT );
						// Fix indentation by "halving" it (2 spaces instead of 4).
						// Additionally, we need to indent each line by the current indentation level.
						$block_attrs = preg_replace( '/^( +)\1/m', str_repeat( $tree_indent, $indent_level ) . '$1', $block_attrs );
						// Finally, indent the first line, and the last line (with the closing curly brace).
						$output .= str_repeat( $tree_indent, $indent_level ) . substr( $block_attrs, 0, -1 ) . str_repeat( $tree_indent, $indent_level ) . "}\n";
						break;
					case 'block-closer':
						// Is this a closer for the currently open block?
						if ( ! empty( $block_context ) && end( $block_context ) === $block_name ) {
							// If it's a closer, we don't add it to the output.
							// Instead, we decrease indentation and remove the block from block context stack.
							--$indent_level;
							array_pop( $block_context );
						}
						break;
					default: // Not a block delimiter.
						$output .= str_repeat( $tree_indent, $indent_level ) . $comment . "\n";
						break;
				}
				break;
			default:
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				$serialized_token_type = var_export( $processor->get_token_type(), true );
				throw new Exception( "Unhandled token type for tree construction: {$serialized_token_type}" );
		}
	}

	if ( null !== $processor->get_unsupported_exception() ) {
		throw $processor->get_unsupported_exception();
	}

	if ( null !== $processor->get_last_error() ) {
		throw new Exception( "Parser error: {$processor->get_last_error()}" );
	}

	if ( $processor->paused_at_incomplete_token() ) {
		throw new Exception( 'Paused at incomplete token.' );
	}

	if ( '' !== $text_node ) {
		$output .= "{$text_node}\"\n";
	}

	return $output;
}
