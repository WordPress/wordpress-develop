<?php

/**
 * Tests for the print_emoji_detection_script function.
 *
 * @group formatting
 *
 * @covers ::wp_enqueue_emoji_styles
 */
class Tests_formatting_wpEnqueueEmojiEtyles extends WP_UnitTestCase {

	/**
	 * @ticket 60301
	 */
	public function test_wp_enqueue_emoji_styles() {

		$this->assertSame( 10, has_action( 'wp_print_styles', 'print_emoji_styles' ) );

		wp_enqueue_emoji_styles();

		$this->assertFalse( has_action( 'wp_print_styles', 'print_emoji_styles' ) );

		$styles = get_echo( 'wp_print_styles' );
		$this->assertStringContainsString( 'wp-emoji-styles-inline-css', $styles );
		$this->assertStringContainsString( 'img.wp-smiley, img.emoji', $styles );
	}
}
