<?php

/**
 * Tests for the print_emoji_detection_script function.
 *
 * @group formatting
 *
 * @covers ::print_emoji_detection_script
 */
class Tests_formatting_printEmojiDetectionScript extends WP_UnitTestCase {

	/**
	 * @ticket 60301
	 */
	public function test_print_emoji_detection_script() {
		$png_cdn = 'https://s.w.org/images/core/emoji/14.0.0/72x72/';

		self::touch( ABSPATH . WPINC . '/js/wp-emoji-loader.js' );
		$output = get_echo( 'print_emoji_detection_script' );

		$this->assertStringContainsString( wp_json_encode( $png_cdn ), $output );

		$this->assertEmpty( get_echo( 'print_emoji_detection_script' ) );
	}
}
