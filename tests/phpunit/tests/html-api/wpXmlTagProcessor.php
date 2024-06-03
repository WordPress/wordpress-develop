<?php
/**
 * Unit tests covering WP_XML_Tag_Processor functionality.
 *
 * @package WordPress
 * @subpackage XML-API
 */

$base_dir = __DIR__ . '/../../../../src/wp-includes/html-api';
require_once $base_dir . "/class-wp-html-token.php";
require_once $base_dir . "/class-wp-html-span.php";
require_once $base_dir . "/class-wp-html-text-replacement.php";
require_once $base_dir . "/class-wp-html-decoder.php";
require_once $base_dir . "/class-wp-html-attribute-token.php";
require_once $base_dir . "/class-wp-xml-tag-processor.php";

/**
 * @group xml-api
 *
 * @coversDefaultClass WP_XML_Tag_Processor
 */
class Tests_XmlApi_WpXmlTagProcessor extends PHPUnit_Framework_TestCase {
	const XML_SIMPLE       = '<div id="first"><span id="second">Text</span></div>';
	const XML_WITH_CLASSES = '<div class="main with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>';
	const XML_MALFORMED    = '<div><span class="d-md-none" Notifications</span><span class="d-none d-md-inline">Back to notifications</span></div>';

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_null_before_finding_tags() {
		$processor = new WP_XML_Tag_Processor( '<div>Test</div>' );

		$this->assertNull( $processor->get_tag(), 'Calling get_tag() without selecting a tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_null_when_not_in_open_tag() {
		$processor = new WP_XML_Tag_Processor( '<div>Test</div>' );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertNull( $processor->get_tag(), 'Accessing a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_open_tag_name() {
		$processor = new WP_XML_Tag_Processor( '<div>Test</div>' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertSame( 'div', $processor->get_tag(), 'Accessing an existing tag name did not return "div"' );
	}

	/**
	 * @ticket 58009
	 *
	 * @covers WP_XML_Tag_Processor::has_self_closing_flag
	 *
	 * @dataProvider data_has_self_closing_flag
	 *
	 * @param string $xml Input XML whose first tag might contain the self-closing flag `/`.
	 * @param bool $flag_is_set Whether the input XML's first tag contains the self-closing flag.
	 */
	public function test_has_self_closing_flag_matches_input_xml( $xml, $flag_is_set ) {
		$processor = new WP_XML_Tag_Processor( $xml );
		$processor->next_tag( array( 'tag_closers' => 'visit' ) );

		if ( $flag_is_set ) {
			$this->assertTrue( $processor->is_empty_element_tag(), 'Did not find the empty element tag when it was present.' );
		} else {
			$this->assertFalse( $processor->is_empty_element_tag(), 'Found the empty element tag when it was absent.' );
		}
	}

	/**
	 * Data provider. XML tags which might have a self-closing flag, and an indicator if they do.
	 *
	 * @return array[]
	 */
	public static function data_has_self_closing_flag() {
		return array(
			// These should not have a self-closer, and will leave an element un-closed if it's assumed they are self-closing.
			'Self-closing flag on non-void XML element' => array( '<div />', true ),
//			'No self-closing flag on non-void XML element' => array( '<div>', false ),
//			// These should not have a self-closer, but are benign when used because the elements are void.
//			'Self-closing flag on void XML element'     => array( '<img />', true ),
//			'No self-closing flag on void XML element'  => array( '<img>', false ),
//			'Self-closing flag on void XML element without spacing' => array( '<img/>', true ),
//			// These should not have a self-closer, but as part of a tag closer they are entirely ignored.
//			'Self-closing flag on tag closer'            => array( '</textarea />', true ),
//			'No self-closing flag on tag closer'         => array( '</textarea>', false ),
//			// These can and should have self-closers, and will leave an element un-closed if it's assumed they aren't self-closing.
//			'Self-closing flag on a foreign element'     => array( '<circle />', true ),
//			'No self-closing flag on a foreign element'  => array( '<circle>', false ),
//			// These involve syntax peculiarities.
//			'Self-closing flag after extra spaces'       => array( '<div      />', true ),
//			'Self-closing flag after attribute'          => array( '<div id=test/>', true ),
//			'Self-closing flag after quoted attribute'   => array( '<div id="test"/>', true ),
//			'Self-closing flag after boolean attribute'  => array( '<div enabled/>', true ),
//			'Boolean attribute that looks like a self-closer' => array( '<div / >', false ),
		);
	}


	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_not_in_open_tag() {
		$processor = new WP_XML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertNull( $processor->get_attribute( 'class' ), 'Accessing an attribute of a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_in_closing_tag() {
		$processor = new WP_XML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), 'Querying an existing closing tag did not return true' );
		$this->assertNull( $processor->get_attribute( 'class' ), 'Accessing an attribute of a closing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_attribute_missing() {
		$processor = new WP_XML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertNull( $processor->get_attribute( 'test-id' ), 'Accessing a non-existing attribute did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_attributes_are_rejected_in_tag_closers() {
		$processor = new WP_XML_Tag_Processor( '<div>Test</div class="test">' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertFalse( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), 'Querying an existing but invalid closing tag did not return false.' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_attribute_value() {
		$processor = new WP_XML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertSame( 'test', $processor->get_attribute( 'class' ), 'Accessing a class="test" attribute value did not return "test"' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_no_value() {
		$processor = new WP_XML_Tag_Processor( '<div enabled class="test">Test</div>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_no_quotes() {
		$processor = new WP_XML_Tag_Processor( '<div enabled=1 class="test">Test</div>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_contains_ampersand() {
		$processor = new WP_XML_Tag_Processor( '<div enabled="the &quot;grande">Test</div>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}
	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_value_contains_lt_character() {
		$processor = new WP_XML_Tag_Processor( '<div enabled="I love <3 this">Test</div>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_tags_duplicate_attributes() {
		$processor = new WP_XML_Tag_Processor( '<div id="update-me" id="ignored-id"><span id="second">Text</span></div>' );

		$this->assertFalse($processor->next_tag());
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 */
	public function test_parsing_stops_on_malformed_attribute_name_contains_slash() {
		$processor = new WP_XML_Tag_Processor( '<div a/b="test">Test</div>' );

		$this->assertFalse( $processor->next_tag(), 'Querying a malformed start tag did not return false' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute
	 *
	 * @param string $attribute_name Name of data-enabled attribute with case variations.
	 */
	public function test_get_attribute_is_case_sensitive( ) {
		$processor = new WP_XML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
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
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_is_case_sensitive() {
		$processor = new WP_XML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
		$processor->next_tag();
		$processor->remove_attribute( 'data-enabled' );

		$this->assertSame( '<div DATA-enabled="true">Test</div>', $processor->get_updated_xml(), 'A case-sensitive remove_attribute call did remove the attribute' );

		$processor->remove_attribute( 'DATA-enabled' );

		$this->assertSame( '<div >Test</div>', $processor->get_updated_xml(), 'A case-sensitive remove_attribute call did not remove the attribute' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_is_case_sensitive() {
		$processor = new WP_XML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
		$processor->next_tag();
		$processor->set_attribute( 'data-enabled', 'abc' );

		$this->assertSame( '<div data-enabled="abc" DATA-enabled="true">Test</div>', $processor->get_updated_xml(), 'A case-insensitive set_attribute call did not update the existing attribute' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_before_finding_tags() {
		$processor = new WP_XML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$this->assertNull(
			$processor->get_attribute_names_with_prefix( 'data-' ),
			'Accessing attributes by their prefix did not return null when no tag was selected'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_when_not_in_open_tag() {
		$processor = new WP_XML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$processor->next_tag( 'p' );
		$this->assertNull( $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing attributes of a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_when_in_closing_tag() {
		$processor = new WP_XML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$processor->next_tag( 'div' );
		$processor->next_tag( array( 'tag_closers' => 'visit' ) );

		$this->assertNull( $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing attributes of a closing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_empty_array_when_no_attributes_present() {
		$processor = new WP_XML_Tag_Processor( '<div>Test</div>' );
		$processor->next_tag( 'div' );

		$this->assertSame( array(), $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing the attributes on a tag without any did not return an empty array' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_matching_attribute_names_in_original_case() {
		$processor = new WP_XML_Tag_Processor( '<div DATA-enabled="yes" class="test" data-test-ID="14">Test</div>' );
		$processor->next_tag();

		$this->assertSame(
			array( 'data-test-ID' ),
			$processor->get_attribute_names_with_prefix( 'data-' ),
			'Accessing attributes by their prefix did not return their lowercase names'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_attribute_added_by_set_attribute() {
		$processor = new WP_XML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$processor->next_tag();
		$processor->set_attribute( 'data-test-id', '14' );

		$this->assertSame(
			'<div data-test-id="14" data-foo="bar">Test</div>',
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
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::__toString
	 */
	public function test_to_string_returns_updated_xml() {
		$processor = new WP_XML_Tag_Processor( '<hr id="remove" /><div enabled="yes" class="test">Test</div><span id="span-id"></span>' );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$processor->next_tag();
		$processor->set_attribute( 'id', 'div-id-1' );

		$this->assertSame(
			$processor->get_updated_xml(),
			(string) $processor,
			'get_updated_xml() returned a different value than __toString()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::get_updated_xml
	 */
	public function test_get_updated_xml_applies_the_updates_so_far_and_keeps_the_processor_on_the_current_tag() {
		$processor = new WP_XML_Tag_Processor( '<hr id="remove" /><div enabled="yes" class="test">Test</div><span id="span-id"></span>' );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$processor->next_tag();
		$processor->set_attribute( 'id', 'div-id-1' );

		$this->assertSame(
			'<hr  /><div id="div-id-1" enabled="yes" class="test">Test</div><span id="span-id"></span>',
			$processor->get_updated_xml(),
			'Calling get_updated_xml after updating the attributes of the second tag returned different XML than expected'
		);

		$processor->set_attribute( 'id', 'div-id-2' );

		$this->assertSame(
			'<hr  /><div id="div-id-2" enabled="yes" class="test">Test</div><span id="span-id"></span>',
			$processor->get_updated_xml(),
			'Calling get_updated_xml after updating the attributes of the second tag for the second time returned different XML than expected'
		);

		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertSame(
			'<hr  /><div id="div-id-2" enabled="yes" class="test">Test</div><span ></span>',
			$processor->get_updated_xml(),
			'Calling get_updated_xml after removing the id attribute of the third tag returned different XML than expected'
		);
	}

	/**
	 * @ticket 56299
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
	 * @ticket 58160
	 */
	public function test_get_updated_xml_applies_updates_to_content_after_seeking_to_before_parsed_bytes() {
		$processor = new WP_XML_Tag_Processor( '<div><img hidden></div>' );

		$processor->next_tag();
		$processor->set_attribute( 'wonky', 'true' );
		$processor->next_tag();
		$processor->set_bookmark( 'here' );

		$processor->next_tag( array( 'tag_closers' => 'visit' ) );
		$processor->seek( 'here' );

		$this->assertSame( '<div wonky="true"><img hidden></div>', $processor->get_updated_xml() );
	}

	/**
	 * Ensures that bookmarks start and length correctly describe a given token in XML.
	 *
	 * @ticket 61301
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
			'DIV start tag'                 => array( '<div>', 1, '<div>' ),
			'DIV start tag with attributes' => array( '<div class="x" disabled="yes">', 1, '<div class="x" disabled="yes">' ),
			'DIV end tag'                   => array( '</div>', 1, '</div>' ),
			'DIV end tag with attributes'   => array( '</div class="x" disabled="yes">', 1, '</div class="x" disabled="yes">' ),
			'Nested DIV'                    => array( '<div><div b="yes">', 2, '<div b="yes">' ),
			'Sibling DIV'                   => array( '<div></div><div b="yes">', 3, '<div b="yes">' ),
			'DIV after text'                => array( 'text <div>', 2, '<div>' ),
			'DIV before text'               => array( '<div> text', 1, '<div>' ),
			// 'DIV after comment'             => array( '<!-- comment --><div>', 2, '<div>' ),
			'DIV before comment'            => array( '<div><!-- c --> ', 1, '<div>' ),
			'Start "self-closing" tag'      => array( '<div />', 1, '<div />' ),
			'Void tag'                      => array( '<img src="img.png">', 1, '<img src="img.png">' ),
			'Void tag w/self-closing flag'  => array( '<img src="img.png" />', 1, '<img src="img.png" />' ),
			'Void tag inside DIV'           => array( '<div><img src="img.png"></div>', 2, '<img src="img.png">' ),

			// Text.
			'Text'                          => array( 'Just text', 1, 'Just text' ),
			'Text in DIV'                   => array( '<div>Text<div>', 2, 'Text' ),
			'Text before DIV'               => array( 'Text<div>', 1, 'Text' ),
			'Text after DIV'                => array( '<div></div>Text', 3, 'Text' ),
			// 'Text after comment'            => array( '<!-- comment -->Text', 2, 'Text' ),
			'Text before comment'           => array( 'Text<!-- c --> ', 1, 'Text' ),

			// Comments.
			// 'Comment'                       => array( '<!-- comment -->', 1, '<!-- comment -->' ),
			// 'Comment in DIV'                => array( '<div><!-- comment --><div>', 2, '<!-- comment -->' ),
			// 'Comment before DIV'            => array( '<!-- comment --><div>', 1, '<!-- comment -->' ),
			// 'Comment after DIV'             => array( '<div></div><!-- comment -->', 3, '<!-- comment -->' ),
			// 'Comment after comment'         => array( '<!-- comment --><!-- comment -->', 2, '<!-- comment -->' ),
			// 'Comment before comment'        => array( '<!-- comment --><!-- c --> ', 1, '<!-- comment -->' ),
			// 'Abruptly closed comment'       => array( '<!-->', 1, '<!-->' ),
			// 'Empty comment'                 => array( '<!---->', 1, '<!---->' ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_next_tag_with_no_arguments_should_find_the_next_existing_tag() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );

		$this->assertTrue( $processor->next_tag(), 'Querying an existing tag did not return true' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_next_tag_should_return_false_for_a_non_existing_tag() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
	}

	/**
	 * @ticket 56299
	 * 
	 * @covers WP_XML_Tag_Processor::next_tag
	 * @covers WP_XML_Tag_Processor::get_breadcrumbs
	 */
	public function test_breadcrumbs()
	{
		$processor = new WP_XML_Tag_Processor( <<<XML
			<div>
				<span>
					<img />
				</span>
			</div>
			XML
		);
		$processor->next_tag(array('tag_closers' => 'visit'));
		$this->assertEquals(
			array('div'),
			$processor->get_breadcrumbs(),
			'get_breadcrumbs() did not return the expected breadcrumbs'
		);

		$processor->next_tag(array('tag_closers' => 'visit'));
		$this->assertEquals(
			array('div', 'span'),
			$processor->get_breadcrumbs(),
			'get_breadcrumbs() did not return the expected breadcrumbs'
		);

		$processor->next_tag(array('tag_closers' => 'visit'));
		$this->assertEquals(
			array('div', 'span', 'img'),
			$processor->get_breadcrumbs(),
			'get_breadcrumbs() did not return the expected breadcrumbs'
		);

		$processor->next_tag(array('tag_closers' => 'visit'));
		$this->assertEquals(
			array('div'),
			$processor->get_breadcrumbs(),
			'get_breadcrumbs() did not return the expected breadcrumbs'
		);

		$processor->next_tag(array('tag_closers' => 'visit'));
		$this->assertEquals(
			array(),
			$processor->get_breadcrumbs(),
			'get_breadcrumbs() did not return the expected breadcrumbs'
		);

		$this->assertFalse($processor->next_token());
	}

	/**
	 * @ticket 57852
	 *
	 * @covers WP_XML_Tag_Processor::get_modifiable_text
	 */
	public function test_normalizes_carriage_returns_in_text_nodes()
	{
		$processor = new WP_XML_Tag_Processor(
			"<div>We are\rnormalizing\r\n\nthe\n\r\r\r\ncarriage returns"
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
	 * @ticket 57852
	 *
	 * @covers WP_XML_Tag_Processor::get_modifiable_text
	 */
	public function test_normalizes_carriage_returns_in_cdata()
	{
		$processor = new WP_XML_Tag_Processor(
			"<div><![CDATA[We are\rnormalizing\r\n\nthe\n\r\r\r\ncarriage returns]]>"
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
	 * @ticket 56299
	 * @ticket 57852
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 * @covers WP_XML_Tag_Processor::is_tag_closer
	 */
	public function test_next_tag_should_stop_on_closers_only_when_requested() {
		$processor = new WP_XML_Tag_Processor( '<div><img /></div>' );

		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'div' ) ), 'Did not find desired tag opener' );
		$this->assertFalse( $processor->next_tag( array( 'tag_name' => 'div' ) ), 'Visited an unwanted tag, a tag closer' );

		$processor = new WP_XML_Tag_Processor( '<div><img /></div>' );
		$processor->next_tag(
			array(
				'tag_name'    => 'div',
				'tag_closers' => 'visit',
			)
		);

		$this->assertFalse( $processor->is_tag_closer(), 'Indicated a tag opener is a tag closer' );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'    => 'div',
					'tag_closers' => 'visit',
				)
			),
			'Did not stop at desired tag closer'
		);
		$this->assertTrue( $processor->is_tag_closer(), 'Indicated a tag closer is a tag opener' );

		$processor = new WP_XML_Tag_Processor( '<div>' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), "Did not find a tag opener when tag_closers was set to 'visit'" );
		$this->assertFalse( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), "Found a closer where there wasn't one" );
	}

	/**
	 * Verifies that updates to a document before calls to `get_updated_xml()` don't
	 * lead to the Tag Processor jumping to the wrong tag after the updates.
	 *
	 * @ticket 58179
	 *
	 * @covers WP_XML_Tag_Processor::get_updated_xml
	 */
	public function test_internal_pointer_returns_to_original_spot_after_inserting_content_before_cursor() {
		$tags = new WP_XML_Tag_Processor( '<div>outside</div><section><div><img>inside</div></section>' );

		$tags->next_tag();
		$tags->set_attribute( 'class', 'foo' );
		$tags->next_tag( 'section' );

		// Return to this spot after moving ahead.
		$tags->set_bookmark( 'here' );

		// Move ahead.
		$tags->next_tag( 'img' );
		$tags->seek( 'here' );
		$this->assertSame( '<div class="foo">outside</div><section><div><img>inside</div></section>', $tags->get_updated_xml() );
		$this->assertSame( 'section', $tags->get_tag() );
		$this->assertFalse( $tags->is_tag_closer() );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_on_a_non_existing_tag_does_not_change_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertFalse( $processor->next_tag( 'div' ), 'Querying a non-existing tag did not return false' );

		$processor->set_attribute( 'id', 'primary' );

		$this->assertSame(
			self::XML_SIMPLE,
			$processor->get_updated_xml(),
			'Calling get_updated_xml after updating a non-existing tag returned an XML that was different from the original XML'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 * @covers WP_XML_Tag_Processor::remove_attribute
	 * @covers WP_XML_Tag_Processor::add_class
	 * @covers WP_XML_Tag_Processor::remove_class
	 */
	public function test_attribute_ops_on_tag_closer_do_not_change_the_markup() {
		$processor = new WP_XML_Tag_Processor( '<div id="3"></div>' );
		$processor->next_tag(
			array(
				'tag_name'    => 'div',
				'tag_closers' => 'visit',
			)
		);

		$this->assertFalse( $processor->is_tag_closer(), 'Skipped tag opener' );

		$processor->next_tag(
			array(
				'tag_name'    => 'div',
				'tag_closers' => 'visit',
			)
		);

		$this->assertTrue( $processor->is_tag_closer(), 'Skipped tag closer' );
		$this->assertFalse( $processor->set_attribute( 'id', 'test' ), "Allowed setting an attribute on a tag closer when it shouldn't have" );
		$this->assertFalse( $processor->remove_attribute( 'invalid-id' ), "Allowed removing an attribute on a tag closer when it shouldn't have" );
		$this->assertSame(
			'<div id="3"></div>',
			$processor->get_updated_xml(),
			'Calling get_updated_xml after updating a non-existing tag returned an XML that was different from the original XML'
		);
	}


	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_a_non_existing_attribute_adds_a_new_attribute_to_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-attribute', 'test-value' );

		$this->assertSame(
			'<div test-attribute="test-value" id="first"><span id="second">Text</span></div>',
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
	 * @ticket 56299
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
			'<div test-attribute="test-value" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_xml(),
			'Updated XML does not include attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 56299
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
			'<div test-ATTribute="test-value" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_xml(),
			'Updated XML does not include attribute added via set_attribute()'
		);
	}


	/**
	 * @ticket 56299
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
			'<div ><span id="second">Text</span></div>',
			$processor->get_updated_xml(),
			'Updated XML includes attribute that was removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
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
	 * @ticket 56299
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
			'<div ><span id="second">Text</span></div>',
			$processor->get_updated_xml(),
			'Updated XML includes attribute that was overwritten by set_attribute() and then removed by remove_attribute()'
		);
	}
	
	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_an_existing_attribute_name_updates_its_value_in_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'id', 'new-id' );
		$this->assertSame(
			'<div id="new-id"><span id="second">Text</span></div>',
			$processor->get_updated_xml(),
			'Existing attribute was not updated'
		);
	}

	/**
	 * Ensures that when setting an attribute multiple times that only
	 * one update flushes out into the updated XML.
	 *
	 * @ticket 58146
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_case_variants_updates_only_the_original_first_copy() {
		$processor = new WP_XML_Tag_Processor( '<div data-enabled="5">' );
		$processor->next_tag();
		$processor->set_attribute( 'data-enabled', 'canary1' );
		$processor->set_attribute( 'data-enabled', 'canary2' );
		$processor->set_attribute( 'data-enabled', 'canary3' );

		$this->assertSame( '<div data-enabled="canary3">', strtolower( $processor->get_updated_xml() ) );
	}

	/**
	 * @ticket 56299
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
			'<div data-foo="bar" id="first"><span data-foo="bar" id="second">Text</span></div>',
			$processor->get_updated_xml(),
			'Not all tags were updated when looping with next_tag() and set_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_with_an_existing_attribute_name_removes_it_from_the_markup() {
		$processor = new WP_XML_Tag_Processor( self::XML_SIMPLE );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertSame(
			'<div ><span id="second">Text</span></div>',
			$processor->get_updated_xml(),
			'Attribute was not removed'
		);
	}

	/**
	 * @ticket 56299
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
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 */
	public function test_correctly_parses_xml_attributes_wrapped_in_single_quotation_marks() {
		$processor = new WP_XML_Tag_Processor(
			'<div id=\'first\'><span id=\'second\'>Text</span></div>'
		);
		$processor->next_tag(
			array(
				'tag_name' => 'div',
				'id'       => 'first',
			)
		);
		$processor->remove_attribute( 'id' );
		$processor->next_tag(
			array(
				'tag_name' => 'span',
				'id'       => 'second',
			)
		);
		$processor->set_attribute( 'id', 'single-quote' );
		$this->assertSame(
			'<div ><span id="single-quote">Text</span></div>',
			$processor->get_updated_xml(),
			'Did not remove single-quoted attribute'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_setting_an_attribute_to_false_is_rejected() {
		$processor = new WP_XML_Tag_Processor(
			'<form action="/action_page.php"><input checked type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>'
		);
		$processor->next_tag( 'input' );
		$this->assertFalse(
			$processor->set_attribute('checked', false),
			'Accepted a boolean attribute name.'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_XML_Tag_Processor::set_attribute
	 */
	public function test_setting_a_missing_attribute_to_false_does_not_change_the_markup() {
		$xml_input = '<form action="/action_page.php"><input type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>';
		$processor  = new WP_XML_Tag_Processor( $xml_input );
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
	 * @ticket 58007
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
			'Shortest open valid comment'      => array( '<!--' ),
			'Basic truncated comment'          => array( '<!-- this ends --' ),
		);
	}

	/**
	 * Ensures that no tags are matched in a document containing only non-tag content.
	 *
	 * @ticket 60122
	 *
	 * @covers WP_XML_Tag_Processor::next_tag
	 * @covers WP_XML_Tag_Processor::paused_at_incomplete_token
	 *
	 * @dataProvider data_xml_without_tags
	 *
	 * @param string $xml_without_tags XML without any tags in it.
	 */
	public function test_next_tag_returns_false_when_there_are_no_tags( $xml_without_tags ) {
		$processor = new WP_XML_Tag_Processor( $xml_without_tags );

		$this->assertFalse(
			$processor->next_tag(),
			"Shouldn't have found any tags but found {$processor->get_tag()}."
		);

		// $this->assertFalse(
		// 	$processor->paused_at_incomplete_token(),
		// 	'Should have indicated that end of document was reached without evidence that elements were truncated.'
		// );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_xml_without_tags() {
		return array(
			'DOCTYPE declaration'    => array( '<!DOCTYPE xml>Just some XML' ),
			'No tags'                => array( 'this is nothing more than a text node' ),
			'Text with comments'     => array( 'One <!-- sneaky --> comment.' ),
			'CDATA as XML comment'  => array( '<![CDATA[this closes at the first &gt;]]> ay' ),
		);
	}

	/**
	 * Ensures that the processor doesn't attempt to match an incomplete token.
	 *
	 * @ticket 58637
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
			'Incomplete tag name'                  => array( '<swit' ),
			// 'Incomplete tag (no attributes)'       => array( '<div' ),
			// 'Incomplete tag (attributes)'          => array( '<div inert title="test"' ),
			'Incomplete attribute (unquoted)'      => array( '<button disabled' ),
			'Incomplete attribute (single quoted)' => array( "<li class='just-another class" ),
			'Incomplete attribute (double quoted)' => array( '<iframe src="https://www.example.com/embed/abcdef' ),
			// 'Incomplete comment (normative)'       => array( '<!-- without end' ),
			// 'Incomplete comment (missing --)'      => array( '<!-- without end --' ),
			// 'Incomplete comment (--!)'             => array( '<!-- without end --!' ),
			// 'Incomplete DOCTYPE'                   => array( '<!DOCTYPE xml' ),
			// 'Partial DOCTYPE'                      => array( '<!DOCTY' ),
			// 'Incomplete CDATA'                     => array( '<![CDATA[something inside of here needs to get out' ),
			// 'Partial CDATA'                        => array( '<![CDA' ),
			// 'Partially closed CDATA]'              => array( '<![CDATA[cannot escape]' ),
			// 'Unclosed IFRAME'                      => array( '<iframe><div>' ),
			// 'Unclosed NOEMBED'                     => array( '<noembed><div>' ),
			// 'Unclosed NOFRAMES'                    => array( '<noframes><div>' ),
			// 'Unclosed SCRIPT'                      => array( '<script><div>' ),
			// 'Unclosed STYLE'                       => array( '<style><div>' ),
			// 'Unclosed TEXTAREA'                    => array( '<textarea><div>' ),
			// 'Unclosed TITLE'                       => array( '<title><div>' ),
			// 'Unclosed XMP'                         => array( '<xmp><div>' ),
			// 'Partially closed IFRAME'              => array( '<iframe><div></iframe' ),
			// 'Partially closed NOEMBED'             => array( '<noembed><div></noembed' ),
			// 'Partially closed NOFRAMES'            => array( '<noframes><div></noframes' ),
			// 'Partially closed SCRIPT'              => array( '<script><div></script' ),
			// 'Partially closed STYLE'               => array( '<style><div></style' ),
			// 'Partially closed TEXTAREA'            => array( '<textarea><div></textarea' ),
			// 'Partially closed TITLE'               => array( '<title><div></title' ),
			// 'Partially closed XMP'                 => array( '<xmp><div></xmp' ),
		);
	}

	/**
	 * The string " -- " (double-hyphen) must not occur within comments.
	 * 
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
	 * Ensures that non-tag syntax starting with `<` is rejected.
	 *
	 * @ticket 60385
	 */
	public function test_single_text_node_with_taglike_text() {
		$processor = new WP_XML_Tag_Processor( 'This is a text node< /A>' );
		$this->assertTrue($processor->next_token(), 'A valid text node was not found.');
		$this->assertEquals('This is a text node', $processor->get_modifiable_text(), 'The contents of a valid text node were not correctly captured.');
		$this->assertFalse($processor->next_tag(), 'A malformed XML markup was not rejected.');
	}

	/**
	 * Ensures that non-tag syntax starting with `<` is rejected.
	 *
	 * @ticket 60385
	 */
	public function test_parses_CDATA() {
		$processor = new WP_XML_Tag_Processor( '<![CDATA[This is a CDATA text node.]]>' );
		$this->assertTrue($processor->next_token(), 'The first text node was not found.');		$this->assertEquals(
			'This is a CDATA text node.',
			$processor->get_modifiable_text(),
			'The contents of a a CDATA text node were not correctly captured.'
		);
	}

	/**
	 * @ticket 60385
	 */
	public function test_yields_CDATA_a_separate_text_node() {
		$processor = new WP_XML_Tag_Processor( 'This is the first text node <![CDATA[ and this is a second text node ]]> and this is the third text node.' );

		$this->assertTrue($processor->next_token(), 'The first text node was not found.');
		$this->assertEquals(
			'This is the first text node ',
			$processor->get_modifiable_text(),
			'The contents of a valid text node were not correctly captured.'
		);

		$this->assertTrue($processor->next_token(), 'The CDATA text node was not found.');
		$this->assertEquals(
			' and this is a second text node ',
			$processor->get_modifiable_text(),
			'The contents of a a CDATA text node were not correctly captured.'
		);

		$this->assertTrue($processor->next_token(), 'The text node was not found.');
		$this->assertEquals(
			' and this is the third text node.',
			$processor->get_modifiable_text(),
			'The contents of a valid text node were not correctly captured.'
		);
	}

	/**
	 * 
	 * @return void
	 */
	public function test_xml_declaration()
	{
		$processor = new WP_XML_Tag_Processor( '<?xml version="1.0" encoding="UTF-8" ?>' );
		$this->assertTrue($processor->next_token(), 'The XML declaration was not found.');
		$this->assertEquals(
			'#xml-declaration',
			$processor->get_token_type(),
			'The XML declaration was not correctly identified.'
		);
		$this->assertEquals('1.0', $processor->get_attribute('version'), 'The version attribute was not correctly captured.');
		$this->assertEquals('UTF-8', $processor->get_attribute('encoding'), 'The encoding attribute was not correctly captured.');
	}

	/**
	 * 
	 * @return void
	 */
	public function test_processor_instructions()
	{
		$processor = new WP_XML_Tag_Processor( 
			// The first <?xml tag is an xml declaration.
			'<?xml version="1.0" encoding="UTF-8" ?>' .
			// The second <?xml tag is a processing instruction.
			'<?xml stylesheet type="text/xsl" href="style.xsl" ?>'
		);
		$this->assertTrue($processor->next_token(), 'The XML declaration was not found.');
		$this->assertTrue($processor->next_token(), 'The processing instruction was not found.');
		$this->assertEquals(
			'#processing-instructions',
			$processor->get_token_type(),
			'The processing instruction was not correctly identified.'
		);
		$this->assertEquals(' stylesheet type="text/xsl" href="style.xsl" ', $processor->get_modifiable_text(), 'The modifiable text was not correctly captured.');
	}

	/**
	 * Ensures that updates which are enqueued in front of the cursor
	 * are applied before moving forward in the document.
	 *
	 * @ticket 60697
	 */
	public function test_applies_updates_before_proceeding() {
		$xml = '<div><img/></div><div><img/></div>';

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

		$subclass->next_tag( 'img' );
		$subclass->insert_after( '<p>snow-capped</p>' );

		$subclass->next_tag();
		$this->assertSame(
			'p',
			$subclass->get_tag(),
			'Should have matched inserted XML as next tag.'
		);

		$subclass->next_tag( 'img' );
		$subclass->set_attribute( 'alt', 'mountain' );

		$this->assertSame(
			'<div><img/><p>snow-capped</p></div><div><img alt="mountain"/></div>',
			$subclass->get_updated_xml(),
			'Should have properly applied the update from in front of the cursor.'
		);
	}
}
