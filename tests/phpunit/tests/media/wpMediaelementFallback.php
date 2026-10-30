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
		$url = 'https://example.com/audio.mp3?"><script>alert(1)</script>';

		$this->assertSame(
			'<a href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>',
			wp_mediaelement_fallback( $url )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_can_modify_output() {
		add_filter(
			'wp_mediaelement_fallback',
			static function () {
				return 'filtered-fallback';
			}
		);

		$this->assertSame(
			'filtered-fallback',
			wp_mediaelement_fallback( 'https://example.com/audio.mp3' )
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_filter_receives_generated_output_and_url() {
		$url            = 'https://example.com/audio.mp3';
		$received_args  = array();
		$default_output = sprintf( '<a href="%1$s">%1$s</a>', esc_url( $url ) );

		add_filter(
			'wp_mediaelement_fallback',
			static function ( $output, $media_url ) use ( &$received_args ) {
				$received_args = array( $output, $media_url );
				return $output;
			},
			10,
			2
		);

		wp_mediaelement_fallback( $url );

		$this->assertSame( array( $default_output, $url ), $received_args );
	}
}
