<?php
/**
 * Unit tests covering WP_HTML_Processor modifiable text functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Processor
 */
class Tests_HtmlApi_WpHtmlProcessorModifiableText extends WP_UnitTestCase {
	/**
	 * TEXTAREA elements ignore the first newline in their content.
	 * Setting the modifiable text with a leading newline (or carriage return variants)
	 * should ensure that the leading newline is present in the resulting TEXTAREA.
	 *
	 * TEXTAREA are treated as atomic tags by the tag processor, so `set_modifiable_text()`
	 * is called directly on the TEXTAREA token, making them different from PRE and LISTING
	 * tags that also have special newline handling in HTML.
	 *
	 * @ticket 64609
	 *
	 * @dataProvider data_modifiable_text_special_textarea
	 *
	 * @param string $set_text         Text to set.
	 * @param string $expected_html    Expected HTML output.
	 */
	public function test_modifiable_text_special_textarea( string $set_text, string $expected_html ) {
		$processor = WP_HTML_Processor::create_fragment( '<textarea></textarea>' );
		$processor->next_token();
		$processor->set_modifiable_text( $set_text );
		$this->assertSame(
			strtr(
				$set_text,
				array(
					"\r\n" => "\n",
					"\r"   => "\n",
				)
			),
			$processor->get_modifiable_text(),
			'Should have preserved or normalized the leading newline in the TEXTAREA content.'
		);
		$this->assertEqualHTML(
			$expected_html,
			$processor->get_updated_html(),
			'<body>',
			'Should have correctly output the TEXTAREA HTML.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_modifiable_text_special_textarea() {
		return array(
			'Leading newline'                   => array(
				"\nAFTER NEWLINE",
				"<textarea>\n\nAFTER NEWLINE</textarea>",
			),
			'Leading carriage return'           => array(
				"\rCR",
				"<textarea>\n\nCR</textarea>",
			),
			'Leading carriage return + newline' => array(
				"\r\nCR-N",
				"<textarea>\n\nCR-N</textarea>",
			),
		);
	}

	/**
	 * PRE and LISTING elements ignore the first newline in their content.
	 * Leading whitespace may split into multiple text nodes in the HTML Processor.
	 * Setting the modifiable text with a leading newline should ensure that the
	 * leading newline is present in the resulting element.
	 *
	 * The HTML Processor has special behavior when a text node starts with whitespace.
	 * Test that PRE and LISTING `::set_modifiable_text()` handling works correctly
	 * with leading whitespace.
	 *
	 * @ticket 64609
	 *
	 * @dataProvider data_modifiable_text_special_leading_whitespace
	 *
	 * @param string $html             HTML containing the element to test.
	 * @param int    $advance_n_tokens Count of times to run `next_token()` after `next_tag()`.
	 * @param string $stopped_on_text  Expected modifiable text before the update.
	 * @param string $set_text         Text to set.
	 * @param string $expected_html    Expected HTML output after setting modifiable text.
	 */
	public function test_modifiable_text_special_leading_whitespace(
		string $html,
		int $advance_n_tokens,
		string $stopped_on_text,
		string $set_text,
		string $expected_html
	) {
		$processor = WP_HTML_Processor::create_fragment( $html );
		$processor->next_tag();
		while ( --$advance_n_tokens >= 0 ) {
			$processor->next_token();
		}
		$this->assertSame( '#text', $processor->get_token_type() );
		$this->assertSame( $stopped_on_text, $processor->get_modifiable_text() );
		$processor->set_modifiable_text( $set_text );

		// Newline normalization transforms \r and \r\n into \n.
		$this->assertSame(
			strtr(
				$set_text,
				array(
					"\r\n" => "\n",
					"\r"   => "\n",
				)
			),
			$processor->get_modifiable_text()
		);
		$this->assertEqualHTML(
			$expected_html,
			$processor->get_updated_html(),
			'<body>',
			'Should have preserved the leading newline in the element content.'
		);
	}

	/**
	 * Data provider.
	 */
	public static function data_modifiable_text_special_leading_whitespace() {
		$tags = array( 'pre', 'listing' );
		foreach ( $tags as $tag_name ) {
			yield "<{$tag_name}> with no leading newline" => array(
				"<{$tag_name}>REPLACEME<!--x--></{$tag_name}>",
				1,
				'REPLACEME',
				"\nAFTER NEWLINE.",
				"<{$tag_name}>\n\nAFTER NEWLINE.<!--x--></{$tag_name}>",
			);

			yield "<{$tag_name}> with leading newline, first text node" => array(
				"<{$tag_name}>\nREPLACEME<!--x--></{$tag_name}>",
				1,
				'',
				"\nAFTER NEWLINE.",
				"<{$tag_name}>\n\nAFTER NEWLINE.REPLACEME<!--x--></{$tag_name}>",
			);

			yield "<{$tag_name}> with leading newline, second text node" => array(
				"<{$tag_name}>\nREPLACEME<!--x--></{$tag_name}>",
				2,
				'REPLACEME',
				"\nAFTER NEWLINE.",
				"<{$tag_name}>\n\nAFTER NEWLINE.<!--x--></{$tag_name}>",
			);

			yield "<{$tag_name}> with leading space, first text node" => array(
				"<{$tag_name}> REPLACEME<!--x--></{$tag_name}>",
				1,
				' ',
				"\nAFTER NEWLINE.",
				"<{$tag_name}>\n\nAFTER NEWLINE.REPLACEME<!--x--></{$tag_name}>",
			);

			yield "<{$tag_name}> with leading space, second text node" => array(
				"<{$tag_name}> REPLACEME<!--x--></{$tag_name}>",
				2,
				'REPLACEME',
				"\nAFTER NEWLINE.",
				"<{$tag_name}>\n \nAFTER NEWLINE.<!--x--></{$tag_name}>",
			);

			yield "<{$tag_name}> insert with leading carriage return" => array(
				"<{$tag_name}>REPLACEME<!--x--></{$tag_name}>",
				1,
				'REPLACEME',
				"\rCR",
				"<{$tag_name}>\n\nCR<!--x--></{$tag_name}>",
			);

			yield "<{$tag_name}> insert with leading carriage return + newline" => array(
				"<{$tag_name}>REPLACEME<!--x--></{$tag_name}>",
				1,
				'REPLACEME',
				"\r\nCR-N",
				"<{$tag_name}>\n\nCR-N<!--x--></{$tag_name}>",
			);

			yield "<{$tag_name}> clear text" => array(
				"<{$tag_name}>REPLACEME<!--x--></{$tag_name}>",
				1,
				'REPLACEME',
				'',
				"<{$tag_name}><!--x--></{$tag_name}>",
			);
		}
	}
}
