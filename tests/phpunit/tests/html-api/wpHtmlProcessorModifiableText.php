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
	 * @ticket 64607
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
	 * PRE elements ignore the first newline in their content.
	 * Setting the modifiable text with a leading newline should ensure that the leading newline
	 * is present in the resulting TEXTAREA.
	 *
	 * @ticket 64607
	 */
	public function test_modifiable_text_special_pre() {
		$set_text  = "\nAFTER NEWLINE";
		$processor = WP_HTML_Processor::create_fragment( '<pre>REPLACEME<!--x--></pre>' );
		$processor->next_tag();
		$processor->next_token();
		$this->assertSame( '#text', $processor->get_token_type() );
		$processor->set_modifiable_text( $set_text );
		$this->assertSame( $set_text, $processor->get_modifiable_text() );
		$this->assertEqualHTML(
			<<<HTML
			<pre>
			{$set_text}<!--x--></pre>
			HTML,
			$processor->get_updated_html(),
			'<body>',
			'Should have preserved the leading newline in the TEXTAREA content.'
		);
	}

	/**
	 *
	 * @ticket 64607
	 */
	public function test_modifiable_text_special_pre_leading_whitespace() {
		$set_text  = "\nAFTER NEWLINE.";
		$processor = WP_HTML_Processor::create_fragment( "<pre>\nREPLACEME<!--x--></pre>" );
		$processor->next_tag();
		$processor->next_token();
		$this->assertSame( '#text', $processor->get_token_type() );
		// This is an empty text node because of how the HTML Processor works.
		$this->assertSame( '', $processor->get_modifiable_text() );
		$processor->set_modifiable_text( $set_text );
		$this->assertSame( $set_text, $processor->get_modifiable_text() );
		$this->assertEqualHTML(
			<<<HTML
			<pre>
			{$set_text}REPLACEME<!--x--></pre>
			HTML,
			$processor->get_updated_html(),
			'<body>',
			'Should have preserved the leading newline in the TEXTAREA content.'
		);

		$processor = WP_HTML_Processor::create_fragment( "<pre>\nREPLACEME<!--x--></pre>" );
		$processor->next_tag();
		$processor->next_token();
		$processor->next_token();
		$this->assertSame( '#text', $processor->get_token_type() );
		// This is an empty text node because of how the HTML Processor works.
		$this->assertSame( 'REPLACEME', $processor->get_modifiable_text() );
		$processor->set_modifiable_text( $set_text );
		$this->assertSame( $set_text, $processor->get_modifiable_text() );
		$this->assertEqualHTML(
			<<<HTML
			<pre>
			{$set_text}<!--x--></pre>
			HTML,
			$processor->get_updated_html(),
			'<body>',
			'Should have preserved the leading newline in the TEXTAREA content.'
		);

		$processor = WP_HTML_Processor::create_fragment( '<pre> REPLACEME<!--x--></pre>' );
		$processor->next_tag();
		$processor->next_token();
		$this->assertSame( '#text', $processor->get_token_type() );
		// This is an empty text node because of how the HTML Processor works.
		$this->assertSame( ' ', $processor->get_modifiable_text() );
		$processor->set_modifiable_text( $set_text );
		$this->assertSame( $set_text, $processor->get_modifiable_text() );
		$this->assertEqualHTML(
			<<<HTML
			<pre>
			{$set_text}REPLACEME<!--x--></pre>
			HTML,
			$processor->get_updated_html(),
			'<body>',
			'Should have preserved the leading newline in the TEXTAREA content.'
		);

		$processor = WP_HTML_Processor::create_fragment( '<pre> REPLACEME<!--x--></pre>' );
		$processor->next_tag();
		$processor->next_token();
		$processor->next_token();
		$this->assertSame( '#text', $processor->get_token_type() );
		// This is an empty text node because of how the HTML Processor works.
		$this->assertSame( 'REPLACEME', $processor->get_modifiable_text() );
		$processor->set_modifiable_text( $set_text );
		$this->assertSame( $set_text, $processor->get_modifiable_text() );
		$this->assertEqualHTML(
			<<<HTML
			<pre>
			 {$set_text}<!--x--></pre>
			HTML,
			$processor->get_updated_html(),
			'<body>',
			'Should have preserved the leading newline in the TEXTAREA content.'
		);
	}

	/**
	 * LISTING elements ignore the first newline in their content.
	 * Setting the modifiable text with a leading newline should ensure that the leading newline
	 * is present in the resulting TEXTAREA.
	 *
	 * @todo Leading whitespace mage split into multiple text nodes. Add appropriate tests.
	 *
	 * @ticket 64607
	 */
	public function test_modifiable_text_special_listing() {
		$set_text  = "\nAFTER NEWLINE";
		$processor = WP_HTML_Processor::create_fragment( '<listing>REPLACEME<!--x--></listing>' );
		$processor->next_tag();
		$processor->next_token();
		$this->assertSame( '#text', $processor->get_token_type() );
		$processor->set_modifiable_text( $set_text );
		$this->assertSame( $set_text, $processor->get_modifiable_text() );
		$this->assertEqualHTML(
			<<<HTML
			<listing>
			{$set_text}<!--x--></listing>
			HTML,
			$processor->get_updated_html(),
			'<body>',
			'Should have preserved the leading newline in the TEXTAREA content.'
		);
	}
}
