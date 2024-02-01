<?php
/**
 * Unit tests covering WP_HTML_Tag_Processor token-scanning functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.5.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Tag_Processor
 */
class Tests_HtmlApi_WpHtmlProcessor_Token_Scanning extends WP_UnitTestCase {
	/**
	 * Ensures that scanning finishes in a complete form when the document is empty.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_completes_empty_document() {
		$processor = new WP_HTML_Tag_Processor( '' );

		$this->assertFalse(
			$processor->next_token(),
			"Should not have found any tokens but found {$processor->get_token_type()}."
		);
	}

	/**
	 * Ensures that normative text nodes are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_text_node() {
		$processor = new WP_HTML_Tag_Processor( 'Hello, World!' );
		$processor->next_token();

		$this->assertSame(
			'#text',
			$processor->get_token_type(),
			"Should have found #text token type but found {$processor->get_token_type()} instead."
		);

		$this->assertSame(
			'Hello, World!',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative Elements are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_element() {
		$processor = new WP_HTML_Tag_Processor( '<div id="test" inert>Hello, World!</div>' );
		$processor->next_token();

		$this->assertSame(
			'DIV',
			$processor->get_token_name(),
			"Should have found DIV tag name but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			'test',
			$processor->get_attribute( 'id' ),
			"Should have found id attribute value 'test' but found {$processor->get_attribute( 'id' )} instead."
		);

		$this->assertTrue(
			$processor->get_attribute( 'inert' ),
			"Should have found boolean attribute 'inert' but didn't."
		);

		$attributes     = $processor->get_attribute_names_with_prefix( '' );
		$attribute_list = array_map( 'Tests_HtmlApi_WpHtmlProcessor_Token_Scanning::quoted', $attributes );
		$this->assertSame(
			array( 'id', 'inert' ),
			$attributes,
			'Should have found only two attributes but found ' . implode( ', ', $attribute_list ) . ' instead.'
		);

		$this->assertSame(
			'',
			$processor->get_modifiable_text(),
			"Should have found empty modifiable text but found '{$processor->get_modifiable_text()}' instead."
		);
	}

	/**
	 * Ensures that normative SCRIPT elements are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_script_element() {
		$processor = new WP_HTML_Tag_Processor( '<script type="module">console.log( "Hello, World!" );</script>' );
		$processor->next_token();

		$this->assertSame(
			'SCRIPT',
			$processor->get_token_name(),
			"Should have found SCRIPT tag name but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			'module',
			$processor->get_attribute( 'type' ),
			"Should have found type attribute value 'module' but found {$processor->get_attribute( 'type' )} instead."
		);

		$attributes     = $processor->get_attribute_names_with_prefix( '' );
		$attribute_list = array_map( 'Tests_HtmlApi_WpHtmlProcessor_Token_Scanning::quoted', $attributes );
		$this->assertSame(
			array( 'type' ),
			$attributes,
			"Should have found single 'type' attribute but found " . implode( ', ', $attribute_list ) . ' instead.'
		);

		$this->assertSame(
			'console.log( "Hello, World!" );',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative TEXTAREA elements are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_textarea_element() {
		$processor = new WP_HTML_Tag_Processor(
			<<<HTML
<textarea rows=30 cols="80">
Is <HTML> &gt; XHTML?
</textarea>
HTML
		);
		$processor->next_token();

		$this->assertSame(
			'TEXTAREA',
			$processor->get_token_name(),
			"Should have found TEXTAREA tag name but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			'30',
			$processor->get_attribute( 'rows' ),
			"Should have found rows attribute value 'module' but found {$processor->get_attribute( 'rows' )} instead."
		);

		$this->assertSame(
			'80',
			$processor->get_attribute( 'cols' ),
			"Should have found cols attribute value 'module' but found {$processor->get_attribute( 'cols' )} instead."
		);

		$attributes     = $processor->get_attribute_names_with_prefix( '' );
		$attribute_list = array_map( 'Tests_HtmlApi_WpHtmlProcessor_Token_Scanning::quoted', $attributes );
		$this->assertSame(
			array( 'rows', 'cols' ),
			$attributes,
			'Should have found only two attributes but found ' . implode( ', ', $attribute_list ) . ' instead.'
		);

		// Note that the leading newline should be removed from the TEXTAREA contents.
		$this->assertSame(
			"Is <HTML> > XHTML?\n",
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative TITLE elements are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_title_element() {
		$processor = new WP_HTML_Tag_Processor(
			<<<HTML
<title class="multi-line-title">
Is <HTML> &gt; XHTML?
</title>
HTML
		);
		$processor->next_token();

		$this->assertSame(
			'TITLE',
			$processor->get_token_name(),
			"Should have found TITLE tag name but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			'multi-line-title',
			$processor->get_attribute( 'class' ),
			"Should have found class attribute value 'multi-line-title' but found {$processor->get_attribute( 'rows' )} instead."
		);

		$attributes     = $processor->get_attribute_names_with_prefix( '' );
		$attribute_list = array_map( 'Tests_HtmlApi_WpHtmlProcessor_Token_Scanning::quoted', $attributes );
		$this->assertSame(
			array( 'class' ),
			$attributes,
			'Should have found only one attribute but found ' . implode( ', ', $attribute_list ) . ' instead.'
		);

		$this->assertSame(
			"\nIs <HTML> > XHTML?\n",
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative RAWTEXT elements are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 *
	 * @dataProvider data_rawtext_elements
	 *
	 * @param string $tag_name The name of the RAWTEXT tag to test.
	 */
	public function test_basic_assertion_rawtext_elements( $tag_name ) {
		$processor = new WP_HTML_Tag_Processor(
			<<<HTML
<{$tag_name} class="multi-line-title">
Is <HTML> &gt; XHTML?
</{$tag_name}>
HTML
		);
		$processor->next_token();

		$this->assertSame(
			$tag_name,
			$processor->get_token_name(),
			"Should have found {$tag_name} tag name but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			'multi-line-title',
			$processor->get_attribute( 'class' ),
			"Should have found class attribute value 'multi-line-title' but found {$processor->get_attribute( 'rows' )} instead."
		);

		$attributes     = $processor->get_attribute_names_with_prefix( '' );
		$attribute_list = array_map( 'Tests_HtmlApi_WpHtmlProcessor_Token_Scanning::quoted', $attributes );
		$this->assertSame(
			array( 'class' ),
			$attributes,
			'Should have found only one attribute but found ' . implode( ', ', $attribute_list ) . ' instead.'
		);

		$this->assertSame(
			"\nIs <HTML> &gt; XHTML?\n",
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_rawtext_elements() {
		return array(
			'IFRAME'   => array( 'IFRAME' ),
			'NOEMBED'  => array( 'NOEMBED' ),
			'NOFRAMES' => array( 'NOFRAMES' ),
			'STYLE'    => array( 'STYLE' ),
			'XMP'      => array( 'XMP' ),
		);
	}

	/**
	 * Ensures that normative CDATA sections are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_cdata_section() {
		$processor = new WP_HTML_Tag_Processor( '<![CDATA[this is a comment]]>' );
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found comment token but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			WP_HTML_Processor::COMMENT_AS_CDATA_LOOKALIKE,
			$processor->get_comment_type(),
			'Should have detected a CDATA-like invalid comment.'
		);

		$this->assertNull(
			$processor->get_tag(),
			'Should not have been able to query tag name on non-element token.'
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			'this is a comment',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative CDATA sections are properly parsed.
	 *
	 * @ticket 60406
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_cdata_comment_with_incorrect_closer() {
		$processor = new WP_HTML_Tag_Processor( '<![CDATA[this is missing a closing square bracket]>' );
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found comment token but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			WP_HTML_Processor::COMMENT_AS_INVALID_HTML,
			$processor->get_comment_type(),
			'Should have detected invalid HTML comment.'
		);

		$this->assertSame(
			'[CDATA[this is missing a closing square bracket]',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that abruptly-closed CDATA sections are properly parsed as comments.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_abruptly_closed_cdata_section() {
		$processor = new WP_HTML_Tag_Processor( '<![CDATA[this is > a comment]]>' );
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found a bogus comment but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			WP_HTML_Processor::COMMENT_AS_INVALID_HTML,
			$processor->get_comment_type(),
			'Should have detected invalid HTML comment.'
		);

		$this->assertNull(
			$processor->get_tag(),
			'Should not have been able to query tag name on non-element token.'
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			'[CDATA[this is ',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);

		$processor->next_token();

		$this->assertSame(
			'#text',
			$processor->get_token_name(),
			"Should have found text node but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			' a comment]]>',
			$processor->get_modifiable_text(),
			'Should have found remaining syntax from abruptly-closed CDATA section.'
		);
	}

	/**
	 * Ensures that normative Processing Instruction nodes are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_processing_instruction() {
		$processor = new WP_HTML_Tag_Processor( '<?wp-bit {"just": "kidding"}?>' );
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found comment token but found {$processor->get_token_name()} instead."
		);

		$this->assertSame(
			WP_HTML_Processor::COMMENT_AS_PI_NODE_LOOKALIKE,
			$processor->get_comment_type(),
			'Should have detected a Processing Instruction-like invalid comment.'
		);

		$this->assertSame(
			'wp-bit',
			$processor->get_tag(),
			"Should have found PI target as tag name but found {$processor->get_tag()} instead."
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			' {"just": "kidding"}',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that abruptly-closed Processing Instruction nodes are properly parsed as comments.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_abruptly_closed_processing_instruction() {
		$processor = new WP_HTML_Tag_Processor( '<?version=">=5.3.6"?>' );
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_type(),
			"Should have found bogus comment but found {$processor->get_token_type()} instead."
		);

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found #comment as name but found {$processor->get_token_name()} instead."
		);

		$this->assertNull(
			$processor->get_tag(),
			'Should not have been able to query tag name on non-element token.'
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			'version="',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);

		$processor->next_token();

		$this->assertSame(
			'=5.3.6"?>',
			$processor->get_modifiable_text(),
			'Should have found remaining syntax from abruptly-closed Processing Instruction.'
		);
	}

	/**
	 * Ensures that common comments are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @dataProvider data_common_comments
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 *
	 * @param string $html Contains the comment in full.
	 * @param string $text Contains the appropriate modifiable text.
	 */
	public function test_basic_assertion_common_comments( $html, $text ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_type(),
			"Should have found comment but found {$processor->get_token_type()} instead."
		);

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found #comment as name but found {$processor->get_token_name()} instead."
		);

		$this->assertNull(
			$processor->get_tag(),
			'Should not have been able to query tag name on non-element token.'
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			$text,
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_common_comments() {
		return array(
			'Shortest comment'        => array( '<!-->', '' ),
			'Short comment'           => array( '<!--->', '' ),
			'Short comment w/o text'  => array( '<!---->', '' ),
			'Short comment with text' => array( '<!----->', '-' ),
			'PI node without target'  => array( '<? missing?>', ' missing?' ),
			'Invalid PI node'         => array( '<?/missing/>', '/missing/' ),
			'Invalid ! directive'     => array( '<!something else>', 'something else' ),
		);
	}

	/**
	 * Ensures that normative HTML comments are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_html_comment() {
		$processor = new WP_HTML_Tag_Processor( '<!-- wp:paragraph -->' );
		$processor->next_token();

		$this->assertSame(
			'#comment',
			$processor->get_token_type(),
			"Should have found comment but found {$processor->get_token_type()} instead."
		);

		$this->assertSame(
			'#comment',
			$processor->get_token_name(),
			"Should have found #comment as name but found {$processor->get_token_name()} instead."
		);

		$this->assertNull(
			$processor->get_tag(),
			'Should not have been able to query tag name on non-element token.'
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			' wp:paragraph ',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative DOCTYPE elements are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_doctype() {
		$processor = new WP_HTML_Tag_Processor( '<!DOCTYPE html>' );
		$processor->next_token();

		$this->assertSame(
			'#doctype',
			$processor->get_token_type(),
			"Should have found DOCTYPE but found {$processor->get_token_type()} instead."
		);

		$this->assertSame(
			'html',
			$processor->get_token_name(),
			"Should have found 'html' as name but found {$processor->get_token_name()} instead."
		);

		$this->assertNull(
			$processor->get_tag(),
			'Should not have been able to query tag name on non-element token.'
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			' html',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative presumptuous tag closers (empty closers) are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_presumptuous_tag() {
		$processor = new WP_HTML_Tag_Processor( '</>' );
		$processor->next_token();

		$this->assertSame(
			'#presumptuous-tag',
			$processor->get_token_type(),
			"Should have found presumptuous tag but found {$processor->get_token_type()} instead."
		);

		$this->assertSame(
			'#presumptuous-tag',
			$processor->get_token_name(),
			"Should have found #presumptuous-tag as name but found {$processor->get_token_name()} instead."
		);

		$this->assertNull(
			$processor->get_tag(),
			'Should not have been able to query tag name on non-element token.'
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			'',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that normative funky comments are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.5.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 */
	public function test_basic_assertion_funky_comment() {
		$processor = new WP_HTML_Tag_Processor( '</%url>' );
		$processor->next_token();

		$this->assertSame(
			'#funky-comment',
			$processor->get_token_type(),
			"Should have found funky comment but found {$processor->get_token_type()} instead."
		);

		$this->assertSame(
			'#funky-comment',
			$processor->get_token_name(),
			"Should have found #funky-comment as name but found {$processor->get_token_name()} instead."
		);

		$this->assertNull(
			$processor->get_tag(),
			'Should not have been able to query tag name on non-element token.'
		);

		$this->assertNull(
			$processor->get_attribute( 'type' ),
			'Should not have been able to query attributes on non-element token.'
		);

		$this->assertSame(
			'%url',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Test helper that wraps a string in double quotes.
	 *
	 * @param string $s The string to wrap in double-quotes.
	 * @return string The string wrapped in double-quotes.
	 */
	private static function quoted( $s ) {
		return "\"$s\"";
	}
}
