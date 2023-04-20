<?php
/**
 * WP_Directive_Processor class.
 *
 * @package wp-directives
 */

/**
 * Process WP directives.
 */
class WP_Directive_Processor extends WP_HTML_Tag_Processor {
	/**
	 * Find the matching closing tag for an opening tag.
	 *
	 * When called while on an open tag, traverse the HTML until we find
	 * the matching closing tag, respecting any in-between content, including
	 * nested tags of the same name. Return false when called on a closing or
	 * void tag, or if no matching closing tag was found.
	 *
	 * @return bool True if a matching closing tag was found.
	 */
	public function next_balanced_closer() {
		$depth = 0;

		$tag_name = $this->get_tag();

		if ( self::is_html_void_element( $tag_name ) ) {
			return false;
		}

		while ( $this->next_tag(
			array(
				'tag_name'    => $tag_name,
				'tag_closers' => 'visit',
			)
		) ) {
			if ( ! $this->is_tag_closer() ) {
				$depth++;
				continue;
			}

			if ( 0 === $depth ) {
				return true;
			}

			$depth--;
		}

		return false;
	}

	/**
	 * Return the content between two balanced tags.
	 *
	 * When called on an opening tag, return the HTML content found between
	 * that opening tag and its matching closing tag.
	 *
	 * @return string The content between the current opening and its matching closing tag.
	 */
	public function get_inner_html() {
		$bookmarks = $this->get_balanced_tag_bookmarks();
		if ( ! $bookmarks ) {
			return false;
		}
		list( $start_name, $end_name ) = $bookmarks;

		$start = $this->bookmarks[ $start_name ]->end + 1;
		$end   = $this->bookmarks[ $end_name ]->start;

		$this->seek( $start_name ); // Return to original position.
		$this->release_bookmark( $start_name );
		$this->release_bookmark( $end_name );

		return substr( $this->html, $start, $end - $start );
	}

	/**
	 * Set the content between two balanced tags.
	 *
	 * When called on an opening tag, set the HTML content found between
	 * that opening tag and its matching closing tag.
	 *
	 * @param string $new_html The string to replace the content between the matching tags with.
	 * @return bool            Whether the content was successfully replaced.
	 */
	public function set_inner_html( $new_html ) {
		$this->get_updated_html(); // Apply potential previous updates.

		$bookmarks = $this->get_balanced_tag_bookmarks();
		if ( ! $bookmarks ) {
			return false;
		}
		list( $start_name, $end_name ) = $bookmarks;

		$start = $this->bookmarks[ $start_name ]->end + 1;
		$end   = $this->bookmarks[ $end_name ]->start;

		$this->seek( $start_name ); // Return to original position.
		$this->release_bookmark( $start_name );
		$this->release_bookmark( $end_name );

		$this->lexical_updates[] = new WP_HTML_Text_Replacement( $start, $end, $new_html );
		return true;
	}

	/**
	 * Return a pair of bookmarks for the current opening tag and the matching closing tag.
	 *
	 * @return array|false A pair of bookmarks, or false if there's no matching closing tag.
	 */
	public function get_balanced_tag_bookmarks() {
		$i = 0;
		while ( array_key_exists( 'start' . $i, $this->bookmarks ) ) {
			++$i;
		}
		$start_name = 'start' . $i;

		$this->set_bookmark( $start_name );
		if ( ! $this->next_balanced_closer() ) {
			$this->release_bookmark( $start_name );
			return false;
		}

		$i = 0;
		while ( array_key_exists( 'end' . $i, $this->bookmarks ) ) {
			++$i;
		}
		$end_name = 'end' . $i;
		$this->set_bookmark( $end_name );

		return array( $start_name, $end_name );
	}

	/**
	 * Wrap the current node in a given tag.
	 *
	 * When positioned on a tag opener, locate its matching closer, and wrap everything
	 * in the tag specified as an argument. When positioned on a void element, wrap that
	 * element in the argument tag.
	 *
	 * Note that the internal pointer will continue to point to the same tag as before
	 * calling the function.
	 *
	 * @param string $tag An HTML tag, specified in uppercase (e.g. "DIV").
	 * @return string|false The name of a bookmark pointing to the wrapping tag opener
	 *                      if successful; false otherwise.
	 *
	 * @todo Allow passing in tags with attributes, e.g. <template id="abc">?
	 */
	public function wrap_in_tag( $tag ) {
		if ( $this->is_tag_closer() ) {
			return false;
		}

		if ( self::is_html_void_element( $tag ) ) {
			// _doing_it_wrong(
			// __METHOD__,
			// __( 'Cannot wrap HTML in void tag.' ),
			// '6.3.0'
			// );
			return false;
		}

		//$this->get_updated_html(); // Apply potential previous updates.

		if ( self::is_html_void_element( $this->get_tag() ) ) {
			// We don't have direct access to the start and end position of the
			// current tag. As a workaround, we set a bookmark that we then
			// release immediately.
			$i = 0;
			while ( array_key_exists( 'void' . $i, $this->bookmarks ) ) {
				++$i;
			}
			$start_name = 'void' . $i;

			$this->set_bookmark( $start_name );

			$start = $this->bookmarks[ $start_name ]->start;
			$end   = $this->bookmarks[ $start_name ]->end + 1;
		} else {
			$bookmarks = $this->get_balanced_tag_bookmarks();
			if ( ! $bookmarks ) {
				return false;
			}
			list( $start_name, $end_name ) = $bookmarks;

			$start = $this->bookmarks[ $start_name ]->start;
			$end   = $this->bookmarks[ $end_name ]->end + 1;

			$this->release_bookmark( $end_name );
		}

		$tag                     = strtolower( $tag );
		$this->lexical_updates[] = new WP_HTML_Text_Replacement( $start, $start, "<$tag>" );
		$this->lexical_updates[] = new WP_HTML_Text_Replacement( $end, $end, "</$tag>" );

		$this->seek( $start_name ); // Return to original position.
		$this->release_bookmark( $start_name );

		$i = 0;
		while ( array_key_exists( $tag . $i, $this->bookmarks ) ) {
			++$i;
		}
		$bookmark_name                     = $tag . $i;
		$this->bookmarks[ $bookmark_name ] = new WP_HTML_Span(
			$start,
			$start + strlen( $tag ) + 2
		);
		return $bookmark_name;
	}

	/**
	 * Whether a given HTML element is void (e.g. <br>).
	 *
	 * @param string $tag_name The element in question.
	 * @return bool True if the element is void.
	 *
	 * @see https://html.spec.whatwg.org/#elements-2
	 */
	public static function is_html_void_element( $tag_name ) {
		switch ( $tag_name ) {
			case 'AREA':
			case 'BASE':
			case 'BR':
			case 'COL':
			case 'EMBED':
			case 'HR':
			case 'IMG':
			case 'INPUT':
			case 'LINK':
			case 'META':
			case 'SOURCE':
			case 'TRACK':
			case 'WBR':
				return true;

			default:
				return false;
		}
	}
}
