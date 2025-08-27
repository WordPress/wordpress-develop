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
	 * Ensures that `get_modifiable_text()` properly transforms text content.
	 *
	 * The newline and NULL byte (U+0000) behaviors can be complicated since they depend
	 * on where the bytes were found and whether they were raw bytes in the input stream
	 * or decoded from character references.
	 *
	 * @ticket 61576
	 *
	 * @dataProvider data_modifiable_text_needing_transformation
	 *
	 * @param string $html_with_target_node    HTML with node containing `target` or `target-next` attribute.
	 * @param string $expected_modifiable_text Expected modifiable text from target node or following node.
	 */
	public function test_modifiable_text_proper_transforms( string $html_with_target_node, string $expected_modifiable_text ) {
		$processor = new WP_HTML_Tag_Processor( $html_with_target_node );

		// Find the expected target node.
		while ( $processor->next_token() ) {
			$target = $processor->get_attribute( 'target' );
			if ( true === $target ) {
				break;
			}

			if ( is_numeric( $target ) ) {
				for ( $i = (int) $target; $i > 0; $i-- ) {
					$processor->next_token();
				}
				break;
			}
		}

		$this->assertSame(
			$expected_modifiable_text,
			$processor->get_modifiable_text(),
			"Should have properly decoded and transformed modifiable text, but didn't."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_modifiable_text_needing_transformation() {
		return array(
			'Text node + NULL byte'      => array( "<span target=1>NULL byte in \x00 text nodes disappears.", 'NULL byte in  text nodes disappears.' ),
			'LISTING + newline'          => array( "<listing target=1>\nNo newline</listing>", 'No newline' ),
			'LISTING + CR + LF'          => array( "<listing target=1>\r\nNo newline</listing>", 'No newline' ),
			'LISTING + Encoded LF'       => array( '<listing target=1>&#x0a;No newline</listing>', 'No newline' ),
			'LISTING + Encoded CR'       => array( '<listing target=1>&#x0d;Newline</listing>', "\rNewline" ),
			'LISTING + Encoded CR + LF'  => array( '<listing target=1>&#x0d;&#x0a;Newline</listing>', "\r\nNewline" ),
			'PRE + newline'              => array( "<pre target=1>\nNo newline</pre>", 'No newline' ),
			'PRE + CR + LF'              => array( "<pre target=1>\r\nNo newline</pre>", 'No newline' ),
			'PRE + Encoded LF'           => array( '<pre target=1>&#x0a;No newline</pre>', 'No newline' ),
			'PRE + Encoded CR'           => array( '<pre target=1>&#x0d;Newline</pre>', "\rNewline" ),
			'PRE + Encoded CR + LF'      => array( '<pre target=1>&#x0d;&#x0a;Newline</pre>', "\r\nNewline" ),
			'TEXTAREA + newline'         => array( "<textarea target>\nNo newline</textarea>", 'No newline' ),
			'TEXTAREA + CR + LF'         => array( "<textarea target>\r\nNo newline</textarea>", 'No newline' ),
			'TEXTAREA + Encoded LF'      => array( '<textarea target>&#x0a;No newline</textarea>', 'No newline' ),
			'TEXTAREA + Encoded CR'      => array( '<textarea target>&#x0d;Newline</textarea>', "\rNewline" ),
			'TEXTAREA + Encoded CR + LF' => array( '<textarea target>&#x0d;&#x0a;Newline</textarea>', "\r\nNewline" ),
			'TEXTAREA + Comment-like'    => array( "<textarea target><!-- comment -->\nNo newline</textarea>", "<!-- comment -->\nNo newline" ),
			'PRE + Comment'              => array( "<pre target=2><!-- comment -->\nNo newline</pre>", "\nNo newline" ),
			'PRE + CDATA-like'           => array( "<pre target=2><![CDATA[test]]>\nNo newline</pre>", "\nNo newline" ),
			'LISTING + NULL byte'        => array( "<listing target=1>\x00 is missing</listing>", ' is missing' ),
			'PRE + NULL byte'            => array( "<pre target=1>\x00 is missing</pre>", ' is missing' ),
			'TEXTAREA + NULL byte'       => array( "<textarea target>\x00 is U+FFFD</textarea>", "\u{FFFD} is U+FFFD" ),
			'SCRIPT + NULL byte'         => array( "<script target>\x00 is U+FFFD</script>", "\u{FFFD} is U+FFFD" ),
			'esc(SCRIPT) + NULL byte'    => array( "<script target><!-- <script> \x00 </script> --> is U+FFFD</script>", "<!-- <script> \u{FFFD} </script> --> is U+FFFD" ),
			'STYLE + NULL byte'          => array( "<style target>\x00 is U+FFFD</style>", "\u{FFFD} is U+FFFD" ),
			'XMP + NULL byte'            => array( "<xmp target>\x00 is U+FFFD</xmp>", "\u{FFFD} is U+FFFD" ),
			'CDATA-like + NULL byte'     => array( "<span target=1><![CDATA[just a \x00comment]]>", "just a \u{FFFD}comment" ),
			'Funky comment + NULL byte'  => array( "<span target=1></%just a \x00comment>", "%just a \u{FFFD}comment" ),
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
	 * Ensures that basic CDATA sections inside foreign content are detected.
	 *
	 * @ticket 61576
	 */
	public function test_basic_cdata_in_foreign_content() {
		$processor = new WP_HTML_Tag_Processor( '<svg><![CDATA[this is >&gt; real CDATA]]></svg>' );
		$processor->next_token();

		// Artificially change namespace; this should be done in the HTML Processor.
		$processor->change_parsing_namespace( 'svg' );
		$processor->next_token();

		$this->assertSame(
			'#cdata-section',
			$processor->get_token_name(),
			"Should have found a CDATA section but found {$processor->get_token_name()} instead."
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
			'this is >&gt; real CDATA',
			$processor->get_modifiable_text(),
			'Found incorrect modifiable text.'
		);
	}

	/**
	 * Ensures that empty CDATA sections inside foreign content are detected.
	 *
	 * @ticket 61576
	 */
	public function test_empty_cdata_in_foreign_content() {
		$processor = new WP_HTML_Tag_Processor( '<svg><![CDATA[]]></svg>' );
		$processor->next_token();

		// Artificially change namespace; this should be done in the HTML Processor.
		$processor->change_parsing_namespace( 'svg' );
		$processor->next_token();

		$this->assertSame(
			'#cdata-section',
			$processor->get_token_name(),
			"Should have found a CDATA section but found {$processor->get_token_name()} instead."
		);

		$this->assertEmpty(
			$processor->get_modifiable_text(),
			'Found non-empty modifiable text.'
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
	 * Ensures that various funky comments are properly parsed.
	 *
	 * @ticket 60170
	 *
	 * @since 6.6.0
	 *
	 * @covers WP_HTML_Tag_Processor::next_token
	 *
	 * @dataProvider data_various_funky_comments
	 *
	 * @param string $funky_comment_html HTML containing a funky comment.
	 * @param string $modifiable_text    Expected modifiable text of first funky comment in HTML.
	 */
	public function test_various_funky_comments( $funky_comment_html, $modifiable_text ) {
		$processor = new WP_HTML_Tag_Processor( $funky_comment_html );
		while ( '#funky-comment' !== $processor->get_token_type() && $processor->next_token() ) {
			continue;
		}

		$this->assertSame(
			'#funky-comment',
			$processor->get_token_type(),
			'Failed to find the expected funky comment.'
		);

		$this->assertSame(
			$modifiable_text,
			$processor->get_modifiable_text(),
			'Found the wrong modifiable text span inside a funky comment.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_various_funky_comments() {
		return array(
			'Space'          => array( '</ >', ' ' ),
			'Short-bang'     => array( '</!>', '!' ),
			'Question mark'  => array( '</?>', '?' ),
			'Short-slash'    => array( '<//>', '/' ),
			'Bit (no attrs)' => array( '<//wp:post-meta>', '/wp:post-meta' ),
			'Bit (attrs)'    => array( '<//wp:post-meta key=isbn>', '/wp:post-meta key=isbn' ),
			'Curly-wrapped'  => array( '</{json}>', '{json}' ),
			'Before P'       => array( '</1><p>', '1' ),
			'After P'        => array( '<p></__("Read more")></p>', '__("Read more")' ),
			'Reference'      => array( '</&gt;>', '&gt;' ),
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
