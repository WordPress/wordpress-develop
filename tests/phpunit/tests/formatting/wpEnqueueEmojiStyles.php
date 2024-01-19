<?php

/**
 * Tests for the wp_enqueue_emoji_styles function.
 *
 * @group formatting
 *
 * @covers ::wp_enqueue_emoji_styles
 */
class Tests_formatting_wpEnqueueEmojiStyles extends WP_UnitTestCase{

	/**
	 * @ticket 60306
	 */
	public function test_wp_enqueue_emoji_styles() {
		wp_enqueue_emoji_styles();
		$this->assertStringContainsString( 'img.wp-smiley, img.emoji', get_echo( 'wp_print_styles' ) );
	}

	/**
	 * @ticket 60306
	 */
	public function test_wp_enqueue_emoji_styles_done() {
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		wp_enqueue_emoji_styles();
		$this->assertStringNotContainsString( 'img.wp-smiley, img.emoji', get_echo( 'wp_print_styles' ) );
	}
}
