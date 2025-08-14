<?php

/**
 * @todo Benchmark extracting blocks against {@link \parse_blocks()}.
 */
class WP_Block_Processor extends WP_Block_Scanner {
	private $open_blocks = array();

	private $was_void = false;

	public static function create( $html ) {
		return new self( $html );
	}

	public function next_delimiter( $block_type = null, $html_spans = 'skip-html' ) {
		if ( $this->was_void ) {
			array_pop( $this->open_blocks );
			$this->was_void = false;
		}

		if ( false === parent::next_token( $html_spans ) ) {
			return false;
		}

		$block_type = $this->get_block_type();

		switch ( $this->get_delimiter_type() ) {
			case parent::OPENER:
				$this->open_blocks[] = $block_type;
				break;

			case parent::CLOSER:
				$closed_block = array_pop( $this->open_blocks );
				if ( $block_type !== $closed_block ) {
					throw new Error( 'Tried to close a block of another type.' );
				}
				break;

			case parent::VOID:
				$this->open_blocks[] = $block_type;
				$this->was_void      = true;
				break;
		}

		if ( isset( $block_type ) && ! $this->is_block_type( $block_type ) ) {
			return $this->next_delimiter( $block_type, $html_spans );
		}

		return true;
	}

	public function get_breadcrumbs() {
		return $this->open_blocks;
	}

	/**
	 * Returns the depth of the open blocks where the processor is currently matched.
	 *
	 * Depth increases before visiting openers and void blocks,
	 * and decreases before visiting closers.
	 * @return int|null
	 */
	public function get_depth() {
		return count( $this->open_blocks );
	}

	/**
	 * Extracts a block object starting at a matched block delimiter opener.
	 *
	 * @todo Use iteration instead of recursion, or at least refactor to tail-call form.
	 *
	 * @return array|null
	 */
	public function extract_block() {
		if ( parent::MATCHED !== $this->state || ! $this->opens_block() ) {
			return null;
		}

		$block = array(
			'blockName'    => $this->get_block_type(),
			'attrs'        => $this->allocate_and_return_parsed_attributes() ?? array(),
			'innerHTML'    => '',
			'innerBlocks'  => array(),
			'innerContent' => array(),
		);

		$depth       = $this->get_depth();
		$child_depth = $depth + 1;
		while ( $this->next_delimiter( null, 'visit-html' ) && $this->get_depth() > $depth ) {
			if ( $this->get_depth() === $child_depth && $this->opens_block( 'core/freeform' ) ) {
				$chunk              = $this->get_html_content_and_advance();
				$block['innerHTML'] .= $chunk;

				$last_chunk = count( $block['innerContent'] ) - 1;
				if ( isset( $block['innerContent'][ $last_chunk ] ) ) {
					$block['innerContent'][ count( $block['innerContent'] ) - 1 ] = $chunk;
				} else {
					$block['innerContent'][] = $chunk;
				}

				continue;
			}

			/**
			 * Inner blocks.
			 *
			 * @todo This is a decent place to call {@link \render_block()}
			 */
			if ( $this->opens_block() ) {
				$inner_block             = $this->extract_block();
				$block['innerBlocks'][]  = $inner_block;
				$block['innerContent'][] = null;
			}
		}

		if ( empty( $block['innerBlocks'] ) ) {
			unset( $block['innerBlocks'] );
		}

		return $block;
	}
}
