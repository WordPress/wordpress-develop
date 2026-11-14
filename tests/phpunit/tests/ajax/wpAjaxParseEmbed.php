<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Tests for the parse-embed Ajax action.
 *
 * @group ajax
 *
 * @covers ::wp_ajax_parse_embed
 */
class Tests_Ajax_wpAjaxParseEmbed extends WP_Ajax_UnitTestCase {

	/**
	 * The media sandbox stylesheets are printed with the style link tag API, so their attributes can be filtered,
	 * and their URLs are escaped.
	 *
	 * @ticket 66342
	 */
	public function test_sandbox_stylesheets_receive_filtered_attributes() {
		$this->_setRole( 'editor' );

		$expected_hrefs = array();
		foreach ( wpview_media_sandbox_styles() as $style ) {
			$expected_hrefs[] = $style . '&onload=alert(1)';
		}

		// A quote in a URL is removed by esc_url(), so it cannot end the `href` attribute.
		add_filter(
			'includes_url',
			static function ( string $url ): string {
				return $url . '&"onload="alert(1)';
			}
		);
		add_filter(
			'wp_style_attributes',
			static function ( array $attributes ): array {
				$attributes['nonce'] = 'sandbox-style';
				return $attributes;
			}
		);

		// An audio file is embedded by a handler registered by Core, so no HTTP request is made.
		$_POST = array(
			'action'    => 'parse-embed',
			'post_ID'   => 0,
			'shortcode' => '[embed]https://example.com/audio.mp3[/embed]',
		);

		try {
			$this->_handleAjax( 'parse-embed' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );

		$hrefs     = array();
		$processor = new WP_HTML_Tag_Processor( $response['data']['body'] );
		while ( $processor->next_tag( 'LINK' ) ) {
			$this->assertSame( 'stylesheet', $processor->get_attribute( 'rel' ) );
			$this->assertSame( 'sandbox-style', $processor->get_attribute( 'nonce' ) );
			$this->assertNull( $processor->get_attribute( 'onload' ), 'A quote in the URL should not end the href attribute.' );
			$hrefs[] = $processor->get_attribute( 'href' );
		}

		$this->assertSame( $expected_hrefs, $hrefs );
	}
}
