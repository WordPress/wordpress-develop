<?php
/**
 * XML API: WP_XML_Processor class
 * 

 * @package WordPress
 * @subpackage XML-API
 * @since WP_VERSION
 */

/**
 * @since WP_VERSION
 */
class WP_XML_Processor extends WP_XML_Tag_Processor {

	/**
	 * Indicates the current parsing stage.
	 * 
	 * A well-formed XML document has the following structure:
	 * 
	 * 	   document ::= prolog element Misc*
	 *     prolog   ::= XMLDecl? Misc* (doctypedecl Misc*)?
	 *     Misc     ::= Comment | PI | S
	 * 
	 * There is exactly one element, called the root. No elements or text nodes may 
	 * precede or follow it.
	 * 
	 * See https://www.w3.org/TR/xml/#NT-document.
	 * 
	 * | Stage           | Meaning                                                             |
	 * | ----------------|---------------------------------------------------------------------|
	 * | *Prolog*        | The parser is parsing the prolog.                                   |
	 * | *Element*       | The parser is parsing the root element.                             |
	 * | *Misc*          | The parser is parsing miscellaneous content.                        |
	 * 
	 * @see WP_XML_Tag_Processor::IN_PROLOG_CONTEXT
	 * @see WP_XML_Tag_Processor::IN_ELEMENT_CONTEXT
	 * @see WP_XML_Tag_Processor::IN_MISC_CONTEXT
	 *
	 * @since WP_VERSION
	 * @var bool 
	 */
	protected $parser_context = self::IN_PROLOG_CONTEXT;

	/**
	 * Tracks open elements while scanning XML.
	 *
	 * @since WP_VERSION
	 *
	 * @var string[]
	 */
	public $stack_of_open_elements = array();

	/**
	 * Constructor.
	 *
	 * @since WP_VERSION
	 *
	 * @param string $xml XML to process.
	 */
	public function __construct( $xml ) {
		parent::__construct( $xml );
	}

	/**
	 * Finds the next element matching the $query.
	 * 
	 * This doesn't currently have a way to represent non-tags and doesn't process
	 * semantic rules for text nodes. For access to the raw tokens consider using
	 * WP_XML_Tag_Processor instead.
	 * 
	 * @since WP_VERSION
	 *
	 * @param array|string|null $query {
	 *     Optional. Which tag name to find, having which class, etc. Default is to find any tag.
	 *
	 *     @type string|null $tag_name     Which tag to find, or `null` for "any tag."
	 *     @type int|null    $match_offset Find the Nth tag matching all search criteria.
	 *                                     1 for "first" tag, 3 for "third," etc.
	 *                                     Defaults to first tag.
	 *     @type string[]    $breadcrumbs  DOM sub-path at which element is found, e.g. `array( 'FIGURE', 'IMG' )`.
	 *                                     May also contain the wildcard `*` which matches a single element, e.g. `array( 'SECTION', '*' )`.
	 * }
	 * @return bool Whether a tag was matched.
	 */
	public function next_tag( $query = null ) {
		if ( null === $query ) {
			while ( $this->step() ) {
				if ( '#tag' !== $this->get_token_type() ) {
					continue;
				}

				if ( ! $this->is_tag_closer() ) {
					return true;
				}
			}

			return false;
		}

		if ( is_string( $query ) ) {
			$query = array( 'breadcrumbs' => array( $query ) );
		}

		if ( ! is_array( $query ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Please pass a query array to this function.' ),
				'6.4.0'
			);
			return false;
		}

		if ( ! ( array_key_exists( 'breadcrumbs', $query ) && is_array( $query['breadcrumbs'] ) ) ) {
			while ( $this->step() ) {
				if ( '#tag' !== $this->get_token_type() ) {
					continue;
				}

				if ( ! $this->is_tag_closer() ) {
					return true;
				}
			}

			return false;
		}

		if ( isset( $query['tag_closers'] ) && 'visit' === $query['tag_closers'] ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Cannot visit tag closers in XML Processor.' ),
				'6.4.0'
			);
			return false;
		}

		$breadcrumbs  = $query['breadcrumbs'];
		$match_offset = isset( $query['match_offset'] ) ? (int) $query['match_offset'] : 1;

		while ( $match_offset > 0 && $this->step() ) {
			if ( '#tag' !== $this->get_token_type() ) {
				continue;
			}

			if ( $this->matches_breadcrumbs( $breadcrumbs ) && 0 === --$match_offset ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Low-level token iteration is not available in WP_XML_Processor
	 * as it could lead to undefined behaviors.
	 * 
	 * @return false
	 */
	public function next_token()
	{
		_doing_it_wrong(
			__METHOD__,
			__( 'next_token() is not available in WP_XML_Processor. Consider using WP_XML_Tag_Processor instead.' ),
			'WP_VERSION'
		);
		return false;
	}

	/**
	 * A private method to step through the XML document without exposing
	 * the low-level API to the public.
	 * 
	 * @see WP_XML_Tag_Processor::next_token
	 * @return bool
	 */
	private function private_next_token()
	{
		return parent::next_token();
	}

	/**
	 * Steps through the XML document and stop at the next tag, if any.
	 * 
	 * @since WP_VERSION
	 *
	 * @param string $node_to_process Whether to parse the next node or reprocess the current node.
	 * @return bool Whether a tag was matched.
	 */
	private function step( $node_to_process = self::PROCESS_NEXT_NODE )
	{
		// Refuse to proceed if there was a previous error.
		if ( null !== $this->last_error ) {
			return false;
		}

		// Finish stepping when there are no more tokens in the document.
		if (
			WP_XML_Tag_Processor::STATE_INCOMPLETE_INPUT === $this->parser_state ||
			WP_XML_Tag_Processor::STATE_COMPLETE === $this->parser_state
		) {
			return false;
		}

		if ( self::PROCESS_NEXT_NODE === $node_to_process ) {
			if ($this->is_empty_element()) {
				$this->pop_open_element();
			}
			$this->private_next_token();
		}

		switch($this->parser_context) {
			case self::IN_PROLOG_CONTEXT:
				return $this->step_in_prolog();
			case self::IN_ELEMENT_CONTEXT:
				return $this->step_in_element();
			case self::IN_MISC_CONTEXT:
				return $this->step_in_misc();
			default:
				$this->last_error = self::ERROR_UNSUPPORTED;
				return false;
		}
	}

	/**
	 * Parses the next node in the 'prolog' part of the XML document.
	 *
	 * @since WP_VERSION
	 *
	 * @see https://www.w3.org/TR/xml/#NT-document.
	 * @see WP_XML_Tag_Processor::step
	 *
	 * @return bool Whether a node was found.
	 */
	private function step_in_prolog()
	{
		// XML requires a root element. If we've reached the end of data in the prolog stage,
		// before finding a root element, then the document is incomplete.
		if(WP_XML_Tag_Processor::STATE_COMPLETE === $this->parser_state) {
			$this->parser_state = self::STATE_INCOMPLETE_INPUT;
			return false;
		}
		switch($this->get_token_type()) {
			case '#text':
				$text = $this->get_modifiable_text();
				$whitespaces = strspn($text, " \t\n\r");
				if($whitespaces !== strlen($text)) {
					$this->last_error = self::ERROR_SYNTAX;
					_doing_it_wrong( __METHOD__, 'Unexpected token type in prolog stage.', 'WP_VERSION' );
				}

				return $this->step();
			case '#xml-declaration':
			case '#comment':
			case '#processing-instructions':
				return true;
			case '#tag':
				$this->parser_context = self::IN_ELEMENT_CONTEXT;
				return $this->step( self::PROCESS_CURRENT_NODE );
			default:
				$this->last_error = self::ERROR_SYNTAX;
				_doing_it_wrong( __METHOD__, 'Unexpected token type in prolog stage.', 'WP_VERSION' );
				return false;
		}
	}

	/**
	 * Parses the next node in the 'element' part of the XML document.
	 *
	 * @since WP_VERSION
	 *
	 * @see https://www.w3.org/TR/xml/#NT-document.
	 * @see WP_XML_Tag_Processor::step
	 *
	 * @return bool Whether a node was found.
	 */
	private function step_in_element() {
		// An XML document isn't complete until the root element is closed.
		if ( 
			self::STATE_COMPLETE === $this->parser_state &&
			count($this->stack_of_open_elements) > 0
		) {
			$this->parser_state = self::STATE_INCOMPLETE_INPUT;
			return false;
		}

		switch($this->get_token_type()) {
			case '#text':
			case '#cdata-section':
			case '#comment':
			case '#processing-instructions':
				return true;
			case '#tag':
				// Update the stack of open elements
				$tag_name = $this->get_tag();
				if ( $this->is_tag_closer() ) {
					$popped = $this->pop_open_element();
					if($popped !== $tag_name) {
						$this->last_error = self::ERROR_SYNTAX;
						_doing_it_wrong(
							__METHOD__,
							__( 'The closing tag did not match the opening tag.' ),
							'WP_VERSION'
						);
						return false;
					}
					if( count($this->stack_of_open_elements) === 0 ) {
						$this->parser_context = self::IN_MISC_CONTEXT;
					}
				} else {
					$this->push_open_element($tag_name);
				}
				return true;
			default:
				$this->last_error = self::ERROR_SYNTAX;
				_doing_it_wrong( __METHOD__, 'Unexpected token type in element stage.', 'WP_VERSION' );
				return false;
		}
	}

	/**
	 * Parses the next node in the 'misc' part of the XML document.
	 *
	 * @since WP_VERSION
	 *
	 * @see https://www.w3.org/TR/xml/#NT-document.
	 * @see WP_XML_Tag_Processor::step
	 *
	 * @return bool Whether a node was found.
	 */
	private function step_in_misc() {
		if(self::STATE_COMPLETE === $this->parser_state) {
			return true;
		}

		switch($this->get_token_type()) {
			case '#comment':
			case '#processing-instructions':
				return true;
			case '#text':
				$text = $this->get_modifiable_text();
				$whitespaces = strspn($text, " \t\n\r");
				if($whitespaces !== strlen($text)) {
					$this->last_error = self::ERROR_SYNTAX;
					_doing_it_wrong( __METHOD__, 'Unexpected token type in prolog stage.', 'WP_VERSION' );
					return false;
				}
				return $this->step();
			default:
				$this->last_error = self::ERROR_SYNTAX;
				_doing_it_wrong( __METHOD__, 'Unexpected token type in misc stage.', 'WP_VERSION' );
				return false;
		}
	}

	/**
	 * Computes the XML breadcrumbs for the currently-matched element, if matched.
	 *
	 * Breadcrumbs start at the outermost parent and descend toward the matched element.
	 * They always include the entire path from the root XML node to the matched element.

	 * Example
	 *
	 *     $processor = WP_XML_Processor::create_fragment( '<p><strong><em><img/></em></strong></p>' );
	 *     $processor->next_tag( 'img' );
	 *     $processor->get_breadcrumbs() === array( 'p', 'strong', 'em', 'img' );
	 *
	 * @since WP_VERSION
	 *
	 * @return string[]|null Array of tag names representing path to matched node, if matched, otherwise NULL.
	 */
	public function get_breadcrumbs()
	{
		return $this->stack_of_open_elements;
	}

	/**
	 * Indicates if the currently-matched tag matches the given breadcrumbs.
	 *
	 * A "*" represents a single tag wildcard, where any tag matches, but not no tags.
	 *
	 * At some point this function _may_ support a `**` syntax for matching any number
	 * of unspecified tags in the breadcrumb stack. This has been intentionally left
	 * out, however, to keep this function simple and to avoid introducing backtracking,
	 * which could open up surprising performance breakdowns.
	 *
	 * Example:
	 *
	 *     $processor = new WP_XML_Tag_Processor( '<root><wp:post><content><image /></content></wp:post></root>' );
	 *     $processor->next_tag( 'img' );
	 *     true  === $processor->matches_breadcrumbs( array( 'content', 'image' ) );
	 *     true  === $processor->matches_breadcrumbs( array( 'wp:post', 'content', 'image' ) );
	 *     false === $processor->matches_breadcrumbs( array( 'wp:post', 'image' ) );
	 *     true  === $processor->matches_breadcrumbs( array( 'wp:post', '*', 'image' ) );
	 *
	 * @since WP_VERSION
	 *
	 * @param string[] $breadcrumbs DOM sub-path at which element is found, e.g. `array( 'content', 'image' )`.
	 *                              May also contain the wildcard `*` which matches a single element, e.g. `array( 'wp:post', '*' )`.
	 * @return bool Whether the currently-matched tag is found at the given nested structure.
	 */
	public function matches_breadcrumbs( $breadcrumbs ) {
		// Everything matches when there are zero constraints.
		if ( 0 === count( $breadcrumbs ) ) {
			return true;
		}

		// Start at the last crumb.
		$crumb = end( $breadcrumbs );

		if ( '*' !== $crumb && $this->get_tag() !== $crumb ) {
			return false;
		}

		for ( $i = count( $this->stack_of_open_elements ) - 1; $i >= 0; $i-- ) {
			$tag_name = $this->stack_of_open_elements[ $i ];
			$crumb = current( $breadcrumbs );

			if ( '*' !== $crumb && $tag_name !== $crumb ) {
				return false;
			}

			if ( false === prev( $breadcrumbs ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the nesting depth of the current location in the document.
	 *
	 * Example:
	 *
	 *     $processor = new WP_XML_Processor( '<?xml version="1.0" ?><root><wp:text></wp:text></root>' );
	 *     0 === $processor->get_current_depth();
	 *
	 *     // Opening the root element increases the depth.
	 *     $processor->next_tag();
	 *     1 === $processor->get_current_depth();
	 *
	 *     // Opening the wp:text element increases the depth.
	 *     $processor->next_tag();
	 *     2 === $processor->get_current_depth();
	 *
	 *     // The wp:text element is closed during `next_token()` so the depth is decreased to reflect that.
	 *     $processor->next_token();
	 *     1 === $processor->get_current_depth();
	 *
	 * @since WP_VERSION
	 *
	 * @return int Nesting-depth of current location in the document.
	 */
	public function get_current_depth() {
		return count($this->stack_of_open_elements);
	}

	private function pop_open_element() {
		return array_pop($this->stack_of_open_elements);
	}

	private function push_open_element($tag_name)
	{
		array_push(
			$this->stack_of_open_elements,
			$tag_name
		);
	}

	private function last_open_element()
	{
		return end($this->stack_of_open_elements);
	}

	/**
	 * Indicates that we're parsing the `prolog` part of the XML
	 * document.
	 * 
	 * @since WP_VERSION
	 * 
	 * @access private
	 */
	const IN_PROLOG_CONTEXT = 'prolog';

	/**
	 * Indicates that we're parsing the `element` part of the XML
	 * document.
	 * 
	 * @since WP_VERSION
	 * 
	 * @access private
	 */
	const IN_ELEMENT_CONTEXT = 'element';

	/**
	 * Indicates that we're parsing the `misc` part of the XML
	 * document.
	 * 
	 * @since WP_VERSION
	 * 
	 * @access private
	 */
	const IN_MISC_CONTEXT = 'misc';

	/**
	 * Indicates that the next HTML token should be parsed and processed.
	 *
	 * @since WP_VERSION
	 *
	 * @var string
	 */
	const PROCESS_NEXT_NODE = 'process-next-node';

	/**
	 * Indicates that the current HTML token should be processed without advancing the parser.
	 *
	 * @since WP_VERSION
	 *
	 * @var string
	 */
	const PROCESS_CURRENT_NODE = 'process-current-node';

}
