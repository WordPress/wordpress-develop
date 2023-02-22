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

define('HTML_DEBUG_MODE', false);
function dbg( $message, $indent = 0 ) {
	if( HTML_DEBUG_MODE ) {
		$indent = str_repeat( ' ', $indent * 2 );
		echo $indent . $message . "\n";
	}
}

class WP_HTML_Token {
	const MARKER = 'MARKER';
	const TAG = 'TAG';
	const TEXT = 'TEXT';

	public $type;

	// For tag tokens
	public $tag;
	public $attributes;
	public $is_closer;
	public $is_opener;
	public $bookmark;

	// For text tokens
	public $value;
	
	static public function marker() {
		return new WP_HTML_Token( self::MARKER );
	}

	static public function tag( $tag, $attributes = null, $is_opener = true, $bookmark = null ) {
		$token = new WP_HTML_Token( self::TAG );
		$token->tag        = $tag;
		$token->attributes = $attributes;
		$token->is_opener  = $is_opener;
		$token->is_closer  = ! $is_opener;
		$token->bookmark = $bookmark;
		return $token;
	}

	static public function text( $text ) {
		$token = new WP_HTML_Token( self::TEXT );
		$token->value = $text;
		return $token;
	}

	public function __construct( $type ) {
		$this->type = $type;
	}

	public function __toString() {
		switch ( $this->type ) {
			case self::MARKER:
				return 'MARKER';
			case self::TAG:
				$attributes = '';
				if($this->attributes) {
					foreach( $this->attributes as $name => $value ) {
						$attributes .= ' ' . $name . '="' . esc_attr( $value ) . '"';
					}
				}
				return sprintf(
					'%s%s%s',
					$this->is_closer ? '/' : '',
					$this->tag,
					$attributes
				);
			case self::TEXT:
				return '#text: ' . trim($this->value);
		}
	}

	public function equivalent( WP_HTML_Token $other ) {
		if ( ! $this->tag || ! $other->tag ) {
			throw new Exception( 'Cannot compare non-tag tokens' );
		}

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
		return self::MARKER === $this->type;
	}

	public function is_tag() {
		return self::TAG === $this->type;
	}

	public function is_text() {
		return self::TEXT === $this->type;
	}
}

class WP_HTML_Node {
	/**
	 * @var WP_HTML_Node
	 */
	public $parent;
	/**
	 * @var WP_HTML_Node[]
	 */
	public $children = array();
	/**
	 * @var WP_HTML_Token
	 */
	public $token;
	public $depth = 1;

	private $type;
	private $value;
	private $tag;

	public function __construct( WP_HTML_Token $token ) {
		$this->token = $token;
		// Just for debugging convenience – remove eventually
		$this->type = $token->type;
		$this->value = $token->value;
		$this->tag = $token->tag;
	}

	public function append_child( WP_HTML_Node $node ) {
		if($node->parent) {
			$node->parent->remove($node);
		}
		$node->parent = $this;
		$this->children[] = $node;
		$node->depth = $this->depth + 1;
	}

	public function remove( WP_HTML_Node $node ) {
		$index = array_search( $node, $this->children, true );
		if ( false !== $index ) {
			unset( $this->children[ $index ] );
		}
	}

	public function __toString() {
		return wp_html_node_to_ascii_tree( $this );
	}
}


function wp_html_node_to_ascii_tree( WP_HTML_Node $node, $prefix = '', $is_last = false ) {
    $ascii_tree = $prefix . ( $node->parent ? ($is_last ? '└─ ' : '├─ ') : '  ' ) . $node->token . "\n";

    // Recursively process the children of the current node
	$children = array_values($node->children);
    $num_children = count( $children );
    for ( $i = 0; $i < $num_children; $i++ ) {
        $child_prefix = $prefix . ( $i == $num_children - 1 ? '   ' : '   ' );
        $is_last_child = ( $i == $num_children - 1 );
        $ascii_tree .= wp_html_node_to_ascii_tree( $children[ $i ], $child_prefix, $is_last_child );
    }

    return $ascii_tree;
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

	/**
	 * @var WP_HTML_Node[]
	 */
	private $open_elements = array();
	/**
	 * @var WP_HTML_Node[]
	 */
	private $active_formatting_elements = array();
	private $root_node                  = null;
	private $context_node               = null;

	/*
	 * WP_HTML_Tag_Processor skips over text nodes and only
	 * processes tags.
	 * 
	 * WP_HTML_Processor needs to process text nodes as well.
	 * 
	 * Whenever the tag processor skips over text to move to
	 * the next tag, the next_token() method emits that text 
	 * as a token and stores the tag in $buffered_tag to be
	 * returned the next time.
	 */
	private $buffered_tag = null;

	private $last_token = null;
	private $inserted_tokens = array();

	const MAX_BOOKMARKS = 1000000;

	public function __construct( $html ) {
		parent::__construct( $html );
		$this->root_node     = new WP_HTML_Node(WP_HTML_Token::tag( 'HTML' ));
		$this->context_node  = new WP_HTML_Node(WP_HTML_Token::tag( 'DOCUMENT' ));
		$this->open_elements = array( $this->root_node );
	}

	public function parse() {
		echo("HTML before main loop:\n");
		echo($this->html);
		echo("\n");
		while ($this->process_next_token()) {
			// ... twiddle thumbs ...
		}
		echo("\n");
		echo("DOM after main loop:\n");
		echo($this->root_node.'');
		echo "\n\n";

		echo "Mem peak usage:" . memory_get_peak_usage(true) . "\n";
	}

	private function process_next_token() {
		$token = $this->next_token();
		if(!$token){
			return false;
		}
		$this->last_token = $token;
		$processed_token = $this->process_token($token);
		$this->last_token = $processed_token;
		return $processed_token;
	}

	private function ignore_token( $ignored_token ) {
		// if ( $ignored_token->bookmark ) {
		// 	// $this->release_bookmark( $ignored_token->bookmark );
		// 	// $ignored_token->bookmark = null;
		// }

		$token = $this->next_token();
		if(!$token){
			return false;
		}
		$processed_token = $this->process_token($token);
		$this->last_token = $processed_token;
		return $processed_token;
	}

	public function process_token(WP_HTML_Token $token) {
		if ( $token->is_text() ) {
			dbg( "Found text node '$token'" );
			dbg( "Inserting text to current node " . $this->current_node()->token->tag, 1 );
			$this->reconstruct_active_formatting_elements();
			$this->insert_text( $token );
		}
		else if ( $token->is_opener ) {
			dbg( "Found {$token->tag} tag opener" );
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
					/*
					 * If the stack of open elements has a p element in button scope,
					 * then close a p element.
					 */
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
					if ( in_array( $this->current_node()->token->tag, array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ) ) ) {
						$this->pop_open_element();
					}
					$this->insert_element( $token );
					break;
				case 'FORM':
					if ( $this->is_element_in_button_scope( 'P' ) ) {
						$this->close_p_element();
					}
					$this->insert_element( $token );
					break;
				case 'LI':
					$i = count( $this->open_elements ) - 1;
					while ( true ) {
						$node = $this->open_elements[ $i ];
						if ( $node->token->tag === 'LI' ) {
							$this->generate_implied_end_tags(
								array(
									'except_for' => array( 'LI' ),
								)
							);
							$this->pop_until_tag_name( 'LI' );
							break;
						} elseif ( self::is_special_element( $node->token->tag, array( 'ADDRESS', 'DIV', 'P' ) ) ) {
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
						if ( $node->token->tag === 'DD' ) {
							$this->generate_implied_end_tags(
								array(
									'except_for' => array( 'DD' ),
								)
							);
							$this->pop_until_tag_name( 'DD' );
							break;
						} elseif ( $node->token->tag === 'DT' ) {
							$this->generate_implied_end_tags(
								array(
									'except_for' => array( 'DT' ),
								)
							);
							$this->pop_until_tag_name( 'DT' );
							break;
						} elseif ( self::is_special_element( $node->token->tag, array( 'ADDRESS', 'DIV', 'P' ) ) ) {
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
						$node = $this->active_formatting_elements[ $i ];
						if ( $node->token->tag === 'A' ) {
							$active_a = $node;
							break;
						} elseif ( $node->token->is_marker() ) {
							break;
						}
					}

					if ( $active_a ) {
						$this->parse_error();
						$this->adoption_agency_algorithm( $token );
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
					$node = $this->insert_element( $token );
					$this->push_active_formatting_element( $node );
					break;
				case 'NOBR':
					$this->reconstruct_active_formatting_elements();
					if ( $this->is_element_in_scope( 'NOBR' ) ) {
						$this->parse_error();
						$this->adoption_agency_algorithm( $token );
						$this->reconstruct_active_formatting_elements();
					}
					$node = $this->insert_element( $token );
					$this->push_active_formatting_element( $node );
					break;
				case 'APPLET':
				case 'MARQUEE':
				case 'OBJECT':
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					$this->active_formatting_elements[] = WP_HTML_Token::marker();
					break;
				case 'TABLE':
					$this->insert_element( $token );
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
				case 'TEXTAREA':
					$this->insert_element( $token );
					break;
				case 'SELECT':
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
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

				// case 'XMP':
				// case 'IFRAME':
				// case 'NOEMBED':
				// case 'MATH':
				// case 'SVG':
				// case 'NOSCRIPT':
				// case 'PLAINTEXT':
				// case 'IMAGE':
				// 	throw new Exception( $token->tag . ' not implemented yet' );

				default:
					$this->reconstruct_active_formatting_elements();
					$this->insert_element( $token );
					break;
			}
		} else {
			dbg( "Found {$token->tag} tag closer" );
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
				case 'PRE':
				case 'SECTION':
				case 'SUMMARY':
				case 'UL':
					if ( ! $this->is_element_in_scope( $token->tag ) ) {
						$this->parse_error();
						return $this->ignore_token( $token );
					}
					$this->generate_implied_end_tags();
					$this->pop_until_tag_name( $token->tag );
					break;
				case 'FORM':
					$this->generate_implied_end_tags();
					$this->pop_until_tag_name( $token->tag );
					break;
				case 'P':
					/*
					 * If the stack of open elements does not have a p element in button scope, 
					 * then this is a parse error; insert an HTML element for a "p" start tag 
					 * token with no attributes.
					 */
					if ( ! $this->is_element_in_button_scope( 'P' ) ) {
						$this->parse_error();
						$this->insert_element( WP_HTML_Token::tag( 'P' ) );
					}
					// Close a p element.
					$this->close_p_element();
					break;
				case 'LI':
					if ( $this->is_element_in_list_item_scope( 'LI' ) ) {
						$this->parse_error();
						return $this->ignore_token( $token );
					}
					$this->generate_implied_end_tags();
					$this->pop_until_tag_name( 'LI' );
					break;
				case 'DD':
				case 'DT':
					if ( $this->is_element_in_scope( $token->tag ) ) {
						$this->parse_error();
						return $this->ignore_token( $token );
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
						$this->parse_error();
						return $this->ignore_token( $token );
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
					dbg( "Found {$token->tag} tag closer" );
					$this->adoption_agency_algorithm( $token );
					break;

				case 'APPLET':
				case 'MARQUEE':
				case 'OBJECT':
					if ( $this->is_element_in_scope( $token->tag ) ) {
						$this->parse_error();
						return $this->ignore_token( $token );
					}
					$this->generate_implied_end_tags();
					if ( $this->current_node()->token->tag !== $token->tag ) {
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
						if ( $node->token->tag === $token->tag ) {
							$this->generate_implied_end_tags(
								array(
									'except_for' => array( $token->tag ),
								)
							);
							$this->pop_until_node( $node );
							break;
						} elseif ( $this->is_special_element( $node->token->tag ) ) {
							$this->parse_error();
							return $this->ignore_token( $token );
						} else {
							--$i;
						}
					}
					break;
			}
		}
		return $token;
	}

	private $element_bookmark_idx = 0;
	private function next_token() {
		if($this->buffered_tag){
			$next_tag = $this->buffered_tag;
			$this->buffered_tag = null;
			return $next_tag;
		}

		$next_tag = false;
		if ( $this->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$bookmark = '__internal_' . ( $this->element_bookmark_idx++ );
			$this->set_bookmark($bookmark);
			$attributes = array();
			$attrs = $this->get_attribute_names_with_prefix('');
			if ($attrs) {
				foreach ($attrs as $name) {
					$attributes[$name] = $this->get_attribute($name);
				}
			}
			$next_tag = WP_HTML_Token::tag(
				$this->get_tag(),
				$attributes,
				! $this->is_tag_closer(),
				$bookmark
			);
			$text_end = $this->bookmarks[$bookmark]->start;
		} else {
			$text_end = strlen($this->html);
		}

		/*
		 * If any text was found between the last tag and this one, 
		 * save the next tag for later and return the text token.
		 */
		$last = $this->last_token;
		if ( 
			$last
			&& $last->is_tag()
			&& $last->bookmark
			&& $this->has_bookmark($last->bookmark)
		) {
			$text_start = $this->bookmarks[$last->bookmark]->end + 1;
			if ($text_start < $text_end) {
				$this->buffered_tag = $next_tag;
				$text = substr($this->html, $text_start, $text_end - $text_start);
				return WP_HTML_Token::text($text);
			}
		}

		return $next_tag;
	}

	const ANY_OTHER_END_TAG = 1;
	private function adoption_agency_algorithm( WP_HTML_Token $token ) {
		dbg("Adoption Agency Algorithm", 1);
		$subject = $token->tag;
		$current_node = $this->current_node();
		if (
			$current_node->token->tag === $subject
			&& ! in_array( $current_node, $this->active_formatting_elements, true )
		) {
			$this->pop_open_element();
			dbg("Skipping AAA: current node is \$subject ($subject) and is not AFE", 2);
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
				if ( $candidate->token->is_marker() ) {
					break;
				}
				if ( $candidate->token->tag === $subject ) {
					$formatting_element     = $candidate;
					$formatting_element_idx = $i;
					break;
				}
			}

			// If there is no such element, then abort these steps and instead act as
			// described in the "any other end tag" entry below.
			if ( null === $formatting_element ) {
				dbg("Skipping AAA: no formatting element found", 2);
				return self::ANY_OTHER_END_TAG;
			}
			dbg("AAA: Formatting element = {$formatting_element->token->tag}", 2);

			// If formatting element is not in the stack of open elements, then this is
			// a parse error; remove the element from the list, and return.
			if ( ! in_array( $formatting_element, $this->open_elements, true ) ) {
				array_splice( $this->active_formatting_elements, $formatting_element_idx, 1 );
				$this->parse_error();
				dbg("Skipping AAA: formatting element is not in the stack of open elements", 2);
				return;
			}

			// If formatting element is not in scope, then this is a parse error; return
			if ( ! $this->is_element_in_scope( $formatting_element ) ) {
				$this->parse_error();
				dbg("Skipping AAA: formatting element {$formatting_element->token->tag} is not in scope", 2);
				$this->print_open_elements('Open elements: ', 2);
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
				if ( $this->is_special_element( $node->token->tag ) ) {
					$furthest_block = $node;
				}
			}

			// If there is no such node, then the UA must first pop all the nodes from
			// the bottom of the stack of open elements, from the current node up to
			// and including formatting element, then remove formatting element from
			// the list of active formatting elements, and finally abort these steps.
			if ( null === $furthest_block ) {
				$this->pop_until_node( $formatting_element );
				array_splice( $this->active_formatting_elements, $formatting_element_idx, 1 );
				dbg("Skipping AAA: no furthest block found", 2);
				return;
			}

			dbg("AAA: Furthest block = {$furthest_block->token->tag}", 2);

			// Let common ancestor be the element immediately above formatting element
			// in the stack of open elements.
			$formatting_elem_stack_index = array_search( $formatting_element, $this->open_elements, true );
			$common_ancestor             = $this->open_elements[ $formatting_elem_stack_index - 1 ];

			dbg("AAA: Common ancestor = {$common_ancestor->token->tag}", 2);

			$this->print_open_elements('AAA: Open elements: ', 2);
			$this->print_active_formatting_elements('AAA: Formatting elements: ', 2);

			// Let a bookmark note the position of formatting element in the list of
			// active formatting elements relative to the elements on either side of it
			// in the list.
			$bookmark = $formatting_element_idx;

			// Let node and last node be furthest block.
			$node                     = $last_node = $furthest_block;
			$node_open_elements_index = array_search( $node, $this->open_elements, true );

			$prev_open_element_index = false;
			$inner_loop_counter      = 0;
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
					if ( false === $prev_open_element_index ) {
						throw new Exception( 'Unexpected error in AAA algorithm – cannot find node.' );
					}
					$node_open_elements_index = $prev_open_element_index;
				}
				--$node_open_elements_index;
				if( $node_open_elements_index < 0 ) {
					throw new Exception( 'Unexpected error in AAA algorithm – node is not in the stack of open elements.' );
				}
				$node                     = $this->open_elements[ $node_open_elements_index ];
				$prev_open_element_index = $node_open_elements_index;

				// If node is formatting element, then break.
				if ( $node === $formatting_element ) {
					dbg("AAA: Inner loop break – node is formatting element", 3);
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
					dbg("AAA: Inner loop – removing node from the stack of open elements", 3);
					array_splice( $this->open_elements, $node_open_elements_index, 1 );
				}

				/*
				 * Create an element for the token for which the element node was created,
				 * in the HTML namespace, with common ancestor as the intended parent.
				 */
				$new_node            = $this->create_element_for_token( $node->token );

				/*
				 * Replace the entry for node in the list of active formatting elements with an entry
				 * for the new element.
				 */
				$node_formatting_idx = array_search( $node, $this->active_formatting_elements, true );
				$this->active_formatting_elements[ $node_formatting_idx ] = $new_node;

				/*
				 * Replace the entry for node in the stack of open elements with an entry for
				 * the new element.
				 */
				$idx                         = array_search( $node, $this->open_elements, true );
				$this->open_elements[ $idx ] = $new_node;

				/*
				 * Let node be the new element.
				 */
				$node = $new_node;

				/*
				 * If last node is furthest block, then move the aforementioned bookmark to be
				 * immediately after the new node in the list of active formatting elements.
				 */
				if ( $last_node === $furthest_block ) {
					$bookmark = $node_formatting_idx + 1;
				}

				// Append last node to node.
				dbg("AAA: Appending {$last_node->token->tag} to {$node->token->tag}", 3);
				$node->append_child( $last_node );

				// Set last node to node.
				$last_node = $node;
			}

			// Insert whatever last node ended up being in the previous step at the appropriate place
			// for inserting a node, but using common ancestor as the override target.
			$this->insert_node( $last_node, $common_ancestor );

			// Create an element for the token for which formatting element was created, in the HTML
			// namespace, with furthest block as the intended parent.
			$new_element = $this->create_element_for_token( $formatting_element->token );

			// Take all of the child nodes of furthest block and append them to the element created in
			// the last step.
			foreach ($furthest_block->children as $child) {
				$new_element->append_child( $child );
			}

			// Append that new element to furthest block.
			$furthest_block->append_child( $new_element );

			// Remove formatting element from the list of active formatting elements
			$idx = array_search( $formatting_element, $this->active_formatting_elements, true );
			array_splice( $this->active_formatting_elements, $idx, 1 );
	
			// Insert the new element into the list of active formatting elements at the 
			// position of the aforementioned bookmark.
			array_splice( $this->active_formatting_elements, $bookmark, 0, array( $new_element ) );

			// Remove formatting element from the stack of open elements
			$idx = array_search( $formatting_element, $this->open_elements, true );
			array_splice( $this->open_elements, $idx, 1 );
			
			// Insert the new element into the stack of open elements immediately below the 
			// position of furthest block in that stack.
			$idx = array_search( $furthest_block, $this->open_elements, true );
			array_splice( $this->open_elements, $idx + 1, 0, array( $new_element ) );
		}
	}

	private function insert_element( WP_HTML_Token $token, $override_target = null ) {
		// Create element for a token
		// Skip reset algorithm for now
		// Skip form-association for now
		$node = $this->create_element_for_token($token);
		$this->insert_node($node, $override_target);
		array_push($this->open_elements, $node);
		return $node;
	}

	private function insert_node( WP_HTML_Node $node, $override_target = null ) {
		$target = $override_target ?: $this->current_node();

		// Appropriate place for inserting a node:
		// For now skip foster parenting and always use the
		// location after the last child of the target
		$target->append_child($node);
		dbg("Inserted element: {$node->token->tag} to parent {$target->token->tag}", 2);
	}

	private function create_element_for_token( WP_HTML_Token $token ) {
		return new WP_HTML_Node($token);
	}

	private function insert_text( WP_HTML_Token $token ) {
		$target = $this->current_node();
		if(count($target->children)){
			$last_child = end($target->children);
			if ( $last_child && $last_child->token->is_text() ) {
				$last_child->token->value .= $token->value;
				return;
			}
		}
		$target->append_child(new WP_HTML_Node($token));
	}

	private function parse_error() {
		// Noop for now
	}

	private function pop_until_tag_name( $tags ) {
		if ( ! is_array( $tags ) ) {
			$tags = array( $tags );
		}
		dbg( "Popping until tag names: " . implode(', ', $tags), 1 );
		$this->print_open_elements( "Open elements before: " );
		do {
			$popped = $this->pop_open_element();
		} while (!in_array($popped->token->tag, $tags));
		$this->print_open_elements( "Open elements after: " );
	}

	private function pop_until_node( $node ) {
		do {
			$popped = $this->pop_open_element();
		} while ( $popped !== $node );
	}

	private function pop_open_element() {
		$popped = array_pop( $this->open_elements );
		if ( $popped->token->bookmark ) {
			$this->release_bookmark( $popped->token->bookmark );
			$popped->token->bookmark = null;
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
		dbg( "close_p_element" );
		$this->generate_implied_end_tags(
			array(
				'except_for' => array( 'P' ),
			)
		);
		// If the current node is not a p element, then this is a parse error.
		if ( $this->current_node()->token->tag !== 'P' ) {
			$this->parse_error();
		}
		$this->pop_until_tag_name( 'P' );
	}

	private function should_generate_implied_end_tags( $options = null ) {
		$current_tag_name = $this->current_node()->token->tag;
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
	private function push_active_formatting_element( WP_HTML_Node $node ) {
		$count = 0;
		for ( $i = count( $this->active_formatting_elements ) - 1; $i >= 0; $i-- ) {
			$formatting_element = $this->active_formatting_elements[ $i ];
			if ( $formatting_element->token->is_marker() ) {
				break;
			}
			if ( ! $formatting_element->token->equivalent( $node->token ) ) {
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

	private function print_active_formatting_elements($msg, $indent=1) {
		if (HTML_DEBUG_MODE) {
			$formats = array_map(function ($node) {
				return $node->token->tag ?: ($node->token->is_marker() ? 'M' : 'ERROR');
			}, $this->active_formatting_elements);
			dbg("$msg " . implode(', ', $formats), $indent);
		}
	}

	private function print_open_elements($msg, $indent=1) {
		if (HTML_DEBUG_MODE) {
			$elems = array_map(function ($node) {
				return $node->token->tag;
			}, $this->open_elements);
			dbg("$msg " . implode(', ', $elems), $indent);
		}
	}

	private function reconstruct_active_formatting_elements() {
		$this->print_active_formatting_elements('AFE: before');
		if ( empty( $this->active_formatting_elements ) ) {
			dbg( "Skipping AFE: empty list", 1 );
			return;
		}
		$entry_idx          = count( $this->active_formatting_elements ) - 1;
		$last_entry = $this->active_formatting_elements[ $entry_idx ];
		if ( $last_entry->token->is_marker() || in_array( $last_entry, $this->open_elements, true ) ) {
			dbg( "Skipping AFE: marker or open element", 1 );
			return;
		}

		// Let entry be the last (most recently added) element in the list of active formatting elements.
		$entry = $last_entry;

		$is_rewinding = true;
		while ( true ) {
			if ( $is_rewinding ) {
				// Rewind:
				/*
				 * If there are no entries before entry in the list of active formatting elements,
				 * then jump to the step labeled create.
				 */
				if ( $entry_idx === 0 ) {
					$is_rewinding = false;
				} else {
					// Let entry be the entry one earlier than entry in the list of active formatting elements.
					$entry = $this->active_formatting_elements[ --$entry_idx ];

					// If entry is neither a marker nor an element that is also in the stack of open elements,
					// go to the step labeled rewind.
					if ( ! $entry->token->is_marker() && ! in_array( $entry, $this->open_elements, true ) ) {
						continue;
					}
				}
			} else {
				// Advance:
				// Let entry be the element one later than entry in the list of active formatting elements.
				$entry = $this->active_formatting_elements[ ++$entry_idx ];
			}

			// Create: Insert an HTML element for the token for which the element entry was created,
			// to obtain new element.
			$new_element = $this->insert_element( $entry->token );

			// Replace the entry for entry in the list with an entry for new element.
			$this->active_formatting_elements[ $entry_idx ] = $new_element;

			// If the entry for new element in the list of active formatting elements is not the last entry 
			// in the list, return to the step labeled advance.
			if ( $entry_idx === count( $this->active_formatting_elements ) - 1 ) {
				break;
			}
		}
		$this->print_active_formatting_elements('AFE: after');
	}

	private function clear_active_formatting_elements_up_to_last_marker() {
		while ( ! empty( $this->active_formatting_elements ) ) {
			$entry = array_pop( $this->active_formatting_elements );
			if ( $entry->token->is_marker() ) {
				break;
			}
		}
	}

	/**
	 * The stack of open elements is said to have a particular element in 
	 * select scope when it has that element in the specific scope consisting
	 * of all element types except the following:
	 * * optgroup
	 * * option
	 */
	private function is_element_in_select_scope( $target_node ) {
		return $this->is_element_in_specific_scope(
			$target_node,
			array(
				'OPTGROUP',
				'OPTION',
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
				'HTML',
				'TABLE',
				'TEMPLATE',
			)
		);
	}

	private function is_element_in_button_scope( $target_node ) {
		return $this->is_element_in_scope(
			$target_node,
			array(
				'BUTTON',
			)
		);
	}

	private function is_element_in_list_item_scope( $target_node ) {
		return $this->is_element_in_scope(
			$target_node,
			array(
				'LI',
				'DD',
				'DT',
			)
		);
	}

	private function is_element_in_scope( $target_node, $additional_elements = array() ) {
		return $this->is_element_in_specific_scope(
			$target_node,
			array_merge(
				array(
					'APPLET',
					'CAPTION',
					'HTML',
					'TABLE',
					'TD',
					'TH',
					'MARQUEE',
					'OBJECT',
					'TEMPLATE',
				),
				$additional_elements
			)
		);
	}

	/*
	 * https://html.spec.whatwg.org/multipage/parsing.html#the-stack-of-open-elements
	 */
	private function is_element_in_specific_scope( $target_node, $element_types_list, $options = array() ) {
		$negative_match = isset( $options['negative_match'] ) ? $options['negative_match'] : false;

		/**
		 * The stack of open elements is said to have an element target node in a
		 * specific scope consisting of a list of element types list when the following
		 * algorithm terminates in a match state:
		 */
		$i = count( $this->open_elements ) - 1;
		// 1. Initialize node to be the current node (the bottommost node of the stack).
		$node = $this->open_elements[ $i ];

		while ( true ) {
			// 2. If node is the target node, terminate in a match state.
			if ( is_string( $target_node ) ) {
				if ( $node->token->tag === $target_node ) {
					return true;
				}
			} else if ( $node === $target_node ) {
				return true;
			}

			// 3. Otherwise, if node is one of the element types in list, terminate in a failure state.
			$failure = in_array( $node->token->tag, $element_types_list, true );

			// Some elements say:
			// > If has that element in the specific scope consisting of all element types 
			// > except the following
			// So we need to invert the result.
			if($negative_match) {
				$failure = ! $failure;
			}
			if ( $failure ) {
				return false;
			}

			// Otherwise, set node to the previous entry in the stack of open elements and
			// return to step 2. (This will never fail, since the loop will always terminate 
			// in the previous step if the top of the stack — an html element — is reached.)
			$node = $this->open_elements[ --$i ];
		}
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

// $dir = realpath( __DIR__ . '/../../../index.html' );

// $htmlspec = file_get_contents( $dir );
// $p = new WP_HTML_Processor( $htmlspec );
// $p->parse();

// die();

$p = new WP_HTML_Processor( '<ul><li>1<li>2<li>3<li>Lorem<b>Ipsum<li>Dolor</ul>Sit<div>Amet' );
$p->parse();
/*
Outputs:

DOM after main loop:
  HTML
   ├─ UL
      ├─ LI
         └─ #text: 1
      ├─ LI
         └─ #text: 2
      ├─ LI
         └─ #text: 3
      ├─ LI
         ├─ #text: Lorem
         └─ B
            └─ #text: Ipsum
      └─ LI
         └─ B
            └─ #text: Dolor
   └─ B
      ├─ #text: Sit
      └─ DIV
         └─ #text: Amet
*/

die();

$p = new WP_HTML_Processor( '<div>1<span>2</div>3</span>4' );
$p->parse();
/*
Outputs:
	p
	├─ #text: 1
	├─ b
	│  ├─ #text: 2
	│  └─ i
	│     └─ #text: 3
	├─ i
	│  └─ #text: 4
	└─ #text: 5
*/

die();

$p = new WP_HTML_Processor( '<p>1<b>2<i>3</b>4</i>5</p>' );
$p->parse();
/*
Outputs:
	p
	├─ #text: 1
	├─ b
	│  ├─ #text: 2
	│  └─ i
	│     └─ #text: 3
	├─ i
	│  └─ #text: 4
	└─ #text: 5
*/

$p = new WP_HTML_Processor( '<b>1<p>2</b>3</p>' );
$p->parse();
/*
Outputs the correct result:
  HTML
   ├─ B
      └─ #text: 1
   └─ P
      ├─ B
         └─ #text: 2
      └─ #text: 3
*/


$p = new WP_HTML_Processor( '<p><b class=x><b class=x><b><b class=x><b class=x><b>X
<p>X
<p><b><b class=x><b>X
<p></b></b></b></b></b></b>X' );
$p->parse();
/*
DOM after main loop:
  HTML
   ├─ P
      └─ B class="x"
         └─ B class="x"
            └─ B
               └─ B class="x"
                  └─ B class="x"
                     └─ B
                        └─ #text: X
   ├─ P
      └─ B class="x"
         └─ B
            └─ B class="x"
               └─ B class="x"
                  └─ B
                     └─ #text: X
   ├─ P
      └─ B class="x"
         └─ B
            └─ B class="x"
               └─ B class="x"
                  └─ B
                     └─ B
                        └─ B class="x"
                           └─ B
                              └─ #text: X
   └─ P
      └─ #text: X
*/
