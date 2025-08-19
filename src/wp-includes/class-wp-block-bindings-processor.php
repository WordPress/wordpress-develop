<?php

/**
 * WP_Block_Bindings_Processor class.
 *
 * This class can be used to perform the sort of structural
 * changes to an HTML document that are required by
 * Block Bindings.
 *
 * @access private
 *
 * @package WordPress
 * @subpackage Block Bindings
 * @since 6.9.0
 */
class WP_Block_Bindings_Processor extends WP_HTML_Processor {
	private $output         = '';
	private $end_of_flushed = 0;

	public function build() {
		return $this->output . substr( $this->html, $this->end_of_flushed );
	}

	/**
	 * Replace the rich text content between a tag opener and matching closer.
	 *
	 * When stopped on a tag opener, replace the content enclosed by it and its
	 * matching closer with the provided rich text.
	 *
	 * @param string $rich_text The rich text to replace the original content with.
	 * @return bool True on success.
	 */
	public function replace_rich_text( $rich_text ) {
		if ( $this->is_tag_closer() ) {
			return false;
		}

		$depth = $this->get_current_depth();

		$this->set_bookmark( '_wp_block_bindings_tag_opener' );
		// The bookmark names are prefixed with `_` so the key below has an extra `_`.
		$bm            = $this->bookmarks['__wp_block_bindings_tag_opener'];
		$this->output .= substr( $this->html, $this->end_of_flushed, $bm->start + $bm->length );
		$this->output .= $rich_text;
		$this->release_bookmark( '_wp_block_bindings_tag_opener' );

		// Find matching tag closer.
		while ( $this->next_token() && $this->get_current_depth() >= $depth ) {
		}

		$this->set_bookmark( '_wp_block_bindings_tag_closer' );
		$bm                   = $this->bookmarks['__wp_block_bindings_tag_closer'];
		$this->end_of_flushed = $bm->start;
		$this->release_bookmark( '_wp_block_bindings_tag_closer' );

		return true;
	}
}
