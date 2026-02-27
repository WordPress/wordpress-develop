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
	 * is called directly on the TEXTAREA token.
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
	 * Ensures that `set_modifiable_text()` returns false for elements that share
	 * tag names with HTML atomic elements but are in non-HTML namespaces.
	 *
	 * @ticket 64751
	 * @dataProvider data_set_modifiable_text_fails_for_non_html_namespace_elements
	 */
	public function test_set_modifiable_text_fails_for_non_html_namespace_elements(
		string $html,
		string $expected_namespace,
		string $expected_tag
	) {
		$processor = WP_HTML_Processor::create_fragment( $html );
		while ( $processor->next_tag() && $expected_tag !== $processor->get_tag() ) {
			continue;
		}
		$this->assertSame( $expected_tag, $processor->get_tag(), 'Failed to find target tag.' );
		$this->assertSame( $expected_namespace, $processor->get_namespace(), 'Unexpected namespace.' );
		$this->assertFalse(
			$processor->set_modifiable_text( 'test' ),
			"set_modifiable_text() should return false for {$expected_namespace}:{$expected_tag}."
		);
		$this->assertSame(
			$html,
			$processor->get_updated_html(),
			'HTML should be unchanged after rejected set_modifiable_text().'
		);
	}

	/**
	 * Data provider.
	 */
	public static function data_set_modifiable_text_fails_for_non_html_namespace_elements(): array {
		return array(
			'SVG TEXTAREA' => array( '<svg><textarea></textarea></svg>', 'svg', 'TEXTAREA' ),
			'SVG TITLE'    => array( '<svg><title></title></svg>', 'svg', 'TITLE' ),
			'SVG STYLE'    => array( '<svg><style></style></svg>', 'svg', 'STYLE' ),
			'SVG SCRIPT'   => array( '<svg><script></script></svg>', 'svg', 'SCRIPT' ),
		);
	}
}
