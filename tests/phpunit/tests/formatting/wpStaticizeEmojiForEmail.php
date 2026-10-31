<?php

/**
 * Tests for the wp_staticize_emoji_for_email function.
 *
 * @group formatting
 *
 * @covers ::wp_staticize_emoji_for_email
 */
class Tests_formating_wpStaticizeEmojiForEmail extends WP_UnitTestCase {

	/**
	 * @ticket 60300
	 *
	 * @dataProvider data_wp_staticize_emoji_for_email
	 */
	public function test_wp_staticize_emoji_for_email( $email, $expected ) {

		$this->assertSame( $expected, wp_staticize_emoji_for_email( $email ) );
	}

	public function data_wp_staticize_emoji_for_email() {
		$png_cdn = 'https://s.w.org/images/core/emoji/14.0.0/72x72/';

		return array(
			'empty array'                          => array(
				'email'    => array(),
				'expected' => array(),
			),
			'no headers'                           => array(
				'email'    => array( 'message' => 'message' ),
				'expected' => array( 'message' => 'message' ),
			),
			'no message'                           => array(
				'email'    => array( 'headers' => 'headers' ),
				'expected' => array( 'headers' => 'headers' ),
			),
			'simple'                               => array(
				'email'    => array(
					'message' => 'message',
					'headers' => 'headers',
				),
				'expected' => array(
					'message' => 'message',
					'headers' => 'headers',
				),
			),
			'simply with emoji'                    => array(
				'email'    => array(
					'message' => 'message 🙂',
					'headers' => 'headers',
				),
				'expected' => array(
					'message' => 'message 🙂',
					'headers' => 'headers',
				),
			),
			'simply with emoji as plain text'      => array(
				'email'    => array(
					'message' => 'message 🙂',
					'headers' => 'content-type:text/plain',
				),
				'expected' => array(
					'message' => 'message 🙂',
					'headers' => 'content-type:text/plain',
				),
			),
			'simply with emoji with html'          => array(
				'email'    => array(
					'message' => 'message 🙂',
					'headers' => 'content-type:text/html',
				),
				'expected' => array(
					'message' => 'message <img src="' . $png_cdn . '1f642.png" alt="🙂" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
					'headers' => 'content-type:text/html',
				),
			),
			'simply with emoji with headers array' => array(
				'email'    => array(
					'message' => 'message 🙂',
					'headers' => array(
						'content-type:text/html',
						'header:value',
					),
				),
				'expected' => array(
					'message' => 'message <img src="' . $png_cdn . '1f642.png" alt="🙂" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
					'headers' => array(
						'content-type:text/html',
						'header:value',
					),
				),
			),
			'simply with emoji with headers array with charset' => array(
				'email'    => array(
					'message' => 'message 🙂',
					'headers' => array(
						'content-type:text/html; charset=utf-8',
						'header:value',
					),
				),
				'expected' => array(
					'message' => 'message <img src="' . $png_cdn . '1f642.png" alt="🙂" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
					'headers' => array(
						'content-type:text/html; charset=utf-8',
						'header:value',
					),
				),
			),
		);
	}
}
