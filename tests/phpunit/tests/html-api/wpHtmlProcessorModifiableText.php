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
	 * Setting the modifiable text with a leading newline should ensure that the leading newline
	 * is present in the resulting TEXTAREA.
	 *
	 * @ticket 64609
	 */
	public function test_modifiable_text_special_textarea() {
		$processor = WP_HTML_Processor::create_fragment( '<textarea></textarea>' );
		$processor->next_token();
		$processor->set_modifiable_text( "\nAFTER NEWLINE" );
		$this->assertSame(
			"\nAFTER NEWLINE",
			$processor->get_modifiable_text(),
			'Should have preserved the leading newline in the TEXTAREA content.'
		);
	}

	/**
	 * TEXTAREA elements ignore the first newline in their content.
	 * Setting the modifiable text with a leading carriage return should be normalized
	 * and ensure the leading newline is present in the resulting TEXTAREA.
	 *
	 * @ticket 64609
	 */
	public function test_modifiable_text_special_textarea_carriage_return() {
		$processor = WP_HTML_Processor::create_fragment( '<textarea></textarea>' );
		$processor->next_token();
		$processor->set_modifiable_text( "\rCR" );
		// Newline normalization transforms \r into \n, and special handling should preserve it.
		$this->assertSame(
			"\nCR",
			$processor->get_modifiable_text(),
			'Should have normalized carriage return and preserved the leading newline in the TEXTAREA content.'
		);
		$this->assertEqualHTML(
			"<textarea>\n\nCR</textarea>",
			$processor->get_updated_html(),
			'<body>',
			'Should have doubled the newline in the output HTML to preserve the leading newline.'
		);
	}

	/**
	 * TEXTAREA elements ignore the first newline in their content.
	 * Setting the modifiable text with a leading carriage return + newline should be normalized
	 * and ensure the leading newline is present in the resulting TEXTAREA.
	 *
	 * @ticket 64609
	 */
	public function test_modifiable_text_special_textarea_carriage_return_newline() {
		$processor = WP_HTML_Processor::create_fragment( '<textarea></textarea>' );
		$processor->next_token();
		$processor->set_modifiable_text( "\r\nCR-N" );
		// Newline normalization transforms \r\n into \n, and special handling should preserve it.
		$this->assertSame(
			"\nCR-N",
			$processor->get_modifiable_text(),
			'Should have normalized carriage return + newline and preserved the leading newline in the TEXTAREA content.'
		);
		$this->assertEqualHTML(
			"<textarea>\n\nCR-N</textarea>",
			$processor->get_updated_html(),
			'<body>',
			'Should have doubled the newline in the output HTML to preserve the leading newline.'
		);
	}

	/**
	 * PRE and LISTING elements ignore the first newline in their content.
	 * Setting the modifiable text with a leading newline should ensure that the leading newline
	 * is present in the resulting element.
	 *
	 * @ticket 64609
	 *
	 * @dataProvider data_modifiable_text_special_pre_tags
	 *
	 * @param string $tag_name The tag name to test (e.g. 'pre', 'listing').
	 */
	public function test_modifiable_text_special_pre_tags( string $tag_name ) {
		$set_text  = "\nAFTER NEWLINE";
		$processor = WP_HTML_Processor::create_fragment( "<{$tag_name}>REPLACEME<!--x--></{$tag_name}>" );
		$processor->next_tag();
		$processor->next_token();
		$this->assertSame( '#text', $processor->get_token_type() );
		$processor->set_modifiable_text( $set_text );
		$this->assertSame( $set_text, $processor->get_modifiable_text() );
		$this->assertEqualHTML(
			<<<HTML
			<{$tag_name}>
			{$set_text}<!--x--></{$tag_name}>
			HTML,
			$processor->get_updated_html(),
			'<body>',
			"Should have preserved the leading newline in the {$tag_name} content."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_modifiable_text_special_pre_tags() {
		return array(
			'PRE'     => array( 'pre' ),
			'LISTING' => array( 'listing' ),
		);
	}

	/**
	 * The HTML Processor has special behavior when a text node starts with whitespace.
	 * Test that PRE and LISTING `::set_modifiable_text()` handling works correctly
	 * with leading whitespace.
	 *
	 * PRE and LISTING elements ignore the first newline in their content.
	 * Leading whitespace may split into multiple text nodes in the HTML Processor.
	 * Setting the modifiable text with a leading newline should ensure that the
	 * leading newline is present in the resulting element.
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
		$set_text = "\nAFTER NEWLINE.";

		foreach ( self::data_modifiable_text_special_pre_tags() as $tag_data ) {
			$tag_name  = $tag_data[0];
			$tag_label = strtoupper( $tag_name );

			yield "{$tag_label} with leading newline, first text node" => array(
				"<{$tag_name}>\nREPLACEME<!--x--></{$tag_name}>",
				1,
				'',
				$set_text,
				"<{$tag_name}>\n{$set_text}REPLACEME<!--x--></{$tag_name}>",
			);

			yield "{$tag_label} with leading newline, second text node" => array(
				"<{$tag_name}>\nREPLACEME<!--x--></{$tag_name}>",
				2,
				'REPLACEME',
				$set_text,
				"<{$tag_name}>\n{$set_text}<!--x--></{$tag_name}>",
			);

			yield "{$tag_label} with leading space, first text node" => array(
				"<{$tag_name}> REPLACEME<!--x--></{$tag_name}>",
				1,
				' ',
				$set_text,
				"<{$tag_name}>\n{$set_text}REPLACEME<!--x--></{$tag_name}>",
			);

			yield "{$tag_label} with leading space, second text node" => array(
				"<{$tag_name}> REPLACEME<!--x--></{$tag_name}>",
				2,
				'REPLACEME',
				$set_text,
				"<{$tag_name}>\n {$set_text}<!--x--></{$tag_name}>",
			);

			yield "{$tag_label} insert with leading carriage return" => array(
				"<{$tag_name}>REPLACEME<!--x--></{$tag_name}>",
				1,
				'REPLACEME',
				"\rCR",
				"<{$tag_name}>\n\nCR<!--x--></{$tag_name}>",
			);

			yield "{$tag_label} insert with leading carriage return + newline" => array(
				"<{$tag_name}>REPLACEME<!--x--></{$tag_name}>",
				1,
				'REPLACEME',
				"\r\nCR-N",
				"<{$tag_name}>\n\nCR-N<!--x--></{$tag_name}>",
			);

			yield "{$tag_label} clear text" => array(
				"<{$tag_name}>REPLACEME<!--x--></{$tag_name}>",
				1,
				'REPLACEME',
				'',
				"<{$tag_name}><!--x--></{$tag_name}>",
			);
		}
	}
}
