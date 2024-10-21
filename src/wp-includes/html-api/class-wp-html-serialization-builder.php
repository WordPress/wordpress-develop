<?php

/**
 * HTML_Serialization_Builder class.
 *
 * This class can be used to perform structural changes to an
 * HTML document while maintaining some but not all safety
 * protections. Namely, proper nesting structure of HTML is
 * maintained, but HTML updates could still leak out of the
 * containing parent node. For example, this allows inserting
 * an A element inside an open A element, which would close
 * the containing A element.
 *
 * Modifications may be requested for a document _once_ after
 * matching a token. Due to the way the modifications are
 * applied, it's not possible to set the inner HTML for a
 * node more than once, or appent more than one HTML chunk.
 */
class HTML_Serialization_Builder extends WP_HTML_Processor {
	private $output;
	private $last_token = null;

	public function next_token(): bool {
		if ( isset( $this->last_token ) ) {
			$this->output    .= $this->last_token;
			$this->last_token = null;
		}

		$did_match = parent::next_token();
		$this->last_token = $this->serialize_token();

		return $did_match;
	}

	public function build() {
		if ( isset( $this->last_token ) ) {
			$this->output    .= $this->last_token;
			$this->last_token = null;
		}

		return $this->output;
	}

	public function append( $html ) {
		if ( ! isset( $this->last_token ) ) {
			return false;
		}

		$this->output    .= $this->last_token;
		$this->output    .= WP_HTML_Processor::normalize( $html );
		$this->last_token = null;
	}

	public function prepend( $html ) {
		if ( ! isset( $this->last_token ) ) {
			return false;
		}

		$this->output    .= WP_HTML_Processor::normalize( $html );
		$this->output    .= $this->last_token;
		$this->last_token = null;
	}

	public function set_inner_html( $html ) {
		if ( $this->is_tag_closer() || ! isset( $this->last_token ) ) {
			return false;
		}

		$this->output    .= $this->last_token;
		$this->output    .= WP_HTML_Processor::normalize( $html );
		$this->last_token = null;

		$depth = $this->get_current_depth();
		while ( $this->get_current_depth() >= $depth && parent::next_token() ) {
			$this->last_token = null;
			continue;
		}

		$this->output    .= $this->serialize_token();
		$this->last_token = null;
		return true;
	}

	public function set_outer_html( $html ) {
		if ( $this->is_tag_closer() || ! isset( $this->last_token ) ) {
			return false;
		}

		$this->output    .= WP_HTML_Processor::normalize( $html );
		$this->last_token = null;

		$depth = $this->get_current_depth();
		while ( $this->get_current_depth() >= $depth && parent::next_token() ) {
			$this->last_token = null;
			continue;
		}

		$this->last_token = null;
		return true;
	}

	public function wrap( $wrapping_tag ) {
		if ( $this->is_tag_closer() || ! isset( $this->last_token ) ) {
			return false;
		}

		$wrapper = WP_HTML_Processor::create_fragment( $wrapping_tag );
		if (
			false === $wrapper->next_token() ||
			'#tag' !== $wrapper->get_token_type() ||
			WP_HTML_Processor::is_void( $wrapper->get_token_name() )
		) {
			return false;
		}

		$this->output    .= $wrapper->serialize_token();
		$this->output    .= $this->serialize_token();
		$this->last_token = null;

		$depth = $this->get_current_depth();
		while ( $this->get_current_depth() > $depth && parent::next_token() ) {
			$this->output    .= $this->serialize_token();
			$this->last_token = null;
		}

		$this->output    .= '</' . strtolower( $wrapper->get_tag() ) . '>';
		$this->last_token = null;
		return true;
	}

	/**
	 * @todo This currently doesn't remove the end tag; why?
	 *
	 * @return bool
	 */
	public function unwrap() {
		if ( $this->is_tag_closer() || ! isset( $this->last_token ) ) {
			return false;
		}

		$this->last_token = null;
		$depth            = $this->get_current_depth();
		while ( $this->get_current_depth() >= $depth && parent::next_token() ) {
			$this->output    .= $this->serialize_token();
			$this->last_token = null;
		}

		$this->last_token = null;
		return true;
	}
}
