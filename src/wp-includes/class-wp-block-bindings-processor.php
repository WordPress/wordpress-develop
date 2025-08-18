<?php

class WP_Block_Bindings_Processor extends WP_HTML_Processor {
	private $output         = '';
	private $end_of_flushed = 0;

	public function build() {
		return $this->output . substr( $this->html, $this->end_of_flushed );
	}

	public function replace_rich_text( $rich_text ) {
		if ( $this->is_tag_closer() ) {
			return false;
		}

		$tag_name = $this->get_tag();
		$depth    = $this->get_current_depth();

		$this->set_bookmark( '_wp_block_bindings_tag_opener' );
		// The bookmark names are prefixed with `_` so the key below has an extra `_`.
		$bm            = $this->bookmarks['__wp_block_bindings_tag_opener'];
		$this->output .= substr( $this->html, $this->end_of_flushed, $bm->start + $bm->length );
		$this->output .= $rich_text;
		$this->release_bookmark( '_wp_block_bindings_tag_opener' );

		while ( $this->next_token() && $this->get_current_depth() >= $depth ) {
		}

		$this->set_bookmark( '_wp_block_bindings_tag_closer' );
		$bm                   = $this->bookmarks['__wp_block_bindings_tag_closer'];
		$this->end_of_flushed = $bm->start;
		$this->release_bookmark( '_wp_block_bindings_tag_closer' );
	}
}
