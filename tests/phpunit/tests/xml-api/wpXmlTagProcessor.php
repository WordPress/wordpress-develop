<?php
/**
 * Unit tests covering WP_XML_Tag_Processor functionality.
 *
 * @package WordPress
 * @subpackage XML-API
 */

/**
 * @group xml-api
 *
 * @coversDefaultClass WP_XML_Tag_Processor
 */
class Tests_XmlApi_WpXmlTagProcessor extends WP_UnitTestCase {
	const XML_SIMPLE       = '<wp:content id="first"><wp:text id="second">Text</wp:text></wp:content>';
	const XML_WITH_CLASSES = '<wp:content wp:post-type="main with-border" id="first"><wp:text wp:post-type="not-main bold with-border" id="second">Text</wp:text></wp:content>';
	const XML_MALFORMED    = '<wp:content><wp:text wp:post-type="d-md-none" Notifications</wp:text><wp:text wp:post-type="d-none d-md-inline">Back to notifications</wp:text></wp:content>';

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_null_before_finding_tags() {
		$processor = new WP_XML_Tag_Processor( '<wp:content>Test</wp:content>' );

		$this->assertNull( $processor->get_tag(), 'Calling get_tag() without selecting a tag did not return null' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_null_when_not_in_open_tag() {
		$processor = new WP_XML_Tag_Processor( '<wp:content>Test</wp:content>' );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertNull( $processor->get_tag(), 'Accessing a non-existing tag did not return null' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_open_tag_name() {
		$processor = new WP_XML_Tag_Processor( '<wp:content>Test</wp:content>' );

		$this->assertTrue( $processor->next_tag( 'wp:content' ), 'Querying an existing tag did not return true' );
		$this->assertSame( 'wp:content', $processor->get_tag(), 'Accessing an existing tag name did not return "div"' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::is_empty_element
	 *
	 * @dataProvider data_is_empty_element
	 *
	 * @param string $xml Input XML whose first tag might contain the self-closing flag `/`.
	 * @param bool $flag_is_set Whether the input XML's first tag contains the self-closing flag.
	 */
	public function test_is_empty_element_matches_input_xml( $xml, $flag_is_set ) {
		$processor = new WP_XML_Tag_Processor( $xml );
		$processor->next_tag( array( 'tag_closers' => 'visit' ) );

		if ( $flag_is_set ) {
			$this->assertTrue( $processor->is_empty_element(), 'Did not find the empty element tag when it was present.' );
		} else {
			$this->assertFalse( $processor->is_empty_element(), 'Found the empty element tag when it was absent.' );
		}
	}

	/**
	 * Data provider. XML tags which might have a self-closing flag, and an indicator if they do.
	 *
	 * @return array[]
	 */
	public static function data_is_empty_element() {
		return array(
			// These should not have a self-closer, and will leave an element un-closed if it's assumed they are self-closing.
			'Self-closing flag on non-void XML element'    => array( '<wp:content />', true ),
			'No self-closing flag on non-void XML element' => array( '<wp:content>', false ),
			// These should not have a self-closer, but are benign when used because the elements are void.
			'Self-closing flag on void XML element'        => array( '<photo />', true ),
			'No self-closing flag on void XML element'     => array( '<photo>', false ),
			'Self-closing flag on void XML element without spacing' => array( '<photo/>', true ),
			// These should not have a self-closer, but as part of a tag closer they are entirely ignored.
			'No self-closing flag on tag closer'           => array( '</textarea>', false ),
			// These can and should have self-closers, and will leave an element un-closed if it's assumed they aren't self-closing.
			'Self-closing flag on a foreign element'       => array( '<circle />', true ),
			'No self-closing flag on a foreign element'    => array( '<circle>', false ),
			// These involve syntax peculiarities.
			'Self-closing flag after extra spaces'         => array( '<wp:content      />', true ),
			'Self-closing flag after quoted attribute'     => array( '<wp:content id="test"/>', true ),
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_not_in_open_tag() {
		$processor = new WP_XML_Tag_Processor( '<wp:content wp:post-type="test">Test</wp:content>' );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertNull( $processor->get_attribute( 'wp:post-type' ), 'Accessing an attribute of a non-existing tag did not return null' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_in_closing_tag() {
		$processor = new WP_XML_Tag_Processor( '<wp:content wp:post-type="test">Test</wp:content>' );

		$this->assertTrue( $processor->next_tag( 'wp:content' ), 'Querying an existing tag did not return true' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), 'Querying an existing closing tag did not return true' );
		$this->assertNull( $processor->get_attribute( 'wp:post-type' ), 'Accessing an attribute of a closing tag did not return null' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_attribute_missing() {
		$processor = new WP_XML_Tag_Processor( '<wp:content wp:post-type="test">Test</wp:content>' );

		$this->assertTrue( $processor->next_tag( 'wp:content' ), 'Querying an existing tag did not return true' );
		$this->assertNull( $processor->get_attribute( 'test-id' ), 'Accessing a non-existing attribute did not return null' );
	}

	/**
	 * @ticket 61365
	 *
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::base_class_next_token
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_attributes_are_rejected_in_tag_closers() {
		$processor = new WP_XML_Tag_Processor( '<wp:content>Test</wp:content wp:post-type="test">' );

		$this->assertTrue( $processor->next_tag( 'wp:content' ), 'Querying an existing tag did not return true' );
		$this->assertFalse( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), 'Querying an existing but invalid closing tag did not return false.' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_attribute_value() {
		$processor = new WP_XML_Tag_Processor( '<wp:content wp:post-type="test">Test</wp:content>' );

		$this->assertTrue( $processor->next_tag( 'wp:content' ), 'Querying an existing tag did not return true' );
		$this->assertSame( 'test', $processor->get_attribute( 'wp:post-type' ), 'Accessing a wp:post-type="test" attribute value did not return "test"' );
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::parse_next_attribute
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_no_value() {
		$processor = new WP_XML_Tag_Processor( '<wp:content enabled wp:post-type="test">Test</wp:content>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::parse_next_attribute
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_no_quotes() {
		$processor = new WP_XML_Tag_Processor( '<wp:content enabled=1 wp:post-type="test">Test</wp:content>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::get_attribute
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_contains_ampersand() {
		$processor = new WP_XML_Tag_Processor( '<wp:content enabled="WordPress & WordPress">Test</wp:content>' );

		$this->assertTrue( $processor->next_tag(), 'Querying a tag did not return true' );
		$this->assertFalse( $processor->get_attribute( 'enabled' ), 'Querying a malformed attribute did not return null' );
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::get_attribute
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_contains_entity_without_semicolon() {
		$processor = new WP_XML_Tag_Processor( '<wp:content enabled="&#x94">Test</wp:content>' );

		$this->assertTrue( $processor->next_tag(), 'Querying a tag did not return true' );
		$this->assertFalse( $processor->get_attribute( 'enabled' ), 'Querying a malformed attribute did not return null' );
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::parse_next_attribute
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_contains_lt_character() {
		$processor = new WP_XML_Tag_Processor( '<wp:content enabled="I love <3 this">Test</wp:content>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::parse_next_attribute
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_tags_duplicate_attributes() {
		$processor = new WP_XML_Tag_Processor( '<wp:content id="update-me" id="ignored-id"><wp:text id="second">Text</wp:text></wp:content>' );

		$this->assertFalse( $processor->next_tag() );
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::parse_next_attribute
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_name_contains_slash() {
		$processor = new WP_XML_Tag_Processor( '<wp:content a/b="test">Test</wp:content>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_modifiable_text_returns_a_decoded_value() {
		$processor = new WP_XML_Tag_Processor( '<root>&#x93;&#x1f604;&#x94;</root>' );

		$processor->next_tag( 'root' );
		$processor->next_token();

		$this->assertEquals(
			'“😄”',
			$processor->get_modifiable_text(),
			'Reading an encoded text did not decode it.'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_a_decoded_value() {
		$processor = new WP_XML_Tag_Processor( '<root encoded-data="&#x93;&#x1f604;&#x94;"></root>' );

		$this->assertTrue( $processor->next_tag( 'root' ), 'Querying a tag did not return true' );
		$this->assertEquals(
			'“😄”',
			$processor->get_attribute( 'encoded-data' ),
			'Reading an encoded attribute did not decode it.'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 *
	 * @param string $attribute_name Name of data-enabled attribute with case variations.
	 */
	public function test_get_attribute_is_case_sensitive() {
		$processor = new WP_XML_Tag_Processor( '<wp:content DATA-enabled="true">Test</wp:content>' );
		$processor->next_tag();

		$this->assertEquals(
			'true',
			$processor->get_attribute( 'DATA-enabled' ),
			'Accessing an attribute by a same-cased name did return not its value'
		);

		$this->assertNull(
			$processor->get_attribute( 'data-enabled' ),
			'Accessing an attribute by a differently-cased name did return its value'
		);
	}


	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_is_case_sensitive() {
		$processor = new WP_XML_Tag_Processor( '<wp:content DATA-enabled="true">Test</wp:content>' );
		$processor->next_tag();
		$processor->remove_attribute( 'data-enabled' );

		$this->assertSame( '<wp:content DATA-enabled="true">Test</wp:content>', $processor->get_updated_xml(), 'A case-sensitive remove_attribute call did remove the attribute' );

		$processor->remove_attribute( 'DATA-enabled' );

		$this->assertSame( '<wp:content >Test</wp:content>', $processor->get_updated_xml(), 'A case-sensitive remove_attribute call did not remove the attribute' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_is_case_sensitive() {
		$processor = new WP_XML_Tag_Processor( '<wp:content DATA-enabled="true">Test</wp:content>' );
		$processor->next_tag();
		$processor->set_attribute( 'data-enabled', 'abc' );

		$this->assertSame( '<wp:content data-enabled="abc" DATA-enabled="true">Test</wp:content>', $processor->get_updated_xml(), 'A case-insensitive set_attribute call did not update the existing attribute' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_before_finding_tags() {
		$processor = new WP_XML_Tag_Processor( '<wp:content data-foo="bar">Test</wp:content>' );
		$this->assertNull(
			$processor->get_attribute_names_with_prefix( 'data-' ),
			'Accessing attributes by their prefix did not return null when no tag was selected'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_when_not_in_open_tag() {
		$processor = new WP_XML_Tag_Processor( '<wp:content data-foo="bar">Test</wp:content>' );
		$processor->next_tag( 'p' );
		$this->assertNull( $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing attributes of a non-existing tag did not return null' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_when_in_closing_tag() {
		$processor = new WP_XML_Tag_Processor( '<wp:content data-foo="bar">Test</wp:content>' );
		$processor->next_tag( 'wp:content' );
		$processor->next_tag( array( 'tag_closers' => 'visit' ) );

		$this->assertNull( $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing attributes of a closing tag did not return null' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_empty_array_when_no_attributes_present() {
		$processor = new WP_XML_Tag_Processor( '<wp:content>Test</wp:content>' );
		$processor->next_tag( 'wp:content' );

		$this->assertSame( array(), $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing the attributes on a tag without any did not return an empty array' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_matching_attribute_names_in_original_case() {
		$processor = new WP_XML_Tag_Processor( '<wp:content DATA-enabled="yes" wp:post-type="test" data-test-ID="14">Test</wp:content>' );
		$processor->next_tag();

		$this->assertSame(
			array( 'data-test-ID' ),
			$processor->get_attribute_names_with_prefix( 'data-' ),
			'Accessing attributes by their prefix did not return their lowercase names'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_attribute_added_by_set_attribute() {
		$processor = new WP_XML_Tag_Processor( '<wp:content data-foo="bar">Test</wp:content>' );
		$processor->next_tag();
		$processor->set_attribute( 'data-test-id', '14' );

		$this->assertSame(
			'<wp:content data-test-id="14" data-foo="bar">Test</wp:content>',
			$processor->get_updated_xml(),
			"Updated XML doesn't include attribute added via set_attribute"
		);
		$this->assertSame(
			array( 'data-test-id', 'data-foo' ),
			$processor->get_attribute_names_with_prefix( 'data-' ),
			"Accessing attribute names doesn't find attribute added via set_attribute"
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::__toString
	 */
	public function test_to_string_returns_updated_xml() {
		$processor = new WP_XML_Tag_Processor( '<line id="remove" /><wp:content enabled="yes" wp:post-type="test">Test</wp:content><wp:text id="span-id"></wp:text>' );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$processor->next_tag();
		$processor->set_attribute( 'id', 'wp:content-id-1' );

		$this->assertSame(
			$processor->get_updated_xml(),
			(string) $processor,
			'get_updated_xml() returned a different value than __toString()'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_updated_xml
	 */
	public function test_get_updated_xml_applies_the_updates_so_far_and_keeps_the_processor_on_the_current_tag() {
		$processor = new WP_XML_Tag_Processor( '<line id="remove" /><wp:content enabled="yes" wp:post-type="test">Test</wp:content><wp:text id="span-id"></wp:text>' );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$processor->next_tag();
		$processor->set_attribute( 'id', 'wp:content-id-1' );

		$this->assertSame(
			'<line  /><wp:content id="wp:content-id-1" enabled="yes" wp:post-type="test">Test</wp:content><wp:text id="span-id"></wp:text>',
			$processor->get_updated_xml(),
			'Calling get_updated_xml after updating the attributes of the second tag returned different XML than expected'
		);

		$processor->set_attribute( 'id', 'wp:content-id-2' );

		$this->assertSame(
			'<line  /><wp:content id="wp:content-id-2" enabled="yes" wp:post-type="test">Test</wp:content><wp:text id="span-id"></wp:text>',
			$processor->get_updated_xml(),
			'Calling get_updated_xml after updating the attributes of the second tag for the second time returned different XML than expected'
		);

		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertSame(
			'<line  /><wp:content id="wp:content-id-2" enabled="yes" wp:post-type="test">Test</wp:content><wp:text ></wp:text>',
			$processor->get_updated_xml(),
			'Calling get_updated_xml after removing the id attribute of the third tag returned different XML than expected'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_updated_xml
	 */
	public function test_get_updated_xml_without_updating_any_attributes_returns_the_original_xml() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );

		$this->assertSame(
			self::XML_SIMPLE,
			$processor->get_updated_xml(),
			'Casting WP_XML_Tag_Processor to a string without performing any updates did not return the initial XML snippet'
		);
	}

	/**
	 * Ensures that when seeking to an earlier spot in the document that
	 * all previously-enqueued updates are applied as they ought to be.
	 *
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::parse_next_attribute
	 */
	public function test_get_updated_xml_applies_updates_to_content_after_seeking_to_before_parsed_bytes() {
		$processor = new WP_XML_Tag_Processor( '<wp:content><photo hidden></wp:content>' );

		$processor->next_tag();
		$processor->set_attribute( 'wonky', 'true' );
		$processor->next_tag();
		$processor->set_bookmark( 'here' );

		$processor->next_tag( array( 'tag_closers' => 'visit' ) );
		$processor->seek( 'here' );

		$this->assertSame( '<wp:content wonky="true"><photo hidden></wp:content>', $processor->get_updated_xml() );
	}

	public function test_declare_element_as_pcdata() {
		$text      = '
			This text contains syntax that may seem
			like XML nodes:

			<input />
			</seemingly invalid element --/>
			<!-- is this a comment? -->
			<?xml version="1.0" ?>

			&amp;&lt;&gt;&quot;&apos;

			But! It is all treated as text.
		';
		$processor = new WP_XML_Tag_Processor(
			"<root><my-pcdata>$text</my-pcdata></root>"
		);
		$processor->declare_element_as_pcdata( 'my-pcdata' );
		$processor->next_tag( 'my-pcdata' );

		$this->assertEquals(
			$text,
			$processor->get_modifiable_text(),
			'get_modifiable_text() did not return the expected text'
		);
	}

	/**
	 * Ensures that bookmarks start and length correctly describe a given token in XML.
	 *
	 * @ticket 61365
	 *
	 * @dataProvider data_xml_nth_token_substring
	 *
	 * @param string $xml            Input XML.
	 * @param int    $match_nth_token Which token to inspect from input XML.
	 * @param string $expected_match  Expected full raw token bookmark should capture.
	 */
	public function test_token_bookmark_span( string $xml, int $match_nth_token, string $expected_match ) {
		$processor = new class( $xml ) extends WP_XML_Tag_Processor {
			/**
			 * Returns the raw span of XML for the currently-matched
			 * token, or null if not paused on any token.
			 *
			 * @return string|null Raw XML content of currently-matched token,
			 *                     otherwise `null` if not matched.
			 */
			public function get_raw_token() {
				if (
					WP_XML_Tag_Processor::STATE_READY === $this->parser_state ||
					WP_XML_Tag_Processor::STATE_INCOMPLETE_INPUT === $this->parser_state ||
					WP_XML_Tag_Processor::STATE_COMPLETE === $this->parser_state
				) {
					return null;
				}

				$this->set_bookmark( 'mark' );
				$mark = $this->bookmarks['mark'];

				return substr( $this->xml, $mark->start, $mark->length );
			}
		};

		for ( $i = 0; $i < $match_nth_token; $i++ ) {
			$processor->next_token();
		}

		$raw_token = $processor->get_raw_token();
		$this->assertIsString(
			$raw_token,
			"Failed to find raw token at position {$match_nth_token}: check test data provider."
		);

		$this->assertSame(
			$expected_match,
			$raw_token,
			'Bookmarked wrong span of text for full matched token.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_xml_nth_token_substring() {
		return array(
			// Tags.
			'DIV start tag'                 => array( '<wp:content>', 1, '<wp:content>' ),
			'DIV start tag with attributes' => array( '<wp:content wp:post-type="x" disabled="yes">', 1, '<wp:content wp:post-type="x" disabled="yes">' ),
			'Nested DIV'                    => array( '<wp:content><wp:content b="yes">', 2, '<wp:content b="yes">' ),
			'Sibling DIV'                   => array( '<wp:content></wp:content><wp:content b="yes">', 3, '<wp:content b="yes">' ),
			'DIV after text'                => array( 'text <wp:content>', 2, '<wp:content>' ),
			'DIV before text'               => array( '<wp:content> text', 1, '<wp:content>' ),
			'DIV after comment'             => array( '<root><!-- comment --><wp:content>', 3, '<wp:content>' ),
			'DIV before comment'            => array( '<wp:content><!-- c --> ', 1, '<wp:content>' ),
			'Start "self-closing" tag'      => array( '<wp:content />', 1, '<wp:content />' ),
			'Void tag'                      => array( '<photo src="img.png">', 1, '<photo src="img.png">' ),
			'Void tag w/self-closing flag'  => array( '<photo src="img.png" />', 1, '<photo src="img.png" />' ),
			'Void tag inside DIV'           => array( '<wp:content><photo src="img.png"></wp:content>', 2, '<photo src="img.png">' ),

			// Text.
			'Text'                          => array( 'Just text</data>', 1, 'Just text' ),
			'Text in DIV'                   => array( '<wp:content>Text<wp:content>', 2, 'Text' ),
			'Text before DIV'               => array( 'Text<wp:content>', 1, 'Text' ),
			'Text after comment'            => array( '<!-- comment -->Text<!-- c -->', 2, 'Text' ),
			'Text before comment'           => array( 'Text<!-- c --> ', 1, 'Text' ),

			// Comments.
			'Comment'                       => array( '<!-- comment -->', 1, '<!-- comment -->' ),
			'Comment in DIV'                => array( '<wp:content><!-- comment --><wp:content>', 2, '<!-- comment -->' ),
			'Comment before DIV'            => array( '<!-- comment --><wp:content>', 1, '<!-- comment -->' ),
			'Comment after DIV'             => array( '<wp:content></wp:content><!-- comment -->', 3, '<!-- comment -->' ),
			'Comment after comment'         => array( '<!-- comment --><!-- comment -->', 2, '<!-- comment -->' ),
			'Comment before comment'        => array( '<!-- comment --><!-- c --> ', 1, '<!-- comment -->' ),
			'Empty comment'                 => array( '<!---->', 1, '<!---->' ),
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_next_tag_with_no_arguments_should_find_the_next_existing_tag() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );

		$this->assertTrue( $processor->next_tag(), 'Querying an existing tag did not return true' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_next_tag_should_return_false_for_a_non_existing_tag() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_modifiable_text
	 */
	public function test_normalizes_carriage_returns_in_text_nodes() {
		$processor = new WP_XML_Tag_Processor(
			"<wp:content>We are\rnormalizing\r\n\nthe\n\r\r\r\ncarriage returns</wp:content>"
		);
		$processor->next_tag();
		$processor->next_token();
		$this->assertEquals(
			"We are\nnormalizing\n\nthe\n\n\n\ncarriage returns",
			$processor->get_modifiable_text(),
			'get_raw_token() did not normalize the carriage return characters'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_modifiable_text
	 */
	public function test_normalizes_carriage_returns_in_cdata() {
		$processor = new WP_XML_Tag_Processor(
			"<wp:content><![CDATA[We are\rnormalizing\r\n\nthe\n\r\r\r\ncarriage returns]]>"
		);
		$processor->next_tag();
		$processor->next_token();
		$this->assertEquals(
			"We are\nnormalizing\n\nthe\n\n\n\ncarriage returns",
			$processor->get_modifiable_text(),
			'get_raw_token() did not normalize the carriage return characters'
		);
	}

	/**
	 * @ticket 61365
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 * @covers WP_XML_Tag_Processor::is_tag_closer
	 */
	public function test_next_tag_should_stop_on_closers_only_when_requested() {
		$processor = new WP_XML_Tag_Processor( '<wp:content><photo /></wp:content>' );

		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'wp:content' ) ), 'Did not find desired tag opener' );
		$this->assertFalse( $processor->next_tag( array( 'tag_name' => 'wp:content' ) ), 'Visited an unwanted tag, a tag closer' );

		$processor = new WP_XML_Tag_Processor( '<wp:content><photo /></wp:content>' );
		$processor->next_tag(
			array(
				'tag_name'    => 'wp:content',
				'tag_closers' => 'visit',
			)
		);

		$this->assertFalse( $processor->is_tag_closer(), 'Indicated a tag opener is a tag closer' );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'    => 'wp:content',
					'tag_closers' => 'visit',
				)
			),
			'Did not stop at desired tag closer'
		);
		$this->assertTrue( $processor->is_tag_closer(), 'Indicated a tag closer is a tag opener' );

		$processor = new WP_XML_Tag_Processor( '<wp:content>' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), "Did not find a tag opener when tag_closers was set to 'visit'" );
		$this->assertFalse( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), "Found a closer where there wasn't one" );
	}

	/**
	 * Verifies that updates to a document before calls to `get_updated_xml()` don't
	 * lead to the Tag Processor jumping to the wrong tag after the updates.
	 *
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_updated_xml
	 */
	public function test_internal_pointer_returns_to_original_spot_after_inserting_content_before_cursor() {
		$tags = new WP_XML_Tag_Processor( '<root><wp:content>outside</wp:content><section><wp:content><photo>inside</wp:content></section></root>' );

		$tags->next_tag();
		$tags->next_tag();
		$tags->set_attribute( 'wp:post-type', 'foo' );
		$tags->next_tag( 'section' );

		// Return to this spot after moving ahead.
		$tags->set_bookmark( 'here' );

		// Move ahead.
		$tags->next_tag( 'photo' );
		$tags->seek( 'here' );
		$this->assertSame( '<root><wp:content wp:post-type="foo">outside</wp:content><section><wp:content><photo>inside</wp:content></section></root>', $tags->get_updated_xml() );
		$this->assertSame( 'section', $tags->get_tag() );
		$this->assertFalse( $tags->is_tag_closer() );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_on_a_non_existing_tag_does_not_change_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertFalse( $processor->next_tag( 'wp:content' ), 'Querying a non-existing tag did not return false' );

		$processor->set_attribute( 'id', 'primary' );

		$this->assertSame(
			self::XML_SIMPLE,
			$processor->get_updated_xml(),
			'Calling get_updated_xml after updating a non-existing tag returned an XML that was different from the original XML'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 * @covers WP_XML_Tag_Processor::remove_attribute
	 * @covers WP_XML_Tag_Processor::add_class
	 * @covers WP_XML_Tag_Processor::remove_class
	 */
	public function test_attribute_ops_on_tag_closer_do_not_change_the_markup() {
		$processor = new WP_XML_Tag_Processor( '<wp:content id="3"></wp:content>' );
		$processor->next_tag(
			array(
				'tag_name'    => 'wp:content',
				'tag_closers' => 'visit',
			)
		);

		$this->assertFalse( $processor->is_tag_closer(), 'Skipped tag opener' );

		$processor->next_tag(
			array(
				'tag_name'    => 'wp:content',
				'tag_closers' => 'visit',
			)
		);

		$this->assertTrue( $processor->is_tag_closer(), 'Skipped tag closer' );
		$this->assertFalse( $processor->set_attribute( 'id', 'test' ), "Allowed setting an attribute on a tag closer when it shouldn't have" );
		$this->assertFalse( $processor->remove_attribute( 'invalid-id' ), "Allowed removing an attribute on a tag closer when it shouldn't have" );
		$this->assertSame(
			'<wp:content id="3"></wp:content>',
			$processor->get_updated_xml(),
			'Calling get_updated_xml after updating a non-existing tag returned an XML that was different from the original XML'
		);
	}


	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_a_non_existing_attribute_adds_a_new_attribute_to_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-attribute', 'test-value' );

		$this->assertSame(
			'<wp:content test-attribute="test-value" id="first"><wp:text id="second">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Updated XML does not include attribute added via set_attribute()'
		);
		$this->assertSame(
			'test-value',
			$processor->get_attribute( 'test-attribute' ),
			'get_attribute() (called after get_updated_xml()) did not return attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_updated_values_before_they_are_applied() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-attribute', 'test-value' );

		$this->assertSame(
			'test-value',
			$processor->get_attribute( 'test-attribute' ),
			'get_attribute() (called before get_updated_xml()) did not return attribute added via set_attribute()'
		);
		$this->assertSame(
			'<wp:content test-attribute="test-value" id="first"><wp:text id="second">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Updated XML does not include attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_updated_values_before_they_are_applied_with_different_name_casing() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-ATTribute', 'test-value' );

		$this->assertSame(
			'test-value',
			$processor->get_attribute( 'test-ATTribute' ),
			'get_attribute() (called before get_updated_xml()) did not return attribute added via set_attribute()'
		);
		$this->assertSame(
			'<wp:content test-ATTribute="test-value" id="first"><wp:text id="second">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Updated XML does not include attribute added via set_attribute()'
		);
	}


	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_removed_attribute_before_it_is_applied() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertNull(
			$processor->get_attribute( 'id' ),
			'get_attribute() (called before get_updated_xml()) returned attribute that was removed by remove_attribute()'
		);
		$this->assertSame(
			'<wp:content ><wp:text id="second">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Updated XML includes attribute that was removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_adding_and_then_removing_an_attribute_before_those_updates_are_applied() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-attribute', 'test-value' );
		$processor->remove_attribute( 'test-attribute' );

		$this->assertNull(
			$processor->get_attribute( 'test-attribute' ),
			'get_attribute() (called before get_updated_xml()) returned attribute that was added via set_attribute() and then removed by remove_attribute()'
		);
		$this->assertSame(
			self::XML_SIMPLE,
			$processor->get_updated_xml(),
			'Updated XML includes attribute that was added via set_attribute() and then removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_setting_and_then_removing_an_existing_attribute_before_those_updates_are_applied() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'id', 'test-value' );
		$processor->remove_attribute( 'id' );

		$this->assertNull(
			$processor->get_attribute( 'id' ),
			'get_attribute() (called before get_updated_xml()) returned attribute that was overwritten by set_attribute() and then removed by remove_attribute()'
		);
		$this->assertSame(
			'<wp:content ><wp:text id="second">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Updated XML includes attribute that was overwritten by set_attribute() and then removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_an_existing_attribute_name_updates_its_value_in_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'id', 'new-id' );
		$this->assertSame(
			'<wp:content id="new-id"><wp:text id="second">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Existing attribute was not updated'
		);
	}

	/**
	 * Ensures that when setting an attribute multiple times that only
	 * one update flushes out into the updated XML.
	 *
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_case_variants_updates_only_the_original_first_copy() {
		$processor = new WP_XML_Tag_Processor( '<wp:content data-enabled="5">' );
		$processor->next_tag();
		$processor->set_attribute( 'data-enabled', 'canary1' );
		$processor->set_attribute( 'data-enabled', 'canary2' );
		$processor->set_attribute( 'data-enabled', 'canary3' );

		$this->assertSame( '<wp:content data-enabled="canary3">', strtolower( $processor->get_updated_xml() ) );
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_next_tag_and_set_attribute_in_a_loop_update_all_tags_in_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		while ( $processor->next_tag() ) {
			$processor->set_attribute( 'data-foo', 'bar' );
		}

		$this->assertSame(
			'<wp:content data-foo="bar" id="first"><wp:text data-foo="bar" id="second">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Not all tags were updated when looping with next_tag() and set_attribute()'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_with_an_existing_attribute_name_removes_it_from_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertSame(
			'<wp:content ><wp:text id="second">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Attribute was not removed'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_with_a_non_existing_attribute_name_does_not_change_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->remove_attribute( 'no-such-attribute' );

		$this->assertSame(
			self::XML_SIMPLE,
			$processor->get_updated_xml(),
			'Content was changed when attempting to remove an attribute that did not exist'
		);
	}

	/**
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_correctly_parses_xml_attributes_wrapped_in_single_quotation_marks() {
		$processor = new WP_XML_Tag_Processor(
			'<wp:content id=\'first\'><wp:text id=\'second\'>Text</wp:text></wp:content>'
		);
		$processor->next_tag(
			array(
				'tag_name' => 'wp:content',
				'id'       => 'first',
			)
		);
		$processor->remove_attribute( 'id' );
		$processor->next_tag(
			array(
				'tag_name' => 'wp:text',
				'id'       => 'second',
			)
		);
		$processor->set_attribute( 'id', 'single-quote' );
		$this->assertSame(
			'<wp:content ><wp:text id="single-quote">Text</wp:text></wp:content>',
			$processor->get_updated_xml(),
			'Did not remove single-quoted attribute'
		);
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::parse_next_attribute
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::set_attribute
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_setting_an_attribute_to_false_is_rejected() {
		$processor = new WP_XML_Tag_Processor(
			'<form action="/action_page.php"><input checked type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>'
		);
		$processor->next_tag( 'input' );
		$this->assertFalse(
			$processor->set_attribute( 'checked', false ),
			'Accepted a boolean attribute name.'
		);
	}

	/**
	 * @ticket 61365
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::set_attribute
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_setting_a_missing_attribute_to_false_does_not_change_the_markup() {
		$xml_input = '<form action="/action_page.php"><input type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>';
		$processor = new WP_XML_Tag_Processor( $xml_input );
		$processor->next_tag( 'input' );
		$processor->set_attribute( 'checked', false );
		$this->assertSame(
			$xml_input,
			$processor->get_updated_xml(),
			'Changed the markup unexpectedly when setting a non-existing attribute to false'
		);
	}

	/**
	 * Ensures that unclosed and invalid comments trigger warnings or errors.
	 *
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 * @covers WP_XML_Tag_Processor::paused_at_incomplete_token
	 *
	 * @dataProvider data_xml_with_unclosed_comments
	 *
	 * @param string $xml_ending_before_comment_close XML with opened comments that aren't closed.
	 */
	public function test_documents_may_end_with_unclosed_comment( $xml_ending_before_comment_close ) {
		$processor = new WP_XML_Tag_Processor( $xml_ending_before_comment_close );

		$this->assertFalse(
			$processor->next_tag(),
			"Should not have found any tag, but found {$processor->get_tag()}."
		);

		$this->assertTrue(
			$processor->paused_at_incomplete_token(),
			"Should have indicated that the parser found an incomplete token but didn't."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_xml_with_unclosed_comments() {
		return array(
			'Shortest open valid comment' => array( '<!--' ),
			'Basic truncated comment'     => array( '<!-- this ends --' ),
		);
	}

	/**
	 * Ensures that the processor doesn't attempt to match an incomplete token.
	 *
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 * @covers WP_XML_Tag_Processor::paused_at_incomplete_token
	 *
	 * @dataProvider data_incomplete_syntax_elements
	 *
	 * @param string $incomplete_xml XML text containing some kind of incomplete syntax.
	 */
	public function test_next_tag_returns_false_for_incomplete_syntax_elements( $incomplete_xml ) {
		$processor = new WP_XML_Tag_Processor( $incomplete_xml );

		$this->assertFalse(
			$processor->next_tag(),
			"Shouldn't have found any tags but found {$processor->get_tag()}."
		);

		$this->assertTrue(
			$processor->paused_at_incomplete_token(),
			"Should have indicated that the parser found an incomplete token but didn't."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_syntax_elements() {
		return array(
			'Incomplete tag name'                         => array( '<swit' ),
			'Incomplete tag (no attributes)'              => array( '<wp:content' ),
			'Incomplete tag (attributes)'                 => array( '<wp:content inert="yes" title="test"' ),
			'Incomplete attribute (before =)'             => array( '<button disabled' ),
			'Incomplete attribute (before ")'             => array( '<button disabled=' ),
			'Incomplete attribute (before closing quote)' => array( '<button disabled="value started' ),
			'Incomplete attribute (single quoted)'        => array( "<li wp:post-type='just-another class" ),
			'Incomplete attribute (double quoted)'        => array( '<iframe src="https://www.example.com/embed/abcdef' ),
			'Incomplete comment (normative)'              => array( '<!-- without end' ),
			'Incomplete comment (missing --)'             => array( '<!-- without end --' ),
			'Incomplete CDATA'                            => array( '<![CDATA[something inside of here needs to get out' ),
			'Partial CDATA'                               => array( '<![CDA' ),
			'Partially closed CDATA]'                     => array( '<![CDATA[cannot escape]' ),
		);
	}

	/**
	 * Ensures that the processor doesn't attempt to match an incomplete text node.
	 *
	 * @ticket 61365
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 * @covers WP_XML_Tag_Processor::paused_at_incomplete_token
	 *
	 * @dataProvider data_incomplete_text_nodes
	 *
	 * @param string $incomplete_xml XML text containing some kind of incomplete syntax.
	 */
	public function test_next_tag_returns_false_for_incomplete_text_nodes( $incomplete_xml, $node_at = 1 ) {
		$processor = new WP_XML_Tag_Processor( $incomplete_xml );

		for ( $i = 0; $i < $node_at - 1; $i++ ) {
			$this->assertTrue(
				$processor->next_token(),
				"Failed to find text node {$i} in incomplete XML."
			);
		}

		$this->assertFalse(
			$processor->next_token(),
			"Shouldn't have found any more text nodes but found {$processor->get_modifiable_text()}."
		);

		$this->assertTrue(
			$processor->paused_at_incomplete_token(),
			"Should have indicated that the parser found an incomplete token but didn't."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_incomplete_text_nodes() {
		return array(
			'Incomplete text node'               => array( 'This is a text node' ),
			'Incomplete text node after a tag'   => array( '<data>This is a text node', 2 ),
			'Incomplete text node after (CDATA)' => array( 'This is a text node<![CDATA[ and this is a second text node ]]> and this is the third text node.', 3 ),
		);
	}

	/**
	 * The string " -- " (double-hyphen) must not occur within comments.
	 *
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::parse_next_tag
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_rejects_malformed_comments() {
		$processor = new WP_XML_Tag_Processor( '<!-- comment -- oh, I did not close it after the initial double dash -->' );
		$this->assertFalse( $processor->next_token(), 'Did not reject a malformed XML comment.' );
	}

	/**
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_handles_malformed_taglike_open_short_xml() {
		$processor = new WP_XML_Tag_Processor( '<' );
		$result    = $processor->next_tag();
		$this->assertFalse( $result, 'Did not handle "<" xml properly.' );
	}

	/**
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_handles_malformed_taglike_close_short_xml() {
		$processor = new WP_XML_Tag_Processor( '</ ' );
		$result    = $processor->next_tag();
		$this->assertFalse( $result, 'Did not handle "</ " xml properly.' );
	}

	/**
	 * @expectedIncorrectUsage WP_XML_Tag_Processor::base_class_next_token
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_rejects_empty_element_that_is_also_a_closer() {
		$processor = new WP_XML_Tag_Processor( '</wp:content/> ' );
		$result    = $processor->next_tag();
		$this->assertFalse( $result, 'Did not handle "</wp:content/>" xml properly.' );
	}

	/**
	 * Ensures that non-tag syntax starting with `<` is rejected.
	 *
	 * @ticket 61365
	 */
	public function test_single_text_node_with_taglike_text() {
		$processor = new WP_XML_Tag_Processor( 'This is a text node< /A>' );
		$this->assertTrue( $processor->next_token(), 'A valid text node was not found.' );
		$this->assertEquals( 'This is a text node', $processor->get_modifiable_text(), 'The contents of a valid text node were not correctly captured.' );
		$this->assertFalse( $processor->next_tag(), 'A malformed XML markup was not rejected.' );
	}

	/**
	 * Ensures that non-tag syntax starting with `<` is rejected.
	 *
	 * @ticket 61365
	 */
	public function test_parses_CDATA() {
		$processor = new WP_XML_Tag_Processor( '<root><![CDATA[This is a CDATA text node.]]></root>' );
		$processor->next_tag();
		$this->assertTrue( $processor->next_token(), 'The first text node was not found.' );      $this->assertEquals(
			'This is a CDATA text node.',
			$processor->get_modifiable_text(),
			'The contents of a a CDATA text node were not correctly captured.'
		);
	}

	/**
	 * @ticket 61365
	 */
	public function test_yields_CDATA_a_separate_text_node() {
		$processor = new WP_XML_Tag_Processor( '<root>This is the first text node <![CDATA[ and this is a second text node ]]> and this is the third text node.</root>' );

		$processor->next_token();
		$this->assertTrue( $processor->next_token(), 'The first text node was not found.' );
		$this->assertEquals(
			'This is the first text node ',
			$processor->get_modifiable_text(),
			'The contents of a valid text node were not correctly captured.'
		);

		$this->assertTrue( $processor->next_token(), 'The CDATA text node was not found.' );
		$this->assertEquals(
			' and this is a second text node ',
			$processor->get_modifiable_text(),
			'The contents of a a CDATA text node were not correctly captured.'
		);

		$this->assertTrue( $processor->next_token(), 'The text node was not found.' );
		$this->assertEquals(
			' and this is the third text node.',
			$processor->get_modifiable_text(),
			'The contents of a valid text node were not correctly captured.'
		);
	}

	/**
	 *
	 * @ticket 61365
	 */
	public function test_xml_declaration() {
		$processor = new WP_XML_Tag_Processor( '<?xml version="1.0" encoding="UTF-8" ?>' );
		$this->assertTrue( $processor->next_token(), 'The XML declaration was not found.' );
		$this->assertEquals(
			'#xml-declaration',
			$processor->get_token_type(),
			'The XML declaration was not correctly identified.'
		);
		$this->assertEquals( '1.0', $processor->get_attribute( 'version' ), 'The version attribute was not correctly captured.' );
		$this->assertEquals( 'UTF-8', $processor->get_attribute( 'encoding' ), 'The encoding attribute was not correctly captured.' );
	}

	/**
	 *
	 * @ticket 61365
	 */
	public function test_xml_declaration_with_single_quotes() {
		$processor = new WP_XML_Tag_Processor( "<?xml version='1.0' encoding='UTF-8' ?>" );
		$this->assertTrue( $processor->next_token(), 'The XML declaration was not found.' );
		$this->assertEquals(
			'#xml-declaration',
			$processor->get_token_type(),
			'The XML declaration was not correctly identified.'
		);
		$this->assertEquals( '1.0', $processor->get_attribute( 'version' ), 'The version attribute was not correctly captured.' );
		$this->assertEquals( 'UTF-8', $processor->get_attribute( 'encoding' ), 'The encoding attribute was not correctly captured.' );
	}

	/**
	 *
	 * @ticket 61365
	 */
	public function test_processor_instructions() {
		$processor = new WP_XML_Tag_Processor(
			// The first <?xml tag is an xml declaration.
			'<?xml version="1.0" encoding="UTF-8" ?>' .
			// The second <?xml tag is a processing instruction.
			'<?xml stylesheet type="text/xsl" href="style.xsl" ?>'
		);
		$this->assertTrue( $processor->next_token(), 'The XML declaration was not found.' );
		$this->assertTrue( $processor->next_token(), 'The processing instruction was not found.' );
		$this->assertEquals(
			'#processing-instructions',
			$processor->get_token_type(),
			'The processing instruction was not correctly identified.'
		);
		$this->assertEquals( ' stylesheet type="text/xsl" href="style.xsl" ', $processor->get_modifiable_text(), 'The modifiable text was not correctly captured.' );
	}

	/**
	 * Ensures that updates which are enqueued in front of the cursor
	 * are applied before moving forward in the document.
	 *
	 * @ticket 61365
	 */
	public function test_applies_updates_before_proceeding() {
		$xml = '<root><wp:content><photo/></wp:content><wp:content><photo/></wp:content></root>';

		$subclass = new class( $xml ) extends WP_XML_Tag_Processor {
			/**
			 * Inserts raw text after the current token.
			 *
			 * @param string $new_xml Raw text to insert.
			 */
			public function insert_after( $new_xml ) {
				$this->set_bookmark( 'here' );
				$this->lexical_updates[] = new WP_HTML_Text_Replacement(
					$this->bookmarks['here']->start + $this->bookmarks['here']->length,
					0,
					$new_xml
				);
			}
		};

		$subclass->next_tag( 'photo' );
		$subclass->insert_after( '<p>snow-capped</p>' );

		$subclass->next_tag();
		$this->assertSame(
			'p',
			$subclass->get_tag(),
			'Should have matched inserted XML as next tag.'
		);

		$subclass->next_tag( 'photo' );
		$subclass->set_attribute( 'alt', 'mountain' );

		$this->assertSame(
			'<root><wp:content><photo/><p>snow-capped</p></wp:content><wp:content><photo alt="mountain"/></wp:content></root>',
			$subclass->get_updated_xml(),
			'Should have properly applied the update from in front of the cursor.'
		);
	}
}
