<?php

/**
 * @group formatting
 * @covers ::wp_unwrap_block_level_content_in_paragraphs
 */
class Tests_Formatting_WpUnwrapBlockLevelContentInParagraphs extends WP_UnitTestCase {

	/**
	 * Reproduces #50863: [playlist ids="1,2,3"] Hey everyone, check out my new songs!
	 *
	 * This is the literal do_shortcode() output for a two-track video playlist,
	 * still wrapped in the <p> that wpautop()/shortcode_unautop() left in place
	 * because the shortcode wasn't alone in its paragraph.
	 */
	public function test_splits_playlist_output_from_trailing_text() {
		$wrapped = '<p><div class="wp-playlist wp-video-playlist wp-playlist-dark">
		<video controls="controls" preload="none" width="640"
		 height="360"	></video>
	<div class="wp-playlist-next"></div>
	<div class="wp-playlist-prev"></div>
	<noscript>
	<ol>
		<li><a href=\'http://localhost:8889/wp-content/uploads/2026/09/file_example_MP4_480_1_5MG.mp4\'>file_example_MP4_480_1_5MG</a></li><li><a href=\'http://localhost:8889/wp-content/uploads/2026/09/file_example_MP4_1280_10MG.mp4\'>file_example_MP4_1280_10MG</a></li>	</ol>
	</noscript>
	<script type="application/json" class="wp-playlist-script">{"type":"video","tracklist":true,"tracknumbers":true,"images":true,"artists":true,"tracks":[]}</script>
</div>
	 Hey everyone, check out my new songs!</p>
';

		$result = wp_unwrap_block_level_content_in_paragraphs( $wrapped );

		// The div is no longer inside a <p>.
		$this->assertStringNotContainsString( '<p><div', $result );

		// The trailing text got its own real paragraph instead of being orphaned.
		$this->assertStringContainsString( '<p>Hey everyone, check out my new songs!</p>', $result );

		// No stray empty <p></p> either side of the block.
		$this->assertStringNotContainsString( '<p></p>', $result );

		// The div itself, including its nested divs, survived untouched.
		$this->assertStringContainsString( '<div class="wp-playlist-next"></div>', $result );
		$this->assertStringContainsString( '<div class="wp-playlist-prev"></div>', $result );
	}

	/**
	 * Leading text before the block-level content should also get its own paragraph.
	 */
	public function test_splits_leading_text_from_block_content() {
		$wrapped = '<p>Check this out: <div class="wp-playlist"><div class="inner"></div></div></p>';

		$result = wp_unwrap_block_level_content_in_paragraphs( $wrapped );

		$this->assertStringContainsString( '<p>Check this out:</p>', $result );
		$this->assertStringContainsString( '<div class="wp-playlist"><div class="inner"></div></div>', $result );
		$this->assertStringNotContainsString( '<p>Check this out: <div', $result );
	}

	/**
	 * Multiple block-level elements in one paragraph should each be unwrapped,
	 * with text between them landing in its own paragraph.
	 */
	public function test_splits_multiple_block_elements_with_text_between() {
		$wrapped = '<p><div class="a"></div> middle text <div class="b"></div></p>';

		$result = wp_unwrap_block_level_content_in_paragraphs( $wrapped );

		$this->assertStringContainsString( '<div class="a"></div>', $result );
		$this->assertStringContainsString( '<p>middle text</p>', $result );
		$this->assertStringContainsString( '<div class="b"></div>', $result );
		$this->assertStringNotContainsString( '<p></p>', $result );
	}

	/**
	 * A paragraph with no block-level content at all must pass through unchanged.
	 */
	public function test_leaves_plain_paragraphs_untouched() {
		$plain = '<p>Just some ordinary text with <strong>inline</strong> markup.</p>';

		$this->assertSame( $plain, wp_unwrap_block_level_content_in_paragraphs( $plain ) );
	}

	/**
	 * A <p> wrapping only block-level content (no surrounding text) should end
	 * up as the bare block element with no leftover empty <p></p>.
	 */
	public function test_removes_wrapper_when_block_is_the_only_content() {
		$wrapped = '<p><div class="wp-playlist"><div class="inner"></div></div></p>';

		$result = wp_unwrap_block_level_content_in_paragraphs( $wrapped );

		$this->assertSame( '<div class="wp-playlist"><div class="inner"></div></div>', $result );
	}

	/**
	 * Content with no <p> tags at all should be returned as-is (fast path).
	 */
	public function test_returns_early_when_no_paragraphs_present() {
		$content = '<div>no paragraphs here</div>';

		$this->assertSame( $content, wp_unwrap_block_level_content_in_paragraphs( $content ) );
	}

	/**
	 * Full pipeline integration: wpautop -> shortcode_unautop -> do_shortcode ->
	 * our new filter, using a stub shortcode that mimics [playlist]'s nested-div
	 * output, matching the reported #50863 scenario end to end.
	 */
	public function test_full_content_pipeline_produces_valid_paragraph_structure() {
		add_shortcode(
			'stub_playlist',
			static function () {
				return '<div class="wp-playlist"><div class="wp-playlist-next"></div><div class="wp-playlist-prev"></div></div>';
			}
		);

		$raw = '[stub_playlist ids="1,2,3"] Hey everyone, check out my new songs!';

		$content = wpautop( $raw );
		$content = shortcode_unautop( $content );
		$content = do_shortcode( $content );
		$content = wp_unwrap_block_level_content_in_paragraphs( $content );

		remove_shortcode( 'stub_playlist' );

		$this->assertStringNotContainsString( '<p><div', $content );
		$this->assertStringContainsString( '<p>Hey everyone, check out my new songs!</p>', $content );
	}
}
