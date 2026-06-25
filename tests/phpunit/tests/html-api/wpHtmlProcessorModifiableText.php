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
	public function test_modifiable_text_special_textarea( string $set_text, string $expected_html ): void {
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
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_modifiable_text_special_textarea(): array {
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
	 * Ensures that text node updates preserve a leading newline where the HTML parser
	 * ignores one newline after the opening tag.
	 *
	 * @ticket 64609
	 *
	 * @dataProvider data_modifiable_text_ignores_leading_newline_text_nodes
	 *
	 * @param string $tag_name      Expected tag name.
	 * @param string $set_text      Text to set.
	 * @param string $expected_html Expected HTML output.
	 */
	public function test_modifiable_text_preserves_ignored_leading_newline_in_text_nodes( string $tag_name, string $set_text, string $expected_html ): void {
		$html      = "<{$tag_name}>initial</{$tag_name}>";
		$processor = WP_HTML_Processor::create_fragment( $html );
		$this->assertNotNull( $processor, 'Failed to create a processor.' );
		$this->assertTrue( $processor->next_tag( $tag_name ), 'Failed to find target tag.' );
		$this->assertTrue( $processor->next_token(), 'Failed to find text node.' );
		$this->assertSame( '#text', $processor->get_token_name(), 'Should have found a text node.' );

		$this->assertTrue( $processor->set_modifiable_text( $set_text ) );
		$expected_text = strtr(
			$set_text,
			array(
				"\r\n" => "\n",
				"\r"   => "\n",
			)
		);
		$this->assertSame(
			$expected_text,
			$processor->get_modifiable_text(),
			'Should have preserved the leading newline when reading the updated text.'
		);

		$updated_html = $processor->get_updated_html();
		$this->assertEqualHTML(
			$expected_html,
			$updated_html,
			'<body>',
			'Should have preserved the leading newline in the serialized HTML.'
		);

		$processor = WP_HTML_Processor::create_fragment( $updated_html );
		$this->assertNotNull( $processor, 'Failed to reprocess updated HTML.' );
		$this->assertTrue( $processor->next_tag( $tag_name ), 'Failed to find target tag after update.' );
		$roundtrip_text = '';
		while ( $processor->next_token() && '#text' === $processor->get_token_name() ) {
			$roundtrip_text .= $processor->get_modifiable_text();
		}
		$this->assertSame(
			$expected_text,
			$roundtrip_text,
			'Should have preserved the leading newline after reprocessing the updated HTML.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function data_modifiable_text_ignores_leading_newline_text_nodes(): array {
		return array(
			'PRE leading newline'                       => array(
				'PRE',
				"\nAFTER NEWLINE",
				"<pre>\n\nAFTER NEWLINE</pre>",
			),
			'PRE leading carriage return'               => array(
				'PRE',
				"\rAFTER CR",
				"<pre>\n\nAFTER CR</pre>",
			),
			'PRE leading carriage return + newline'     => array(
				'PRE',
				"\r\nAFTER CRLF",
				"<pre>\n\nAFTER CRLF</pre>",
			),
			'LISTING leading newline'                   => array(
				'LISTING',
				"\nAFTER NEWLINE",
				"<listing>\n\nAFTER NEWLINE</listing>",
			),
			'LISTING leading carriage return'           => array(
				'LISTING',
				"\rAFTER CR",
				"<listing>\n\nAFTER CR</listing>",
			),
			'LISTING leading carriage return + newline' => array(
				'LISTING',
				"\r\nAFTER CRLF",
				"<listing>\n\nAFTER CRLF</listing>",
			),
		);
	}

	/**
	 * Ensures that `set_modifiable_text()` returns false for elements that are not special "atomic" elements.
	 *
	 * This includes atomic-like foreign elements (`<svg><textarea>`) as well as arbitrary HTML
	 * elements (`<div>`).
	 *
	 * @ticket 64751
	 * @dataProvider data_set_modifiable_fails_non_atomic_tags
	 */
	public function test_set_modifiable_fails_non_atomic_tags(
		string $html,
		string $target_tag
	): void {
		$processor = WP_HTML_Processor::create_fragment( $html );
		$this->assertNotNull( $processor, 'Failed to create a processor.' );
		$this->assertTrue( $processor->next_tag( $target_tag ), 'Failed to find target tag.' );
		$this->assertFalse(
			$processor->set_modifiable_text( 'test' ),
			"set_modifiable_text() should return false on {$processor->get_namespace()}:{$processor->get_qualified_tag_name()}."
		);
		$this->assertSame(
			$html,
			$processor->get_updated_html(),
			'HTML should be unchanged after rejected set_modifiable_text().'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function data_set_modifiable_fails_non_atomic_tags(): array {
		return array(
			// Plain HTML tags.
			'html DIV'                   => array( '<div>', 'DIV' ),

			// Foreign elements with non-atomic tags.
			'svg PATH'                   => array( '<svg><path></path></svg>', 'PATH' ),
			'svg PATH (self-closing)'    => array( '<svg><path /></svg>', 'PATH' ),
			'math MTEXT'                 => array( '<math><mtext></mtext></math>', 'MTEXT' ),
			'math MSPACE (self-closing)' => array( '<math><mspace /></math>', 'MSPACE' ),

			// Foreign elements with atomic-like tags.
			'svg TEXTAREA'               => array( '<svg><textarea></textarea></svg>', 'TEXTAREA' ),
			'svg TITLE'                  => array( '<svg><title></title></svg>', 'TITLE' ),
			'svg STYLE'                  => array( '<svg><style></style></svg>', 'STYLE' ),
			'svg SCRIPT'                 => array( '<svg><script></script></svg>', 'SCRIPT' ),
			'math TEXTAREA'              => array( '<math><textarea></textarea></math>', 'TEXTAREA' ),
			'math TITLE'                 => array( '<math><title></title></math>', 'TITLE' ),
			'math STYLE'                 => array( '<math><style></style></math>', 'STYLE' ),
			'math SCRIPT'                => array( '<math><script></script></math>', 'SCRIPT' ),
		);
	}
}
