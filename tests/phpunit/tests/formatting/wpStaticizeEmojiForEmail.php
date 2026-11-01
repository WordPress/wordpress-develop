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
	public function test_wp_staticize_emoji_for_email( $email, $expected, $filtered_content_type = null ) {
		add_filter(
			'emoji_url',
			static function () {
				return 'https://example.org/emoji/';
			}
		);

		if ( null !== $filtered_content_type ) {
			add_filter(
				'wp_mail_content_type',
				static function () use ( $filtered_content_type ) {
					return $filtered_content_type;
				}
			);
		}

		$this->assertSame( $expected, wp_staticize_emoji_for_email( $email ) );
	}

	public static function data_wp_staticize_emoji_for_email() {
		$png_cdn = 'https://example.org/emoji/';

		return array(
			'emoji with default plain text'   => array(
				'email'    => array( 'message' => 'message 🙂' ),
				'expected' => array( 'message' => 'message 🙂' ),
			),
			'simply with emoji as plain text' => array(
				'email'    => array(
					'message' => 'message 🙂',
					'headers' => 'content-type:text/plain',
				),
				'expected' => array(
					'message' => 'message 🙂',
					'headers' => 'content-type:text/plain',
				),
			),
			'simply with emoji with html'     => array(
				'email'    => array(
					'message' => 'message 🙂',
					'headers' => 'content-type:text/html',
				),
				'expected' => array(
					'message' => 'message <img src="' . $png_cdn . '1f642.png" alt="🙂" class="wp-smiley" style="height: 1em; max-height: 1em;" />',
					'headers' => 'content-type:text/html',
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
			'filtered to html'                => array(
				'email'                 => array( 'message' => 'message 🙂' ),
				'expected'              => array( 'message' => 'message <img src="' . $png_cdn . '1f642.png" alt="🙂" class="wp-smiley" style="height: 1em; max-height: 1em;" />' ),
				'filtered_content_type' => 'text/html',
			),
			'filtered to plain text'          => array(
				'email'                 => array(
					'message' => 'message 🙂',
					'headers' => 'content-type:text/html',
				),
				'expected'              => array(
					'message' => 'message 🙂',
					'headers' => 'content-type:text/html',
				),
				'filtered_content_type' => 'text/plain',
			),
		);
	}
}
