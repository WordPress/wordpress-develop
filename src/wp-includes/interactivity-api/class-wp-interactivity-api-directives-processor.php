<?php
/**
 * Interactivity API: WP_Interactivity_API_Directives_Processor class.
 *
 * @package WordPress
 * @subpackage Interactivity API
 * @since 6.5.0
 */

/**
 * Class used to iterate over the tags of an HTML string and help process the
 * directive attributes.
 *
 * @since 6.5.0
 *
 * @access private
 */
final class WP_Interactivity_API_Directives_Processor extends WP_HTML_Tag_Processor {
	/**
	 * List of tags whose closer tag is not visited by the WP_HTML_Tag_Processor.
	 *
	 * @since 6.5.0
	 *
	 * @var string[]
	 */
	const TAGS_THAT_DONT_VISIT_CLOSER_TAG = array(
		'SCRIPT',
		'IFRAME',
		'NOEMBED',
		'NOFRAMES',
		'STYLE',
		'TEXTAREA',
		'TITLE',
		'XMP',
	);

	/**
	 * Returns the content between two balanced tags.
	 *
	 * @since 6.5.0
	 *
	 * @access private
	 *
	 * @return string|null The content between the current opening and its matching closing tag or null if it doesn't
	 *                     find the matching closing tag.
	 */
	public function get_content_between_balanced_tags() {
		$bookmarks = $this->get_balanced_tag_bookmarks();
		if ( ! $bookmarks ) {
			return null;
		}
		list( $start_name, $end_name ) = $bookmarks;

		$start = $this->bookmarks[ $start_name ]->start + $this->bookmarks[ $start_name ]->length + 1;
		$end   = $this->bookmarks[ $end_name ]->start;

		$this->seek( $start_name );
		$this->release_bookmark( $start_name );
		$this->release_bookmark( $end_name );

		return substr( $this->html, $start, $end - $start );
	}

	/**
	 * Sets the content between two balanced tags.
	 *
	 * @since 6.5.0
	 *
	 * @access private
	 *
	 * @param string $new_content The string to replace the content between the matching tags.
	 * @return bool Whether the content was successfully replaced.
	 */
	public function set_content_between_balanced_tags( string $new_content ): bool {
		$this->get_updated_html();

		$bookmarks = $this->get_balanced_tag_bookmarks();
		if ( ! $bookmarks ) {
			return false;
		}
		list( $start_name, $end_name ) = $bookmarks;

		$start = $this->bookmarks[ $start_name ]->start + $this->bookmarks[ $start_name ]->length + 1;
		$end   = $this->bookmarks[ $end_name ]->start;

		$this->seek( $start_name );
		$this->release_bookmark( $start_name );
		$this->release_bookmark( $end_name );

		$this->lexical_updates[] = new WP_HTML_Text_Replacement( $start, $end - $start, esc_html( $new_content ) );
		return true;
	}

	/**
	 * Returns a pair of bookmarks for the current opening tag and the matching
	 * closing tag.
	 *
	 * @since 6.5.0
	 *
	 * @return array|null A pair of bookmarks, or null if there's no matching closing tag.
	 */
	private function get_balanced_tag_bookmarks() {
		static $i   = 0;
		$start_name = 'start_of_balanced_tag_' . ++$i;

		$this->set_bookmark( $start_name );
		if ( ! $this->next_balanced_closer() ) {
			$this->release_bookmark( $start_name );
			return null;
		}

		$end_name = 'end_of_balanced_tag_' . ++$i;
		$this->set_bookmark( $end_name );

		return array( $start_name, $end_name );
	}

	/**
	 * Finds the matching closing tag for an opening tag.
	 *
	 * When called while the processor is on an open tag, it traverses the HTML
	 * until it finds the matching closer tag, respecting any in-between content,
	 * including nested tags of the same name. Returns false when called on a
	 * closer tag, a tag that doesn't have a closer tag (void), a tag that
	 * doesn't visit the closer tag, or if no matching closing tag was found.
	 *
	 * @since 6.5.0
	 *
	 * @return bool Whether a matching closing tag was found.
	 */
	private function next_balanced_closer(): bool {
		$depth    = 0;
		$tag_name = $this->get_tag();

		if ( ! $this->has_and_visits_its_closer_tag() ) {
			return false;
		}

		while ( $this->next_tag(
			array(
				'tag_name'    => $tag_name,
				'tag_closers' => 'visit',
			)
		) ) {
			if ( ! $this->is_tag_closer() ) {
				++$depth;
				continue;
			}

			if ( 0 === $depth ) {
				return true;
			}

			--$depth;
		}

		return false;
	}

	/**
	 * Checks whether the current tag has and visits a closer tag.
	 *
	 * @since 6.5.0
	 *
	 * @access private
	 *
	 * @return bool Whether the current tag has a closer tag.
	 */
	public function has_and_visits_its_closer_tag(): bool {
		$tag_name = $this->get_tag();
		return ! WP_HTML_Processor::is_void( null !== $tag_name ? $tag_name : '' ) &&
			! in_array( $tag_name, self::TAGS_THAT_DONT_VISIT_CLOSER_TAG, true );
	}
}
