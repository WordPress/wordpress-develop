<?php

/**
 * Tests for wp_mediaelement_fallback().
 *
 * @group media
 *
 * @covers ::wp_mediaelement_fallback
 */
class Tests_Media_WpMediaelementFallback extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_returns_anchor_markup_for_url() {
		$url = 'https://example.com/audio.mp3';

		$this->assertSame(
			'<a href="https://example.com/audio.mp3">https://example.com/audio.mp3</a>',
			wp_mediaelement_fallback( $url )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_escapes_url_in_markup() {
		$url = 'https://example.com/audio.mp3?foo=1&bar=2"><script>alert(1)</script>';

		$this->assertSame(
			'<a href="https://example.com/audio.mp3?foo=1&#038;bar=2scriptalert(1)/script">https://example.com/audio.mp3?foo=1&#038;bar=2scriptalert(1)/script</a>',
			wp_mediaelement_fallback( $url )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_generated_output_and_url_and_can_replace_output() {
		$url           = 'https://example.com/audio.mp3?foo=1&bar=2';
		$received_args = array();

		add_filter(
			'wp_mediaelement_fallback',
			static function ( $output, $media_url ) use ( &$received_args ) {
				$received_args = array( $output, $media_url );
				return 'filtered-fallback';
			},
			10,
			2
		);

		$this->assertSame( 'filtered-fallback', wp_mediaelement_fallback( $url ) );
		$this->assertSame(
			array(
				'<a href="https://example.com/audio.mp3?foo=1&#038;bar=2">https://example.com/audio.mp3?foo=1&#038;bar=2</a>',
				$url,
			),
			$received_args
		);
	}
}
