<?php

// PHPUnit is slow to load, so I'll just run this file directly.
if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
	require __DIR__ . '/class-wp-html-attribute-token.php';
	require __DIR__ . '/class-wp-html-span.php';
	require __DIR__ . '/class-wp-html-text-replacement.php';
	require __DIR__ . '/class-wp-html-tag-processor.php';
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

// Could be just WP_HTML_Node actually
class WP_HTML_Element {
	const MARKER = -1;
	public $tag;
	public $attributes;
	public $is_closer;
	public $is_opener;
	public $tag_processor_bookmark;
	public function __construct( $tag, $attributes = null, $is_opener = true ) {
		$this->tag        = $tag;
		$this->attributes = $attributes;
		$this->is_opener  = $is_opener;
		$this->is_closer  = ! $is_opener;
	}

	public function equivalent( WP_HTML_Element $other ) {
		if ( $this->is_closer !== $other->is_closer ) {
			return false;
		}

		if ( $this->tag !== $other->tag ) {
			return false;
		}

		if ( count( $this->attributes ) !== count( $other->attributes ) ) {
			return false;
		}

		$attributes_match = true;
		foreach ( $other->attributes as $name => $value ) {
			if ( ! isset( $this->attributes[ $name ] ) || $this->attributes[ $name ] !== $value ) {
				$attributes_match = false;
				break;
			}
		}
		return $attributes_match;
	}

	public function is_marker() {
		return self::MARKER === $this->tag;
	}
}

class WP_HTML_Insertion_Mode {

	const INITIAL            = 'INITIAL';
	const IN_SELECT          = 'IN_SELECT';
	const IN_SELECT_IN_TABLE = 'IN_SELECT_IN_TABLE';
	const IN_CELL            = 'IN_CELL';
	const IN_ROW             = 'IN_ROW';
	const IN_TABLE_BODY      = 'IN_TABLE_BODY';
	const IN_CAPTION         = 'IN_CAPTION';
	const IN_COLUMN_GROUP    = 'IN_COLUMN_GROUP';
	const IN_TABLE           = 'IN_TABLE';
	const IN_HEAD            = 'IN_HEAD';
	const IN_BODY            = 'IN_BODY';
	const IN_FRAMESET        = 'IN_FRAMESET';
	const BEFORE_HEAD        = 'BEFORE_HEAD';
	const TEXT               = 'TEXT';

}

/**
 *
 */
class WP_HTML_Processor extends WP_HTML_Tag_Processor {

	private $tag_processor;
	/**
	 * @var WP_HTML_Element[]
	 */
	private $open_elements = array();
	/**
	 * @var WP_HTML_Element[]
	 */
	private $active_formatting_elements = array();
	private $root_node                  = null;
	private $context_node               = null;
	private $original_insertion_mode    = null;
	private $insertion_mode             = null;

	private $inserted_tokens = array();

	private $head_pointer;
	private $form_pointer;

	public function __construct( $html ) {
		parent::__construct( $html );
		$this->root_node     = new WP_HTML_Element( 'HTML' );
		$this->context_node  = new WP_HTML_Element( 'DOCUMENT' );
		$this->open_elements = array( $this->root_node );
		$this->reset_insertion_mode();
	}

	public function parse_next() {
		return $this->next_tag_in_body_insertion_mode();
		// @TODO:
		// switch($this->insertion_mode) {
		// case WP_HTML_Insertion_Mode::INITIAL:
		// $this->next_tag_in_initial_mode();
		// break;
		// case WP_HTML_Insertion_Mode::BEFORE_HEAD:
		// $this->next_tag_in_before_head_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_HEAD:
		// $this->next_tag_in_head_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_BODY:
		// $this->next_tag_in_body_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_TABLE:
		// $this->next_tag_in_table_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_TABLE_BODY:
		// $this->next_tag_in_table_body_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_ROW:
		// $this->next_tag_in_row_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_CELL:
		// $this->next_tag_in_cell_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_SELECT:
		// $this->next_tag_in_select_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_SELECT_IN_TABLE:
		// $this->next_tag_in_select_in_table_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_CAPTION:
		// $this->next_tag_in_caption_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_COLUMN_GROUP:
		// $this->next_tag_in_column_group_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::IN_FRAMESET:
		// $this->next_tag_in_frameset_insertion_mode();
		// break;
		// case WP_HTML_Insertion_Mode::TEXT:
		// $this->next_tag_in_text_insertion_mode();
		// break;
		// }
	}

	public function next_tag_in_body_insertion_mode() {
		$token = $this->next_token();
		if ( $token->is_opener ) {
			// Should we care?
			// if(self::is_rcdata_element($token->tag)) {
			// $this->original_insertion_mode = $this->insertion_mode;
			// $this->insertion_mode = WP_HTML_Insertion_Mode::TEXT;
			// }
			switch ( $token->tag ) {
				case 'ADDRESS':
				case 'ARTICLE':
				case 'ASIDE':
				case 'BLOCKQUOTE':
				case 'CENTER':
				case 'DETAILS':
				case 'DIALOG':
				case 'DIR':
				case 'DIV':
				case 'DL':
				case 'FIELDSET':
				case 'FIGCAPTION':
				case 'FIGURE':
				case 'FOOTER':
				case 'HEADER':
				case 'HGROUP':
				case 'MAIN':
				case 'MENU':
				case 'NAV':
				case 'OL':
				case 'P':
				case 'SECTION':
				case 'SUMMARY':
				case 'UL':
					// Ignore special rules for 'PRE' and 'LISTING'
				case 'PRE':
				case 'LISTING':
					if ( $this->is_element_in_button_scope( 'P' ) ) {
						$this->close_p_element();
					}
					$this->insert_element( $token );
					break;
				// A start tag whose tag name is "h1", "h2", "h3", "h4", "h5", or "h6"
				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
					if ( $this->is_element_in_button_scope( 'P' ) ) {
						$this->close_p_element();
					}
					if ( in_array( $this->current_node()->tag, array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ) ) ) {
						$this->pop_open_element();
					}
					$this->insert_element( $token );
					break;
				case 'FORM':
					if ( $this->form_pointer ) {
						$this->ignore_token( $token );
						return $this->next_tag();
					}
					if ( $this->is_element_in_button_scope( 'P' ) ) {
						$this->close_p_element();
					}
					$this->form_pointer = $token;
					$this->insert_element( $token );
					break;
				case 'LI':
					$i = count( $this->open_elements ) - 1;
					while ( true ) {
						$node = $this->open_elements[ $i ];
						if ( $node->tag === 'LI' ) {
							$this->generate_implied_end_tags(
								array(
									'except_for' => array( 'LI' ),
								)
							);
							$this->pop_until_tag_name( 'LI' );
							break;
						} elseif ( self::is_special_element( $node->tag, array( 'ADDRESS', 'DIV', 'P' ) ) ) {
							break;
						} else {
							--$i;
							$node = $this->open_elements[ $i ];
						}
					}

					if ( $this->is_element_in_button_scope( 'P' ) ) {
						$this->close_p_element();
					}
					$this->insert_element( $token );
					break;
				case 'DD':
				case 'DT':
					$i = count( $this->open_elements ) - 1;
					while ( true ) {
						$node = $this->open_elements[ $i ];
						if ( $node->tag === 'DD' ) {
							$this->generate_implied_end_tags(
								array(
									'except_for' => array( 'DD' ),
								)
							);
							$this->pop_until_tag_name( 'DD' );
							break;
						} elseif ( $node->tag === 'DT' ) {
							$this->generate_implied_end_tags(
								array(
									'except_for' => array( 'DT' ),
								)
							);
							$this->pop_until_tag_name( 'DT' );
							break;
						} elseif ( self::is_special_element( $node->tag, array( 'ADDRESS', 'DIV', 'P' ) ) ) {
							break;
						} else {
							--$i;
							$node = $this->open_elements[ $i ];
						}
					}

					if ( $this->is_element_in_button_scope( 'P' ) ) {
						$this->close_p_element();
					}
					$this->insert_element( $token );
					break;
				case 'PLAINTEXT':
					throw new Exception( 'PLAINTEXT not implemented yet' );
				case 'BUTTON':
					if ( $this->is_element_in_button_scope( 'BUTTON' ) ) {
						$this->generate_implied_end_tags();
						$this->pop_until_tag_name( 'BUTTON' );
					}
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					break;
				case 'A':
					$active_a = null;
					for ( $i = count( $this->active_formatting_elements ) - 1; $i >= 0; --$i ) {
						$elem = $this->active_formatting_elements[ $i ];
						if ( $elem->tag === 'A' ) {
							$active_a = $elem;
							break;
						} elseif ( $elem->is_marker() ) {
							break;
						}
					}

					if ( $active_a ) {
						$this->parse_error();
						// @TODO:
						// Run the adoption agency algorithm with the tag name "a".
					}

					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					break;
				case 'B':
				case 'BIG':
				case 'CODE':
				case 'EM':
				case 'FONT':
				case 'I':
				case 'S':
				case 'SMALL':
				case 'STRIKE':
				case 'STRONG':
				case 'TT':
				case 'U':
					$this->reconstruct_active_formatting_elements();
					$this->push_active_formatting_element( $token );
					$this->insert_element( $token );
					break;
				case 'NOBR':
					$this->reconstruct_active_formatting_elements();
					if ( $this->is_element_in_scope( 'NOBR' ) ) {
						$this->parse_error();
						$this->adoption_agency_algorithm( $token );
						$this->reconstruct_active_formatting_elements();
					}
					$this->insert_element( $token );
					$this->push_active_formatting_element( $token );
					break;
				case 'APPLET':
				case 'MARQUEE':
				case 'OBJECT':
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					$this->active_formatting_elements[] = new WP_HTML_Element( WP_HTML_Element::MARKER );
					break;
				case 'TABLE':
					$this->insert_element( $token );
					$this->insertion_mode = WP_HTML_Insertion_Mode::IN_TABLE;
					break;
				case 'AREA':
				case 'BR':
				case 'EMBED':
				case 'IMG':
				case 'KEYGEN':
				case 'WBR':
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					$this->pop_open_element();
					// @TODO: Acknowledge the token's self-closing flag, if it is set.
					break;
				case 'PARAM':
				case 'SOURCE':
				case 'TRACK':
					$this->insert_element( $token );
					$this->pop_open_element();
					break;
				case 'HR':
					if ( $this->is_element_in_button_scope( 'P' ) ) {
						$this->close_p_element();
					}
					$this->insert_element( $token );
					$this->pop_open_element();
					break;
				case 'IMAGE':
					$this->parse_error();
					// Change the tag name to "img" and reprocess the token.
					throw new Exception( 'IMAGE not implemented yet' );
				case 'TEXTAREA':
					$this->insert_element( $token );
					$this->original_insertion_mode = $this->insertion_mode;
					$this->insertion_mode          = WP_HTML_Insertion_Mode::TEXT;
					break;

				case 'XMP':
					if ( $this->is_element_in_button_scope( 'P' ) ) {
						$this->close_p_element();
					}
					$this->reconstruct_active_formatting_elements();
					// @TODO: Follow the generic raw text element parsing algorithm.
					throw new Exception( 'XMP not implemented yet' );
					break;
				case 'IFRAME':
				case 'NOEMBED':
				case 'NOSCRIPT':
					// @TODO: Follow the generic raw text element parsing algorithm.
					throw new Exception( $token->tag . ' not implemented yet' );
				case 'SELECT':
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					if ( in_array(
						$this->insertion_mode,
						array(
							WP_HTML_Insertion_Mode::IN_TABLE,
							WP_HTML_Insertion_Mode::IN_CAPTION,
							WP_HTML_Insertion_Mode::IN_TABLE_BODY,
							WP_HTML_Insertion_Mode::IN_ROW,
							WP_HTML_Insertion_Mode::IN_CELL,
						)
					) ) {
						$this->insertion_mode = WP_HTML_Insertion_Mode::IN_SELECT_IN_TABLE;
					} else {
						$this->insertion_mode = WP_HTML_Insertion_Mode::IN_SELECT;
					}
					break;
				case 'OPTGROUP':
				case 'OPTION':
					if ( 'OPTION' === $token->tag ) {
						$this->pop_open_element();
					}
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					break;
				case 'RB':
				case 'RTC':
					if ( $this->is_element_in_scope( 'RB' ) || $this->is_element_in_scope( 'RTC' ) ) {
						$this->parse_error();
						$this->adoption_agency_algorithm( $token );
						$this->reconstruct_active_formatting_elements();
					}
					$this->insert_element( $token );
					break;
				case 'RP':
				case 'RT':
					if ( $this->is_element_in_scope( 'RP' ) || $this->is_element_in_scope( 'RT' ) ) {
						$this->parse_error();
						$this->adoption_agency_algorithm( $token );
						$this->reconstruct_active_formatting_elements();
					}
					$this->insert_element( $token );
					break;
				case 'MATH':
					throw new Exception( 'MATH not implemented yet' );
				case 'SVG':
					throw new Exception( 'SVG not implemented yet' );
				case 'CAPTION':
				case 'COL':
				case 'COLGROUP':
				case 'FRAME':
				case 'HEAD':
				case 'TBODY':
				case 'TD':
				case 'TFOOT':
				case 'TH':
				case 'THEAD':
				case 'TR':
					$this->parse_error();
					// Ignore the token.
					return;
				default:
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					break;
			}
		} else {
			switch ( $token->tag ) {
				case 'ADDRESS':
				case 'ARTICLE':
				case 'ASIDE':
				case 'BLOCKQUOTE':
				case 'CENTER':
				case 'DETAILS':
				case 'DIALOG':
				case 'DIR':
				case 'DIV':
				case 'DL':
				case 'FIELDSET':
				case 'FIGCAPTION':
				case 'FIGURE':
				case 'FOOTER':
				case 'HEADER':
				case 'HGROUP':
				case 'MAIN':
				case 'MENU':
				case 'NAV':
				case 'OL':
				case 'P':
				case 'SECTION':
				case 'SUMMARY':
				case 'UL':
					if ( $this->is_element_in_scope( $token->tag ) ) {
						$this->ignore_token( $token );
						$this->parse_error();
						return $this->next_tag();
					}
					$this->generate_implied_end_tags();
					$this->pop_until_tag_name( $token->tag );
					break;
				case 'FORM':
					if ( $this->form_pointer ) {
						$this->ignore_token( $token );
						$this->parse_error();
						return $this->next_tag();
					}
					if ( $this->is_element_in_scope( $this->form_pointer ) ) {
						$this->ignore_token( $token );
						$this->parse_error();
						return $this->next_tag();
					}
					$this->generate_implied_end_tags();
					array_splice( $this->open_elements, array_search( $this->form_pointer, $this->open_elements ), 1 );
					$this->form_pointer = null;
					break;
				case 'P':
					if ( ! $this->is_element_in_button_scope( 'P' ) ) {
						// Parse error, insert an HTML element for a "p" start tag token with no attributes.
						$this->parse_error();
						$this->insert_element( new WP_HTML_Element( 'P', array() ) );
					}
					$this->close_p_element();
					break;
				case 'LI':
					if ( $this->is_element_in_list_item_scope( 'LI' ) ) {
						$this->ignore_token( $token );
						$this->parse_error();
						return $this->next_tag();
					}
					$this->generate_implied_end_tags();
					$this->pop_until_tag_name( 'LI' );
					break;
				case 'DD':
				case 'DT':
					if ( $this->is_element_in_scope( $token->tag ) ) {
						$this->ignore_token( $token );
						$this->parse_error();
						return $this->next_tag();
					}
					$this->generate_implied_end_tags();
					$this->pop_until_tag_name( $token->tag );
					break;
				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
					if ( $this->is_element_in_scope( array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ) ) ) {
						$this->ignore_token( $token );
						$this->parse_error();
						return $this->next_tag();
					}
					$this->generate_implied_end_tags();
					$this->pop_until_tag_name( array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ) );
					break;
				case 'A':
				case 'B':
				case 'BIG':
				case 'CODE':
				case 'EM':
				case 'FONT':
				case 'I':
				case 'S':
				case 'SMALL':
				case 'STRIKE':
				case 'STRONG':
				case 'TT':
				case 'U':
					$this->parse_error();
					$this->adoption_agency_algorithm( $token );
					break;

				case 'APPLET':
				case 'MARQUEE':
				case 'OBJECT':
					if ( $this->is_element_in_scope( $token->tag ) ) {
						$this->ignore_token( $token );
						$this->parse_error();
						return $this->next_tag();
					}
					$this->generate_implied_end_tags();
					if ( $this->current_node()->tag !== $token->tag ) {
						$this->parse_error();
					}
					$this->pop_until_tag_name( $token->tag );
					$this->clear_active_formatting_elements_up_to_last_marker();
					break;
				case 'BR':
					// This should never happen since Tag_Processor corrects that
				default:
					$i = count( $this->open_elements ) - 1;
					while ( true ) {
						$node = $this->open_elements[ $i ];
						if ( $node->tag === $token->tag ) {
							$this->generate_implied_end_tags(
								array(
									'except_for' => array( $token->tag ),
								)
							);
							$this->pop_until_node( $node );
							break;
						} elseif ( $this->is_special_element( $node->tag ) ) {
							$this->ignore_token( $token );
							$this->parse_error();
							return $this->next_tag();
						} else {
							--$i;
						}
					}
					break;
			}
		}
	}

	private $element_bookmark_idx = 0;
	private function next_token() {
		if ( ! $this->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			return false;
		}

		$consumed_node = new WP_HTML_Element(
			$this->get_tag(),
			array(),
			! $this->is_tag_closer()
		);

		$consumed_node->tag_processor_bookmark = $this->set_bookmark(
			'__internal_' . ( $this->element_bookmark_idx++ )
		);

		return $consumed_node;
	}

	const ANY_OTHER_END_TAG = 1;
	private function adoption_agency_algorithm( WP_HTML_Element $token ) {
		$subject = $token->tag;
		if (
			$this->current_node()->tag === $subject
			&& ! in_array( $subject, $this->active_formatting_elements, true )
		) {
			$this->pop_open_element();
			return;
		}

		$outer_loop_counter = 0;
		while ( ++$outer_loop_counter < 8 ) {
			/*
			 * Let __formatting element__ be the last element in the list of active
			 * formatting elements that:
			 *    - is between the end of the list and the last marker in the list,
			 *      if any, or the start of the list otherwise, and
			 *    - has the same tag name as the token.
			 */
			$formatting_element     = null;
			$formatting_element_idx = -1;
			for ( $i = count( $this->active_formatting_elements ) - 1; $i >= 0; $i-- ) {
				$candidate = $this->active_formatting_elements[ $i ];
				if ( $candidate->is_marker() ) {
					break;
				}
				if ( $candidate->tag === $subject ) {
					$formatting_element     = $candidate;
					$formatting_element_idx = $i;
					break;
				}
			}
			// If there is no such element, then abort these steps and instead act as
			// described in the "any other end tag" entry below.
			if ( null === $formatting_element ) {
				return self::ANY_OTHER_END_TAG;
			}

			// If formatting element is not in the stack of open elements, then this is
			// a parse error; remove the element from the list, and return.
			if ( ! in_array( $formatting_element, $this->open_elements, true ) ) {
				array_splice( $this->active_formatting_elements, $formatting_element_idx, 1 );
				$this->parse_error();
				return;
			}

			// If formatting element is not in scope, then this is a parse error; return
			if ( ! $this->is_element_in_scope( $formatting_element->tag ) ) {
				$this->parse_error();
				return;
			}

			// If formatting element is not the current node, then this is a parse error.
			// (But do not return.)
			if ( $formatting_element !== $this->current_node() ) {
				$this->parse_error();
			}

			/*
			 * Let furthest block be the topmost node in the stack of open elements that
			 * is lower in the stack than formatting element, and is an element in the
			 * special category. There might not be one.
			 */
			$furthest_block = null;
			for ( $i = count( $this->open_elements ) - 1; $i >= 0; $i-- ) {
				$node = $this->open_elements[ $i ];
				if ( $node === $formatting_element ) {
					break;
				}
				if ( $this->is_special_element( $node->tag ) ) {
					$furthest_block = $node;
					break;
				}
			}

			// If there is no such node, then the UA must first pop all the nodes from
			// the bottom of the stack of open elements, from the current node up to
			// and including formatting element, then remove formatting element from
			// the list of active formatting elements, and finally abort these steps.
			if ( null === $furthest_block ) {
				$this->pop_until_node( $formatting_element );
				array_splice( $this->active_formatting_elements, $formatting_element_idx, 1 );
				return;
			}

			// Let common ancestor be the element immediately above formatting element
			// in the stack of open elements.
			$formatting_elem_stack_index = array_search( $formatting_element, $this->open_elements, true );
			$common_ancestor             = $this->open_elements[ $formatting_elem_stack_index - 1 ];

			// Let a bookmark note the position of formatting element in the list of
			// active formatting elements relative to the elements on either side of it
			// in the list.
			$bookmark = $formatting_element_idx;

			// Let node and last node be furthest block.
			$node                     = $last_node = $furthest_block;
			$node_open_elements_index = array_search( $node, $this->open_elements, true );

			$prev_node_open_elements_index = -1;
			$inner_loop_counter            = 0;
			while ( true ) {
				$inner_loop_counter++;

				/**
				 * Let node be the element immediately above node in the stack of open elements,
				 * or if node is no longer in the stack of open elements (e.g. because it got
				 * removed by this algorithm), the element that was immediately above node in
				 * the stack of open elements before node was removed.
				 */
				$node_open_elements_index = array_search( $node, $this->open_elements, true );
				if ( false === $node_open_elements_index ) {
					$node_open_elements_index = $prev_node_open_elements_index;
					return;
				}
				--$node_open_elements_index;
				$node                          = $this->open_elements[ $node_open_elements_index ];
				$prev_node_open_elements_index = $node_open_elements_index;

				// If node is formatting element, then break.
				if ( $node === $formatting_element ) {
					break;
				}

				/*
				 * If inner loop counter is greater than 3 and node is in the list
				 * of active formatting elements, then remove node from the list of
				 * active formatting elements.
				 */
				if ( $inner_loop_counter > 3 && in_array( $node, $this->active_formatting_elements, true ) ) {
					$node_formatting_idx = array_search( $node, $this->active_formatting_elements, true );
					array_splice( $this->active_formatting_elements, $node_formatting_idx, 1 );
				}

				/*
				 * If node is not in the list of active formatting elements, then remove
				 * node from the stack of open elements and continue.
				 */
				if ( ! in_array( $node, $this->active_formatting_elements, true ) ) {
					array_splice( $this->open_elements, $node_open_elements_index, 1 );
					continue;
				}

				/*
				 * Create an element for the token for which the element node was created,
				 * in the HTML namespace, with common ancestor as the intended parent.
				 *
				 * Replace the entry for node in the list of active formatting elements with an entry
				 * for the new element.
				 *
				 * Replace the entry for node in the stack of open elements with an entry for
				 * the new element.
				 *
				 * Let node be the new element.
				 */
				$new_node            = new WP_HTML_Element( $node->tag, array() );
				$node_formatting_idx = array_search( $node, $this->active_formatting_elements, true );
				$this->active_formatting_elements[ $node_formatting_idx ] = $new_node;

				$node_open_elements_index                         = array_search( $node, $this->open_elements, true );
				$this->open_elements[ $node_open_elements_index ] = $new_node;
				$node = $new_node;

				/*
				 * If last node is furthest block, then move the aforementioned bookmark to be
				 * immediately after the new node in the list of active formatting elements.
				 */
				if ( $last_node === $furthest_block ) {
					$bookmark = $node_formatting_idx + 1;
				}

				// Append last node to node.
				// @TODO

				// Set last node to node.
				$last_node = $node;
			}

			// Insert whatever last node ended up being in the previous step at the appropriate place
			// for inserting a node, but using common ancestor as the override target.
			// @TODO

			// Create an element for the token for which formatting element was created, in the HTML
			// namespace, with furthest block as the intended parent.
			$new_element = new WP_HTML_Element( $formatting_element->tag, array() );

			// Take all of the child nodes of furthest block and append them to the element created in
			// the last step.
			// @TODO

			// Append that new element to furthest block.
			// @TODO

			// Remove formatting element from the list of active formatting elements, and insert the new
			// element into the list of active formatting elements at the position of the aforementioned
			// bookmark.
			$formatting_element_idx = array_search( $formatting_element, $this->active_formatting_elements, true );
			array_splice( $this->active_formatting_elements, $formatting_element_idx, 1, array( $new_element ) );
			array_splice( $this->active_formatting_elements, $bookmark, 0, array( $new_element ) );

			// Remove formatting element from the stack of open elements, and insert the new element into
			// the stack of open elements immediately below the position of furthest block in that stack.
			$formatting_element_idx = array_search( $formatting_element, $this->active_formatting_elements, true );
			array_splice( $this->active_formatting_elements, $formatting_element_idx, 1, array( $new_element ) );

			$furthest_block_idx = array_search( $furthest_block, $this->open_elements, true );
			array_splice( $this->open_elements, $furthest_block_idx + 1, 0, array( $new_element ) );
		}
	}

	/*
		@TODO Implement https://html.spec.whatwg.org/multipage/parsing.html#insert-a-foreign-element

		Let the adjusted insertion location be the appropriate place for inserting a node.

		Let element be the result of creating an element for the token in the given namespace, with the intended parent being the element in which the adjusted insertion location finds itself.

		If it is possible to insert element at the adjusted insertion location, then:

		If the parser was not created as part of the HTML fragment parsing algorithm, then push a new element queue onto element's relevant agent's custom element reactions stack.

		Insert element at the adjusted insertion location.

		If the parser was not created as part of the HTML fragment parsing algorithm, then pop the element queue from element's relevant agent's custom element reactions stack, and invoke custom element reactions in that queue.

		If the adjusted insertion location cannot accept more elements, e.g. because it's a Document that already has an element child, then element is dropped on the floor.

		Push element onto the stack of open elements so that it is the new current node.

		Return element.

	*/
	private function insert_html_element( $node ) {
		if ( ! $node->is_closer ) {
			$this->insert_element( $node );
		}
		$this->inserted_tokens[] = $node;
	}

	private function ignore_token( $token ) {
		if ( $token->tag_processor_bookmark ) {
			$this->release_bookmark( $token->tag_processor_bookmark );
			$token->tag_processor_bookmark = null;
		}
		return;
	}

	private function insert_element( $node ) {
		$this->open_elements[] = $node;
	}

	private function parse_error() {
		// Noop for now
	}

	private function pop_until_tag_name( $tags ) {
		if ( ! is_array( $tags ) ) {
			$tags = array( $tags );
		}
		while ( ! in_array( $this->current_node()->tag, $tags ) ) {
			$this->pop_open_element();
		}
	}

	private function pop_until_node( $node ) {
		do {
			$popped = $this->pop_open_element();
		} while ( $popped !== $node );
	}

	private function pop_open_element() {
		$popped = array_pop( $this->open_elements );
		if ( $popped->tag_processor_bookmark ) {
			$this->release_bookmark( $popped->tag_processor_bookmark );
			$popped->tag_processor_bookmark = null;
		}
		return $popped;
	}

	private function generate_implied_end_tags( $options = null ) {
		while ( $this->should_generate_implied_end_tags( $options ) ) {
			yield $this->pop_open_element();
		}
	}

	private function current_node() {
		return end( $this->open_elements );
	}

	private function close_p_element() {
		$this->generate_implied_end_tags(
			array(
				'except_for' => array( 'P' ),
			)
		);
		// If the current node is not a p element, then this is a parse error.
		if ( $this->current_node()->tag !== 'P' ) {
			$this->parse_error();
		}
		$this->pop_until_tag_name( 'P' );
	}

	private function should_generate_implied_end_tags( $options = null ) {
		$current_tag_name = $this->current_node()->tag;
		if ( null !== $options && isset( $options['except_for'] ) && in_array( $current_tag_name, $options['except_for'] ) ) {
			return false;
		}
		switch ( $current_tag_name ) {
			case 'DD':
			case 'DT':
			case 'LI':
			case 'OPTION':
			case 'OPTGROUP':
			case 'P':
			case 'RB':
			case 'RP':
			case 'RT':
			case 'RTC':
				return true;
		}

		$thoroughly = null !== $options && isset( $options['thoroughly'] ) && $options['thoroughly'];
		if ( $thoroughly ) {
			switch ( $current_tag_name ) {
				case 'TBODY':
				case 'TFOOT':
				case 'THEAD':
				case 'TD':
				case 'TH':
				case 'TR':
					return true;
			}
		}

		return false;
	}

	/**
	 * https://html.spec.whatwg.org/multipage/parsing.html#the-list-of-active-formatting-elements
	 */
	private function push_active_formatting_element( $node ) {
		$count = 0;
		for ( $i = count( $this->active_formatting_elements ) - 1; $i >= 0; $i-- ) {
			$formatting_element = $this->active_formatting_elements[ $i ];
			if ( $formatting_element->is_marker() ) {
				break;
			}
			if ( ! $node->equivalent( $node ) ) {
				continue;
			}
			$count++;
			if ( $count === 3 ) {
				array_splice( $this->active_formatting_elements, $i, 1 );
				break;
			}
		}
		$this->active_formatting_elements[] = $node;
	}

	private function reconstruct_active_formatting_elements() {
		if ( empty( $this->active_formatting_elements ) ) {
			return;
		}
		$i          = count( $this->active_formatting_elements ) - 1;
		$last_entry = $this->active_formatting_elements[ $i ];
		if ( $last_entry->is_marker() || in_array( $last_entry, $this->open_elements, true ) ) {
			return;
		}
		$entry = $last_entry;
		while ( true ) {
			if ( $i <= 0 ) {
				break;
			}
			--$i;
			$entry = $this->active_formatting_elements[ $i ];
			if ( $entry->is_marker() || in_array( $entry, $this->open_elements, true ) ) {
				break;
			}
		}
		while ( true ) {
			++$i;
			$entry = $this->active_formatting_elements[ $i ];
			if ( $entry === $last_entry ) {
				break;
			}

			// @TODO:
			// Create: Insert an HTML element for the token for which the element entry
			// was created, to obtain new element.
			$new_element = new WP_HTML_Element( $entry->tag, $entry->attributes );

			// Replace the entry for entry in the list with an entry for new element.
			$index = array_search( $entry, $this->active_formatting_elements, true );

			$this->active_formatting_elements[ $index ] = $new_element;
			if ( $index === count( $this->active_formatting_elements ) - 1 ) {
				break;
			}
		}
	}

	private function clear_active_formatting_elements_up_to_last_marker() {
		while ( ! empty( $this->active_formatting_elements ) ) {
			$entry = array_pop( $this->active_formatting_elements );
			if ( $entry->is_marker() ) {
				break;
			}
		}
	}

	private function is_element_in_select_scope( $target_node ) {
		return $this->is_element_in_specific_scope(
			$target_node,
			array(
				'optgroup',
				'option',
			),
			array(
				'negative_match' => 'true',
			)
		);
	}

	private function is_element_in_table_scope( $target_node ) {
		return $this->is_element_in_specific_scope(
			$target_node,
			array(
				'html',
				'table',
				'template',
			)
		);
	}

	private function is_element_in_button_scope( $target_node ) {
		return $this->is_element_in_scope(
			$target_node,
			array(
				'button',
			)
		);
	}

	private function is_element_in_list_item_scope( $target_node ) {
		return $this->is_element_in_scope(
			$target_node,
			array(
				'li',
				'dd',
				'dt',
			)
		);
	}

	private function is_element_in_scope( $target_node, $additional_elements = array() ) {
		return $this->is_element_in_specific_scope(
			$target_node,
			array_merge(
				array(
					'applet',
					'caption',
					'html',
					'table',
					'td',
					'th',
					'marquee',
					'object',
					'template',
				),
				$additional_elements
			)
		);
	}

	/**
	 * https://html.spec.whatwg.org/multipage/parsing.html#the-stack-of-open-elements
	 */
	private function is_element_in_specific_scope( $target_node, $element_types_list, $options = array() ) {
		$negative_match = isset( $options['negative_match'] ) ? $options['negative_match'] : false;
		$i              = count( $this->open_elements ) - 1;
		while ( true ) {
			$node = $this->open_elements[ $i ];

			if ( $node === $target_node ) {
				return true;
			}

			$is_in_the_list = in_array( $node->tag, $element_types_list, true );
			$failure        = $negative_match ? $is_in_the_list : ! $is_in_the_list;
			if ( $failure ) {
				return false;
			}
		}
	}

	/**
	 * https://html.spec.whatwg.org/multipage/parsing.html#reset-the-insertion-mode-appropriately
	 */
	private function reset_insertion_mode() {
		$last = false;
		$node = end( $this->open_elements );

		while ( true ) {
			if ( count( $this->open_elements ) === 1 && $node === reset( $this->open_elements ) ) {
				$last = true;
				$node = $this->context_node;
			}

			if ( $node->tag === 'select' ) {
				if ( $last ) {
					break;
				}

				$ancestor = $node;
				while ( true ) {
					if ( $ancestor === $this->open_elements[0] ) {
						break;
					}

					$index    = array_search( $ancestor, $this->open_elements );
					$ancestor = $this->open_elements[ $index - 1 ];
					if ( $ancestor->tag === 'template' ) {
						break;
					}

					if ( $ancestor->tag === 'table' ) {
						$this->insertion_mode = wP_HTML_Insertion_Mode::IN_SELECT_IN_TABLE;
						return;
					}
				}

				$this->insertion_mode = wP_HTML_Insertion_Mode::IN_SELECT;
				return;
			}

			switch ( $node->tag ) {
				case 'TD':
				case 'TH':
					if ( ! $last ) {
						$this->insertion_mode = wP_HTML_Insertion_Mode::IN_CELL;
						return;
					}
					break;
				case 'TR':
					$this->insertion_mode = wP_HTML_Insertion_Mode::IN_ROW;
					return;
				case 'TBODY':
				case 'THEAD':
				case 'TFOOT':
					$this->insertion_mode = wP_HTML_Insertion_Mode::IN_TABLE_BODY;
					return;
				case 'CAPTION':
					$this->insertion_mode = wP_HTML_Insertion_Mode::IN_CAPTION;
					return;
				case 'COLGROUP':
					$this->insertion_mode = wP_HTML_Insertion_Mode::IN_COLUMN_GROUP;
					return;
				case 'TABLE':
					$this->insertion_mode = wP_HTML_Insertion_Mode::IN_TABLE;
					return;
				case 'TEMPLATE':
					// TODO: implement the current template insertion mode
					$this->insertion_mode = 0;
					return;
				case 'HEAD':
					if ( ! $last ) {
						$this->insertion_mode = wP_HTML_Insertion_Mode::IN_HEAD;
						return;
					}
					break;
				case 'BODY':
					$this->insertion_mode = wP_HTML_Insertion_Mode::IN_BODY;
					return;
				case 'FRAMESET':
					$this->insertion_mode = wP_HTML_Insertion_Mode::IN_FRAMESET;
					return;
				case 'HTML':
					// TODO: implement the head element pointer
					$this->insertion_mode = WP_HTML_Insertion_Mode::BEFORE_HEAD;
					return;
				default:
					if ( $last ) {
						$this->insertion_mode = wP_HTML_Insertion_Mode::IN_BODY;
						return;
					}
			}

			$index = array_search( $node, $this->open_elements );
			$node  = $this->open_elements[ $index - 1 ];
		}

		$this->insertion_mode = wP_HTML_Insertion_Mode::IN_BODY;
	}


	private static function is_special_element( $tag_name, $except = null ) {
		if ( null !== $except && in_array( $tag_name, $except, true ) ) {
			return false;
		}

		switch ( $tag_name ) {
			case 'ADDRESS':
			case 'APPLET':
			case 'AREA':
			case 'ARTICLE':
			case 'ASIDE':
			case 'BASE':
			case 'BASEFONT':
			case 'BGSOUND':
			case 'BLOCKQUOTE':
			case 'BODY':
			case 'BR':
			case 'BUTTON':
			case 'CAPTION':
			case 'CENTER':
			case 'COL':
			case 'COLGROUP':
			case 'DD':
			case 'DETAILS':
			case 'DIR':
			case 'DIV':
			case 'DL':
			case 'DT':
			case 'EMBED':
			case 'FIELDSET':
			case 'FIGCAPTION':
			case 'FIGURE':
			case 'FOOTER':
			case 'FORM':
			case 'FRAME':
			case 'FRAMESET':
			case 'H1':
			case 'H2':
			case 'H3':
			case 'H4':
			case 'H5':
			case 'H6':
			case 'HEAD':
			case 'HEADER':
			case 'HGROUP':
			case 'HR':
			case 'HTML':
			case 'IFRAME':
			case 'IMG':
			case 'INPUT':
			case 'ISINDEX':
			case 'LI':
			case 'LINK':
			case 'LISTING':
			case 'MAIN':
			case 'MARQUEE':
			case 'MENU':
			case 'MENUITEM':
			case 'META':
			case 'NAV':
			case 'NOEMBED':
			case 'NOFRAMES':
			case 'NOSCRIPT':
			case 'OBJECT':
			case 'OL':
			case 'P':
			case 'PARAM':
			case 'PLAINTEXT':
			case 'PRE':
			case 'SCRIPT':
			case 'SECTION':
			case 'SELECT':
			case 'SOURCE':
			case 'STYLE':
			case 'SUMMARY':
			case 'TABLE':
			case 'TBODY':
			case 'TD':
			case 'TEMPLATE':
			case 'TEXTAREA':
			case 'TFOOT':
			case 'TH':
			case 'THEAD':
			case 'TITLE':
			case 'TR':
			case 'TRACK':
			case 'UL':
			case 'WBR':
			case 'XMP':
				return true;
			default:
				return false;
		}
	}

	private static function is_rcdata_element( $tag_name ) {
		switch ( $tag_name ) {
			case 'TITLE':
			case 'TEXTAREA':
			case 'STYLE':
			case 'XMP':
			case 'IFRAME':
			case 'NOEMBED':
			case 'NOFRAMES':
			case 'NOSCRIPT':
				return true;
			default:
				return false;
		}
	}

	private static function is_formatting_element( $tag_name ) {
		switch ( strtoupper( $tag_name ) ) {
			case 'A':
			case 'B':
			case 'BIG':
			case 'CODE':
			case 'EM':
			case 'FONT':
			case 'I':
			case 'NOBR':
			case 'S':
			case 'SMALL':
			case 'STRIKE':
			case 'STRONG':
			case 'TT':
			case 'U':
				return true;
			default:
				return false;
		}
	}

}


$p = new WP_HTML_Processor( '<p>Lorem<b>Ipsum</p>Dolor</b>Sit' );
// The controller's schema is hardcoded, so tests would not be meaningful.
$p->parse_next();

// $this->tag_processor->next_tag(
//     array(
//         'tag_closers' => 'visit',
//     )
// );
// var_dump( $this->tag_processor->get_tag() );
// var_dump( $this->tag_processor->is_tag_closer() );
// $last_parent = end( $this->open_elements );
