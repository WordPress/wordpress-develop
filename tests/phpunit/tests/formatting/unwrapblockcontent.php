<?php

/**
 * @group formatting
 * @covers ::wp_split_paragraphs_around_block_shortcodes
 */
class Tests_Formatting_WpSplitParagraphsAroundBlockShortcodes extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		add_shortcode(
			'playlist',
			static function () {
				return '<div class="wp-playlist wp-video-playlist wp-playlist-dark">'
					. '<div class="wp-playlist-next"></div>'
					. '<div class="wp-playlist-prev"></div>'
					. '<script type="application/json" class="wp-playlist-script">{"tracks":[]}</script>'
					. '</div>';
			}
		);
	}

	public function tear_down() {
		remove_shortcode( 'playlist' );
		parent::tear_down();
	}

	/**
	 * Reproduces #50863 end to end through the real content pipeline.
	 */
	public function test_playlist_with_trailing_text_produces_valid_nesting() {
		$raw = '[playlist ids="1,2,3"] Hey everyone, check out my new songs!';

		$content = wpautop( $raw );
		$content = shortcode_unautop( $content );
		$content = wp_split_paragraphs_around_block_shortcodes( $content );
		$content = do_shortcode( $content );

		$this->assertStringNotContainsString( '<p><div', $content );
		$this->assertStringNotContainsString( '<p></p>', $content );
		$this->assertStringContainsString( '<p>Hey everyone, check out my new songs!</p>', $content );
		$this->assertStringContainsString( '<div class="wp-playlist-next"></div>', $content );
	}

	/**
	 * Leading text before the shortcode should get its own paragraph too.
	 */
	public function test_playlist_with_leading_text_produces_valid_nesting() {
		$raw = 'Check this out: [playlist ids="1,2,3"]';

		$content = wpautop( $raw );
		$content = shortcode_unautop( $content );
		$content = wp_split_paragraphs_around_block_shortcodes( $content );
		$content = do_shortcode( $content );

		$this->assertStringContainsString( '<p>Check this out:</p>', $content );
		$this->assertStringNotContainsString( '<p>Check this out: <div', $content );
	}

	/**
	 * A shortcode alone in its paragraph is shortcode_unautop()'s job; the new
	 * function must be a no-op there, not double-handle it.
	 */
	public function test_standalone_playlist_shortcode_is_left_to_shortcode_unautop() {
		$raw = '[playlist ids="1,2,3"]';

		$content       = wpautop( $raw );
		$after_unautop = shortcode_unautop( $content );

		// wpautop() emits each paragraph as "<p>...</p>\n"; shortcode_unautop() only
		// replaces the matched <p>...</p> span, so that trailing newline is expected
		// and irrelevant to what's being tested here.
		$this->assertSame( '[playlist ids="1,2,3"]', trim( $after_unautop ) );
		$this->assertSame( $after_unautop, wp_split_paragraphs_around_block_shortcodes( $after_unautop ) );
	}

	/**
	 * A non-block-level shortcode (not in the registered tag list) must be left alone,
	 * even with surrounding text, since it isn't known to expand into block markup.
	 */
	public function test_non_block_level_shortcode_is_untouched() {
		$content = '<p>Some text with [caption]a normal caption[/caption] inline.</p>';

		$this->assertSame( $content, wp_split_paragraphs_around_block_shortcodes( $content ) );
	}

	/**
	 * Raw HTML a user typed directly into a paragraph, with no shortcode involved
	 * at all, must never be touched by this filter.
	 */
	public function test_raw_user_html_with_no_shortcode_is_untouched() {
		$content = '<p>Some text with a <div>raw div a user pasted</div> inside it.</p>';

		$this->assertSame( $content, wp_split_paragraphs_around_block_shortcodes( $content ) );
	}

	/**
	 * The wp_block_level_shortcode_tags filter lets other block-producing
	 * shortcodes (core's own [gallery], or a plugin's) opt in.
	 */
	public function test_block_level_tag_list_is_filterable() {
		add_shortcode(
			'stub_gallery',
			static function () {
				return '<div class="gallery"><div class="gallery-item"></div></div>';
			}
		);

		$add_gallery = static function ( $tags ) {
			$tags[] = 'stub_gallery';
			return $tags;
		};
		add_filter( 'wp_block_level_shortcode_tags', $add_gallery );

		$raw     = '[stub_gallery] Look at these!';
		$content = wpautop( $raw );
		$content = shortcode_unautop( $content );
		$content = wp_split_paragraphs_around_block_shortcodes( $content );
		$content = do_shortcode( $content );

		remove_filter( 'wp_block_level_shortcode_tags', $add_gallery );
		remove_shortcode( 'stub_gallery' );

		$this->assertStringNotContainsString( '<p><div', $content );
		$this->assertStringContainsString( '<p>Look at these!</p>', $content );
	}
}
