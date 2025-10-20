<?php

/**
 * @group oembed
 */
class Tests_Filter_oEmbed_Result extends WP_UnitTestCase {
	public function test_filter_oembed_result_trusted_malicious_iframe() {
		$html = '<p></p><iframe onload="alert(1)"></iframe>';

		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), 'https://www.youtube.com/watch?v=72xdCU__XCk' );

		$this->assertEqualHTML( $html, $actual );
	}

	public function test_filter_oembed_result_with_untrusted_provider() {
		$html   = '<p></p><iframe onload="alert(1)" src="http://example.com/sample-page/"></iframe>';
		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), 'http://example.com/sample-page/' );

		$processor = new WP_HTML_Tag_Processor( $actual );

		$this->assertTrue(
			$processor->next_tag( 'IFRAME' ),
			'Failed to find expected IFRAME element in filtered output.'
		);

		$src = $processor->get_attribute( 'src' );
		$this->assertIsString(
			$src,
			isset( $src )
				? 'Expected "src" attribute on IFRAME with string value but found boolean attribute instead.'
				: 'Failed to find expected "src" attribute on IFRAME element.'
		);

		$query_string = parse_url( $src, PHP_URL_FRAGMENT );
		$this->assertStringStartsWith(
			'?',
			$query_string,
			'Should have found URL fragment in "src" attribute resembling a query string.'
		);

		$query_string = substr( $query_string, 1 );
		$query_args   = array();
		parse_str( $query_string, $query_args );

		$this->assertArrayHasKey(
			'secret',
			$query_args,
			'Failed to find expected query arg "secret" in IFRAME "src" attribute.'
		);

		$this->assertSame(
			$query_args['secret'],
			$processor->get_attribute( 'data-secret' ),
			'Expected to find identical copy of secret from IFRAME "src" in the "data-secret" attribute.'
		);
	}

	public function test_filter_oembed_result_only_one_iframe_is_allowed() {
		$html   = '<div><iframe></iframe><iframe></iframe><p></p></div>';
		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), '' );

		$this->assertEqualHTML( '<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted"></iframe>', $actual );
	}

	public function test_filter_oembed_result_with_newlines() {
		$html = <<<EOD
<script>var = 1;</script>
<iframe></iframe>
<iframe></iframe>
<p></p>
EOD;

		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), '' );

		$this->assertEqualHTML( '<iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted"></iframe>', $actual );
	}

	public function test_filter_oembed_result_without_iframe() {
		$html   = '<span>Hello</span><p>World</p>';
		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), '' );

		$this->assertFalse( $actual );

		$html   = '<div><p></p></div><script></script>';
		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), '' );

		$this->assertFalse( $actual );
	}

	public function test_filter_oembed_result_secret_param_available() {
		$html   = '<iframe src="https://wordpress.org"></iframe>';
		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), '' );

		$processor = new WP_HTML_Tag_Processor( $actual );

		$this->assertTrue(
			$processor->next_tag( 'IFRAME' ),
			'Failed to find expected IFRAME element in filtered output.'
		);

		$src = $processor->get_attribute( 'src' );
		$this->assertMatchesRegularExpression(
			'~^https://wordpress.org~',
			$src,
			'Failed to find expected "src" attribute on IFRAME element.'
		);

		$query_string = parse_url( $src, PHP_URL_FRAGMENT );
		$this->assertStringStartsWith(
			'?',
			$query_string,
			'Should have found URL fragment in "src" attribute resembling a query string.'
		);

		$query_string = substr( $query_string, 1 );
		$query_args   = array();
		parse_str( $query_string, $query_args );

		$this->assertArrayHasKey(
			'secret',
			$query_args,
			'Failed to find expected query arg "secret" in IFRAME "src" attribute.'
		);

		$this->assertSame(
			$query_args['secret'],
			$processor->get_attribute( 'data-secret' ),
			'Expected to find identical copy of secret from IFRAME "src" in the "data-secret" attribute.'
		);
	}

	public function test_filter_oembed_result_wrong_type_provided() {
		$actual = wp_filter_oembed_result( 'some string', (object) array( 'type' => 'link' ), '' );

		$this->assertEqualHTML( 'some string', $actual );
	}

	public function test_filter_oembed_result_invalid_result() {
		$this->assertFalse( wp_filter_oembed_result( false, (object) array( 'type' => 'rich' ), '' ) );
		$this->assertFalse( wp_filter_oembed_result( '', (object) array( 'type' => 'rich' ), '' ) );
	}

	public function test_filter_oembed_result_blockquote_adds_style_to_iframe() {
		$html   = '<blockquote></blockquote><iframe></iframe>';
		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), '' );

		$this->assertEqualHTML( '<blockquote class="wp-embedded-content"></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;"></iframe>', $actual );
	}

	public function test_filter_oembed_result_allowed_html() {
		$html   = '<blockquote class="foo" id="bar"><strong><a href="" target=""></a></strong></blockquote><iframe></iframe>';
		$actual = wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), '' );

		$this->assertEqualHTML( '<blockquote class="wp-embedded-content"><a href=""></a></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;"></iframe>', $actual );
	}

	public function data_wp_filter_pre_oembed_custom_result() {
		return array(
			array(
				'<blockquote></blockquote><iframe title=""></iframe>',
				'<blockquote class="wp-embedded-content"></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;" title="Hola"></iframe>',
			),
			array(
				'<blockquote class="foo" id="bar"><strong><a href="" target=""></a></strong></blockquote><iframe width=123></iframe>',
				'<blockquote class="wp-embedded-content"><a href=""></a></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;" title="Hola" width="123"></iframe>',
			),
			array(
				'<blockquote><iframe width="100"></iframe></blockquote><iframe stitle="aaaa"></iframe>',
				'<blockquote class="wp-embedded-content"><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;" title="Hola" width="100"></iframe></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;" title="Hola"></iframe>',
			),
			array(
				"<blockquote><iframe title=' width=\"'></iframe></blockquote><iframe title='' height=' title=' width=\"'' height='123'\"></iframe>",
				'<blockquote class="wp-embedded-content"><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;" title=" width=&quot;"></iframe></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" style="position: absolute; visibility: hidden;" title=" width=&quot;" height=\' title=\' width="\'\' height=\'123\'"></iframe>',
			),
		);
	}

	/**
	 * @dataProvider data_wp_filter_pre_oembed_custom_result
	 */
	public function test_wp_filter_pre_oembed_custom_result( $html, $expected ) {
		$data   = (object) array(
			'type'  => 'rich',
			'title' => 'Hola',
			'html'  => $html,
		);
		$actual = _wp_oembed_get_object()->data2html( $data, 'https://untrusted.localhost' );
		$this->assertEqualHTML( $expected, $actual );
	}

	/**
	 * @group feed
	 */
	public function test_filter_feed_content() {
		$html   = '<blockquote></blockquote><iframe></iframe>';
		$actual = _oembed_filter_feed_content( wp_filter_oembed_result( $html, (object) array( 'type' => 'rich' ), '' ) );

		$this->assertEqualHTML( '<blockquote class="wp-embedded-content"></blockquote><iframe class="wp-embedded-content" sandbox="allow-scripts" security="restricted" ></iframe>', $actual );
	}
}
