<?php

/**
 * Tests that inline note markers are unwrapped in rendered block output via the
 * render_block filter, while raw post content is left untouched.
 *
 * The `<mark class="wp-note">` wrapper is removed entirely - both the open tag
 * and its matching closer - so no note marker or metadata reaches the public
 * HTML, while the marked text (and any nested formatting) is preserved.
 *
 * @group comment
 * @group notes
 *
 * @covers ::wp_strip_inline_note_markers
 */
class Tests_Comment_StripInlineNoteMarkers extends WP_UnitTestCase {

	/**
	 * @ticket 65482
	 */
	public function test_strip_unwraps_marker_from_mark(): void {
		$html     = '<p>Hello <mark class="wp-note" data-id="7">marked</mark> world</p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>Hello marked world</p>', $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_handles_multiple_markers_in_one_block(): void {
		$html     = '<p><mark class="wp-note" data-id="1">a</mark> and <mark class="wp-note" data-id="2">b</mark></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>a and b</p>', $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_passes_through_block_content_without_markers(): void {
		$html     = '<p>Plain text with no notes here.</p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( $html, $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_keeps_other_classes_when_removing_wp_note(): void {
		// The whole wrapper is removed, so any companion classes go with it.
		$html     = '<p><mark class="custom wp-note other" data-id="3">x</mark></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>x</p>', $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_leaves_unrelated_marks_untouched(): void {
		// A user highlight (`core/text-color`) serializes as a plain `<mark>` and
		// must survive untouched.
		$html     = '<p><mark style="background-color:#ff0">keep me</mark></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( $html, $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_does_not_match_partial_class_names(): void {
		// `wp-note-foo` is a different class and must not be treated as a marker;
		// a regex word boundary would incorrectly match it.
		$html     = '<p><mark class="wp-note-foo">keep me</mark></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( $html, $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_preserves_user_mark_attributes_next_to_note(): void {
		// A user/plugin `<mark>` with several attributes sitting beside a note
		// marker must be returned byte-for-byte; only the `wp-note` wrapper goes.
		$html     = '<p><mark class="highlight" style="background-color:#ff0" data-id="99" title="kept">user</mark> and <mark class="wp-note" data-id="4">noted</mark></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p><mark class="highlight" style="background-color:#ff0" data-id="99" title="kept">user</mark> and noted</p>', $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_preserves_nested_formatting(): void {
		// A note wrapping already-formatted text (e.g. coloured text) serializes
		// with nested inline elements. The wrapper is removed while the inner
		// markup is preserved intact.
		$html     = '<p><mark class="wp-note" data-id="1">a <span style="color:red">red</span> b</mark></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>a <span style="color:red">red</span> b</p>', $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_unwraps_note_but_keeps_inner_highlight_mark(): void {
		// A note wrapping a user highlight nests `<mark>` inside `<mark>`. Only the
		// note wrapper is removed; the inner highlight `<mark>` is preserved, and
		// the closer pairing must not unbalance.
		$html     = '<p><mark class="wp-note" data-id="1">a <mark style="background-color:#ff0">hi</mark> b</mark></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>a <mark style="background-color:#ff0">hi</mark> b</p>', $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_handles_overlapping_nested_note_markers(): void {
		// Two notes anchored on overlapping text serialize as nested `<mark>`s.
		// Both wrappers are removed and the text survives.
		$html     = '<p><mark class="wp-note" data-id="1">a<mark class="wp-note" data-id="2">b</mark>c</mark></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>abc</p>', $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_ignores_mark_like_text_inside_a_comment(): void {
		// A `</mark>` sequence inside an HTML comment is text, not a tag. Walking
		// the parsed token stream ignores it; a raw regex over the string would
		// mistake it for the note's closer, unbalance the pairing, and corrupt
		// both the comment and the real wrapper.
		$html     = '<p><mark class="wp-note" data-id="1">a<!-- </mark> -->b</mark>tail</p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>a<!-- </mark> -->btail</p>', $stripped );
	}

	/**
	 * A note marker left unclosed (e.g. by a hand edit in the code editor) still
	 * has its opener stripped, so no `wp-note` metadata leaks to the front end.
	 *
	 * @ticket 65482
	 */
	public function test_strip_unwraps_unclosed_note_marker(): void {
		$html     = '<p><mark class="wp-note" data-id="1">a';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>a', $stripped );
	}

	/**
	 * A stray `</mark>` closer with no matching opener is not a note marker, so it
	 * is left exactly as it was rather than corrupting the surrounding markup.
	 *
	 * @ticket 65482
	 */
	public function test_strip_leaves_stray_mark_closer_untouched(): void {
		$html     = '<p>a</mark>b</p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( $html, $stripped );
	}

	/**
	 * Note markers can be crossed with other inline formatting in hand-edited or
	 * otherwise ill-formed content. The full HTML tree builder would abort on this
	 * nesting and leave the marker (and its metadata) in place; scanning tokens
	 * strips the `wp-note` marker regardless and keeps the rest of the markup.
	 *
	 * @ticket 65482
	 */
	public function test_strip_unwraps_note_marker_with_improper_nesting(): void {
		$html     = '<p><mark class="wp-note" data-id="1">a<i>b</mark>c</i></p>';
		$stripped = wp_strip_inline_note_markers( $html );

		$this->assertSame( '<p>a<i>bc</i></p>', $stripped );
	}

	/**
	 * @ticket 65482
	 */
	public function test_strip_filter_is_registered_on_render_block(): void {
		// Guards against future hook rewiring that would silently leave
		// inline-note markers in rendered output.
		$this->assertNotFalse(
			has_filter( 'render_block', 'wp_strip_inline_note_markers' )
		);
	}
}
