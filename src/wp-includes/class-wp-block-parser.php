<?php
/**
 * Block Serialization Parser
 *
 * @package WordPress
 */

/**
 * Class WP_Block_Parser
 *
 * Parses a document and constructs a list of parsed block objects
 *
 * @since 5.0.0
 * @since 4.0.0 returns arrays not objects, all attributes are arrays
 */
class WP_Block_Parser {
	/**
	 * Input document being parsed
	 *
	 * @example "Pre-text\n<!-- wp:paragraph -->This is inside a block!<!-- /wp:paragraph -->"
	 *
	 * @since 5.0.0
	 * @var string
	 */
	public $document;

	/**
	 * Tracks parsing progress through document
	 *
	 * @since 5.0.0
	 * @var int
	 */
	public $offset;

	/**
	 * List of parsed blocks
	 *
	 * @since 5.0.0
	 * @var WP_Block_Parser_Block[]
	 */
	public $output;

	/**
	 * Stack of partially-parsed structures in memory during parse
	 *
	 * @since 5.0.0
	 * @var WP_Block_Parser_Frame[]
	 */
	public $stack;

	/**
	 * Parses a document and returns a list of block structures
	 *
	 * When encountering an invalid parse will return a best-effort
	 * parse. In contrast to the specification parser this does not
	 * return an error on invalid inputs.
	 *
	 * @since 5.0.0
	 *
	 * @param string $document Input document being parsed.
	 * @return array[]
	 */
	public function parse( $document ) {
		$this->document = $document;
		$this->offset   = 0;
		$this->output   = array();
		$this->stack    = array();

		while ( $this->proceed() ) {
			continue;
		}

		return $this->output;
	}

	/**
	 * Processes the next token from the input document
	 * and returns whether to proceed eating more tokens
	 *
	 * This is the "next step" function that essentially
	 * takes a token as its input and decides what to do
	 * with that token before descending deeper into a
	 * nested block tree or continuing along the document
	 * or breaking out of a level of nesting.
	 *
	 * @internal
	 * @since 5.0.0
	 * @return bool
	 */
	public function proceed() {
		$delimiter   = WP_Parsed_Block_Delimiter_Info::next_delimiter( $this->document, $this->offset, $delimiter_at, $delimiter_length );
		$stack_depth = count( $this->stack );

		if ( ! isset( $delimiter ) ) {
			// if not in a block then flush output.
			if ( 0 === $stack_depth ) {
				$this->add_freeform();
				return false;
			}

			/*
			 * Otherwise we have a problem
			 * This is an error
			 *
			 * we have options
			 * - treat it all as freeform text
			 * - assume an implicit closer (easiest when not nesting)
			 */

			// for the easy case we'll assume an implicit closer.
			if ( 1 === $stack_depth ) {
				$this->add_block_from_stack();
				return false;
			}

			/*
			 * For the nested case where it's more difficult we'll
			 * have to assume that multiple closers are missing
			 * and so we'll collapse the whole stack piecewise
			 *
			 * The count of the stack changes during each iteration of the loop.
			 */
			while ( 0 < count( $this->stack ) ) { // phpcs:ignore
				$this->add_block_from_stack();
			}
			return false;
		}

		// we may have some HTML soup before the next block.
		$leading_html_start = $delimiter_at > $this->offset ? $this->offset : null;

		switch ( $delimiter->get_delimiter_type() ) {
			case WP_Parsed_Block_Delimiter_Info::VOID:
				$block_name = $delimiter->allocate_and_return_block_type();
				$attrs      = $delimiter->allocate_and_return_parsed_attributes() ?? array();

				/*
				 * easy case is if we stumbled upon a void block
				 * in the top-level of the document
				 */
				if ( 0 === $stack_depth ) {
					if ( isset( $leading_html_start ) ) {
						$this->output[] = (array) $this->freeform(
							substr(
								$this->document,
								$leading_html_start,
								$delimiter_at - $leading_html_start
							)
						);
					}

					$this->output[] = (array) new WP_Block_Parser_Block( $block_name, $attrs, array(), '', array() );
					$this->offset   = $delimiter_at + $delimiter_length;
					return true;
				}

				// otherwise we found an inner block.
				$this->add_inner_block(
					new WP_Block_Parser_Block( $block_name, $attrs, array(), '', array() ),
					$delimiter_at,
					$delimiter_length
				);
				$this->offset = $delimiter_at + $delimiter_length;
				return true;

			case WP_Parsed_Block_Delimiter_Info::OPENER:
				$block_name = $delimiter->allocate_and_return_block_type();
				$attrs      = $delimiter->allocate_and_return_parsed_attributes() ?? array();

				// track all newly-opened blocks on the stack.
				array_push(
					$this->stack,
					new WP_Block_Parser_Frame(
						new WP_Block_Parser_Block( $block_name, $attrs, array(), '', array() ),
						$delimiter_at,
						$delimiter_length,
						$delimiter_at + $delimiter_length,
						$leading_html_start
					)
				);
				$this->offset = $delimiter_at + $delimiter_length;
				return true;

			case WP_Parsed_Block_Delimiter_Info::CLOSER:
				/*
				 * if we're missing an opener we're in trouble
				 * This is an error
				 */
				if ( 0 === $stack_depth ) {
					/*
					 * we have options
					 * - assume an implicit opener
					 * - assume _this_ is the opener
					 * - give up and close out the document
					 */
					$this->add_freeform();
					return false;
				}

				// if we're not nesting then this is easy - close the block.
				if ( 1 === $stack_depth ) {
					$this->add_block_from_stack( $delimiter_at );
					$this->offset = $delimiter_at + $delimiter_length;
					return true;
				}

				/*
				 * otherwise we're nested and we have to close out the current
				 * block and add it as a new innerBlock to the parent
				 */
				$stack_top                        = array_pop( $this->stack );
				$html                             = substr( $this->document, $stack_top->prev_offset, $delimiter_at - $stack_top->prev_offset );
				$stack_top->block->innerHTML     .= $html;
				$stack_top->block->innerContent[] = $html;
				$stack_top->prev_offset           = $delimiter_at + $delimiter_length;

				$this->add_inner_block(
					$stack_top->block,
					$stack_top->token_start,
					$stack_top->token_length,
					$delimiter_at + $delimiter_length
				);
				$this->offset = $delimiter_at + $delimiter_length;
				return true;

			default:
				// This is an error.
				$this->add_freeform();
				return false;
		}
	}

	/**
	 * Scans the document from where we last left off
	 * and finds the next valid token to parse if it exists
	 *
	 * Returns the type of the find: kind of find, block information, attributes
	 *
	 * @deprecated {WP_VERSION} Replaced by WP_Parsed_Block_Delimiter_Info.
	 *
	 * @internal
	 * @since 5.0.0
	 * @since 4.6.1 fixed a bug in attribute parsing which caused catastrophic backtracking on invalid block comments
	 * @since {WP_VERSION} Relies on the WP_Parsed_Block_Delimiter_Info class for parsing.
	 *
	 * @return array
	 */
	public function next_token() {
		$delimiter = WP_Parsed_Block_Delimiter_Info::next_delimiter( $this->document, $this->offset, $delimiter_at, $delimiter_length );
		if ( ! isset( $delimiter ) ) {
			return array( 'no-more-tokens', null, null, null, null );
		}

		$name  = $delimiter->allocate_and_return_block_type();
		$attrs = $delimiter->allocate_and_return_parsed_attributes() ?? array();

		switch ( $delimiter->get_delimiter_type() ) {
			case WP_Parsed_Block_Delimiter_Info::VOID:
				return array( 'void-block', $name, $attrs, $delimiter_at, $delimiter_length );

			case WP_Parsed_Block_Delimiter_Info::CLOSER:
				return array( 'block-closer', $name, null, $delimiter_at, $delimiter_length );

			case WP_Parsed_Block_Delimiter_Info::OPENER:
				return array( 'block-opener', $name, $attrs, $delimiter_at, $delimiter_length );
		}
	}

	/**
	 * Returns a new block object for freeform HTML
	 *
	 * @internal
	 * @since 3.9.0
	 *
	 * @param string $inner_html HTML content of block.
	 * @return WP_Block_Parser_Block freeform block object.
	 */
	public function freeform( $inner_html ) {
		return new WP_Block_Parser_Block( null, array(), array(), $inner_html, array( $inner_html ) );
	}

	/**
	 * Pushes a length of text from the input document
	 * to the output list as a freeform block.
	 *
	 * @internal
	 * @since 5.0.0
	 * @param null $length how many bytes of document text to output.
	 */
	public function add_freeform( $length = null ) {
		$length = $length ? $length : strlen( $this->document ) - $this->offset;

		if ( 0 === $length ) {
			return;
		}

		$this->output[] = (array) $this->freeform( substr( $this->document, $this->offset, $length ) );
	}

	/**
	 * Given a block structure from memory pushes
	 * a new block to the output list.
	 *
	 * @internal
	 * @since 5.0.0
	 * @param WP_Block_Parser_Block $block        The block to add to the output.
	 * @param int                   $token_start  Byte offset into the document where the first token for the block starts.
	 * @param int                   $token_length Byte length of entire block from start of opening token to end of closing token.
	 * @param int|null              $last_offset  Last byte offset into document if continuing form earlier output.
	 */
	public function add_inner_block( WP_Block_Parser_Block $block, $token_start, $token_length, $last_offset = null ) {
		$parent                       = $this->stack[ count( $this->stack ) - 1 ];
		$parent->block->innerBlocks[] = (array) $block;
		$html                         = substr( $this->document, $parent->prev_offset, $token_start - $parent->prev_offset );

		if ( ! empty( $html ) ) {
			$parent->block->innerHTML     .= $html;
			$parent->block->innerContent[] = $html;
		}

		$parent->block->innerContent[] = null;
		$parent->prev_offset           = $last_offset ? $last_offset : $token_start + $token_length;
	}

	/**
	 * Pushes the top block from the parsing stack to the output list.
	 *
	 * @internal
	 * @since 5.0.0
	 * @param int|null $end_offset byte offset into document for where we should stop sending text output as HTML.
	 */
	public function add_block_from_stack( $end_offset = null ) {
		$stack_top   = array_pop( $this->stack );
		$prev_offset = $stack_top->prev_offset;

		$html = isset( $end_offset )
			? substr( $this->document, $prev_offset, $end_offset - $prev_offset )
			: substr( $this->document, $prev_offset );

		if ( ! empty( $html ) ) {
			$stack_top->block->innerHTML     .= $html;
			$stack_top->block->innerContent[] = $html;
		}

		if ( isset( $stack_top->leading_html_start ) ) {
			$this->output[] = (array) $this->freeform(
				substr(
					$this->document,
					$stack_top->leading_html_start,
					$stack_top->token_start - $stack_top->leading_html_start
				)
			);
		}

		$this->output[] = (array) $stack_top->block;
	}
}

/**
 * WP_Block_Parser_Block class.
 *
 * Required for backward compatibility in WordPress Core.
 */
require_once __DIR__ . '/class-wp-block-parser-block.php';

/**
 * WP_Block_Parser_Frame class.
 *
 * Required for backward compatibility in WordPress Core.
 */
require_once __DIR__ . '/class-wp-block-parser-frame.php';
