<?php
/**
 * Unit tests covering WP_HTML_Tag_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 */

/**
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Tag_Processor
 */
class Tests_HtmlApi_WpHtmlTagProcessor extends WP_UnitTestCase {
	const HTML_SIMPLE       = '<div id="first"><span id="second">Text</span></div>';
	const HTML_WITH_CLASSES = '<div class="main with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>';
	const HTML_MALFORMED    = '<div><span class="d-md-none" Notifications</span><span class="d-none d-md-inline">Back to notifications</span></div>';

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_null_before_finding_tags() {
		$processor = new WP_HTML_Tag_Processor( '<div>Test</div>' );

		$this->assertNull( $processor->get_tag(), 'Calling get_tag() without selecting a tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_null_when_not_in_open_tag() {
		$processor = new WP_HTML_Tag_Processor( '<div>Test</div>' );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertNull( $processor->get_tag(), 'Accessing a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_open_tag_name() {
		$processor = new WP_HTML_Tag_Processor( '<div>Test</div>' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertSame( 'DIV', $processor->get_tag(), 'Accessing an existing tag name did not return "div"' );
	}

	/**
	 * @ticket 58009
	 *
	 * @covers WP_HTML_Tag_Processor::has_self_closing_flag
	 *
	 * @dataProvider data_has_self_closing_flag
	 *
	 * @param string $html Input HTML whose first tag might contain the self-closing flag `/`.
	 * @param bool $flag_is_set Whether the input HTML's first tag contains the self-closing flag.
	 */
	public function test_has_self_closing_flag_matches_input_html( $html, $flag_is_set ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag( array( 'tag_closers' => 'visit' ) );

		if ( $flag_is_set ) {
			$this->assertTrue( $processor->has_self_closing_flag(), 'Did not find the self-closing tag when it was present.' );
		} else {
			$this->assertFalse( $processor->has_self_closing_flag(), 'Found the self-closing tag when it was absent.' );
		}
	}

	/**
	 * Data provider. HTML tags which might have a self-closing flag, and an indicator if they do.
	 *
	 * @return array[]
	 */
	public static function data_has_self_closing_flag() {
		return array(
			// These should not have a self-closer, and will leave an element un-closed if it's assumed they are self-closing.
			'Self-closing flag on non-void HTML element' => array( '<div />', true ),
			'No self-closing flag on non-void HTML element' => array( '<div>', false ),
			// These should not have a self-closer, but are benign when used because the elements are void.
			'Self-closing flag on void HTML element'     => array( '<img />', true ),
			'No self-closing flag on void HTML element'  => array( '<img>', false ),
			'Self-closing flag on void HTML element without spacing' => array( '<img/>', true ),
			// These should not have a self-closer, but as part of a tag closer they are entirely ignored.
			'Self-closing flag on tag closer'            => array( '</textarea />', true ),
			'No self-closing flag on tag closer'         => array( '</textarea>', false ),
			// These can and should have self-closers, and will leave an element un-closed if it's assumed they aren't self-closing.
			'Self-closing flag on a foreign element'     => array( '<circle />', true ),
			'No self-closing flag on a foreign element'  => array( '<circle>', false ),
			// These involve syntax peculiarities.
			'Self-closing flag after extra spaces'       => array( '<div      />', true ),
			'Self-closing flag after attribute'          => array( '<div id=test/>', true ),
			'Self-closing flag after quoted attribute'   => array( '<div id="test"/>', true ),
			'Self-closing flag after boolean attribute'  => array( '<div enabled/>', true ),
			'Boolean attribute that looks like a self-closer' => array( '<div / >', false ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_before_finding_tags() {
		$processor = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertNull( $processor->get_attribute( 'class' ), 'Accessing an attribute without selecting a tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_not_in_open_tag() {
		$processor = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertNull( $processor->get_attribute( 'class' ), 'Accessing an attribute of a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_in_closing_tag() {
		$processor = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), 'Querying an existing closing tag did not return true' );
		$this->assertNull( $processor->get_attribute( 'class' ), 'Accessing an attribute of a closing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_attribute_missing() {
		$processor = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertNull( $processor->get_attribute( 'test-id' ), 'Accessing a non-existing attribute did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_attribute_value() {
		$processor = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $processor->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertSame( 'test', $processor->get_attribute( 'class' ), 'Accessing a class="test" attribute value did not return "test"' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_true_for_boolean_attribute() {
		$processor = new WP_HTML_Tag_Processor( '<div enabled class="test">Test</div>' );

		$this->assertTrue( $processor->next_tag( array( 'class_name' => 'test' ) ), 'Querying an existing tag did not return true' );
		$this->assertTrue( $processor->get_attribute( 'enabled' ), 'Accessing a boolean "enabled" attribute value did not return true' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_string_for_truthy_attributes() {
		$processor = new WP_HTML_Tag_Processor( '<div enabled=enabled checked=1 hidden="true" class="test">Test</div>' );

		$this->assertTrue( $processor->next_tag(), 'Querying an existing tag did not return true' );
		$this->assertSame( 'enabled', $processor->get_attribute( 'enabled' ), 'Accessing a boolean "enabled" attribute value did not return true' );
		$this->assertSame( '1', $processor->get_attribute( 'checked' ), 'Accessing a checked=1 attribute value did not return "1"' );
		$this->assertSame( 'true', $processor->get_attribute( 'hidden' ), 'Accessing a hidden="true" attribute value did not return "true"' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_decodes_html_character_references() {
		$processor = new WP_HTML_Tag_Processor( '<div id="the &quot;grande&quot; is &lt; &#x033;&#50;oz&dagger;"></div>' );
		$processor->next_tag();

		$this->assertSame( 'the "grande" is < 32oz†', $processor->get_attribute( 'id' ), 'HTML Attribute value was returned without decoding character references' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_attributes_parser_treats_slash_as_attribute_separator() {
		$processor = new WP_HTML_Tag_Processor( '<div a/b/c/d/e="test">Test</div>' );

		$this->assertTrue( $processor->next_tag(), 'Querying an existing tag did not return true' );
		$this->assertTrue( $processor->get_attribute( 'a' ), 'Accessing an existing attribute did not return true' );
		$this->assertTrue( $processor->get_attribute( 'b' ), 'Accessing an existing attribute did not return true' );
		$this->assertTrue( $processor->get_attribute( 'c' ), 'Accessing an existing attribute did not return true' );
		$this->assertTrue( $processor->get_attribute( 'd' ), 'Accessing an existing attribute did not return true' );
		$this->assertSame( 'test', $processor->get_attribute( 'e' ), 'Accessing an existing e="test" did not return "test"' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 *
	 * @dataProvider data_attribute_name_case_variants
	 *
	 * @param string $attribute_name Name of data-enabled attribute with case variations.
	 */
	public function test_get_attribute_is_case_insensitive_for_attributes_with_values( $attribute_name ) {
		$processor = new WP_HTML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
		$processor->next_tag();

		$this->assertSame(
			'true',
			$processor->get_attribute( $attribute_name ),
			'Accessing an attribute by a differently-cased name did not return its value'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 *
	 * @dataProvider data_attribute_name_case_variants
	 *
	 * @param string $attribute_name Name of data-enabled attribute with case variations.
	 */
	public function test_attributes_parser_is_case_insensitive_for_attributes_without_values( $attribute_name ) {
		$processor = new WP_HTML_Tag_Processor( '<div DATA-enabled>Test</div>' );
		$processor->next_tag();

		$this->assertTrue(
			$processor->get_attribute( $attribute_name ),
			'Accessing an attribute by a differently-cased name did not return its value'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_attribute_name_case_variants() {
		return array(
			array( 'DATA-enabled' ),
			array( 'data-enabled' ),
			array( 'DATA-ENABLED' ),
			array( 'DatA-EnABled' ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_is_case_insensitive() {
		$processor = new WP_HTML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
		$processor->next_tag();
		$processor->remove_attribute( 'data-enabled' );

		$this->assertSame( '<div >Test</div>', $processor->get_updated_html(), 'A case-insensitive remove_attribute call did not remove the attribute' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_is_case_insensitive() {
		$processor = new WP_HTML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
		$processor->next_tag();
		$processor->set_attribute( 'data-enabled', 'abc' );

		$this->assertSame( '<div data-enabled="abc">Test</div>', $processor->get_updated_html(), 'A case-insensitive set_attribute call did not update the existing attribute' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_before_finding_tags() {
		$processor = new WP_HTML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$this->assertNull(
			$processor->get_attribute_names_with_prefix( 'data-' ),
			'Accessing attributes by their prefix did not return null when no tag was selected'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_when_not_in_open_tag() {
		$processor = new WP_HTML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$processor->next_tag( 'p' );
		$this->assertNull( $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing attributes of a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_when_in_closing_tag() {
		$processor = new WP_HTML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$processor->next_tag( 'div' );
		$processor->next_tag( array( 'tag_closers' => 'visit' ) );

		$this->assertNull( $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing attributes of a closing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_empty_array_when_no_attributes_present() {
		$processor = new WP_HTML_Tag_Processor( '<div>Test</div>' );
		$processor->next_tag( 'div' );

		$this->assertSame( array(), $processor->get_attribute_names_with_prefix( 'data-' ), 'Accessing the attributes on a tag without any did not return an empty array' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_matching_attribute_names_in_lowercase() {
		$processor = new WP_HTML_Tag_Processor( '<div DATA-enabled class="test" data-test-ID="14">Test</div>' );
		$processor->next_tag();

		$this->assertSame(
			array( 'data-enabled', 'data-test-id' ),
			$processor->get_attribute_names_with_prefix( 'data-' ),
			'Accessing attributes by their prefix did not return their lowercase names'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_attribute_added_by_set_attribute() {
		$processor = new WP_HTML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$processor->next_tag();
		$processor->set_attribute( 'data-test-id', '14' );

		$this->assertSame(
			'<div data-test-id="14" data-foo="bar">Test</div>',
			$processor->get_updated_html(),
			"Updated HTML doesn't include attribute added via set_attribute"
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
	 * @covers WP_HTML_Tag_Processor::__toString
	 */
	public function test_to_string_returns_updated_html() {
		$processor = new WP_HTML_Tag_Processor( '<hr id="remove" /><div enabled class="test">Test</div><span id="span-id"></span>' );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$processor->next_tag();
		$processor->set_attribute( 'id', 'div-id-1' );
		$processor->add_class( 'new_class_1' );

		$this->assertSame(
			$processor->get_updated_html(),
			(string) $processor,
			'get_updated_html() returned a different value than __toString()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_updated_html
	 */
	public function test_get_updated_html_applies_the_updates_so_far_and_keeps_the_processor_on_the_current_tag() {
		$processor = new WP_HTML_Tag_Processor( '<hr id="remove" /><div enabled class="test">Test</div><span id="span-id"></span>' );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$processor->next_tag();
		$processor->set_attribute( 'id', 'div-id-1' );
		$processor->add_class( 'new_class_1' );

		$this->assertSame(
			'<hr  /><div id="div-id-1" enabled class="test new_class_1">Test</div><span id="span-id"></span>',
			$processor->get_updated_html(),
			'Calling get_updated_html after updating the attributes of the second tag returned different HTML than expected'
		);

		$processor->set_attribute( 'id', 'div-id-2' );
		$processor->add_class( 'new_class_2' );

		$this->assertSame(
			'<hr  /><div id="div-id-2" enabled class="test new_class_1 new_class_2">Test</div><span id="span-id"></span>',
			$processor->get_updated_html(),
			'Calling get_updated_html after updating the attributes of the second tag for the second time returned different HTML than expected'
		);

		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertSame(
			'<hr  /><div id="div-id-2" enabled class="test new_class_1 new_class_2">Test</div><span ></span>',
			$processor->get_updated_html(),
			'Calling get_updated_html after removing the id attribute of the third tag returned different HTML than expected'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_updated_html
	 */
	public function test_get_updated_html_without_updating_any_attributes_returns_the_original_html() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );

		$this->assertSame(
			self::HTML_SIMPLE,
			$processor->get_updated_html(),
			'Casting WP_HTML_Tag_Processor to a string without performing any updates did not return the initial HTML snippet'
		);
	}

	/**
	 * Ensures that when seeking to an earlier spot in the document that
	 * all previously-enqueued updates are applied as they ought to be.
	 *
	 * @ticket 58160
	 */
	public function test_get_updated_html_applies_updates_to_content_after_seeking_to_before_parsed_bytes() {
		$processor = new WP_HTML_Tag_Processor( '<div><img hidden></div>' );

		$processor->next_tag();
		$processor->set_attribute( 'wonky', true );
		$processor->next_tag();
		$processor->set_bookmark( 'here' );

		$processor->next_tag( array( 'tag_closers' => 'visit' ) );
		$processor->seek( 'here' );

		$this->assertSame( '<div wonky><img hidden></div>', $processor->get_updated_html() );
	}

	/**
	 * Ensures that bookmarks start and length correctly describe a given token in HTML.
	 *
	 * @ticket 61301
	 *
	 * @dataProvider data_html_nth_token_substring
	 *
	 * @param string $html            Input HTML.
	 * @param int    $match_nth_token Which token to inspect from input HTML.
	 * @param string $expected_match  Expected full raw token bookmark should capture.
	 */
	public function test_token_bookmark_span( string $html, int $match_nth_token, string $expected_match ) {
		$processor = new class( $html ) extends WP_HTML_Tag_Processor {
			/**
			 * Returns the raw span of HTML for the currently-matched
			 * token, or null if not paused on any token.
			 *
			 * @return string|null Raw HTML content of currently-matched token,
			 *                     otherwise `null` if not matched.
			 */
			public function get_raw_token() {
				if (
					WP_HTML_Tag_Processor::STATE_READY === $this->parser_state ||
					WP_HTML_Tag_Processor::STATE_INCOMPLETE_INPUT === $this->parser_state ||
					WP_HTML_Tag_Processor::STATE_COMPLETE === $this->parser_state
				) {
					return null;
				}

				$this->set_bookmark( 'mark' );
				$mark = $this->bookmarks['mark'];

				return substr( $this->html, $mark->start, $mark->length );
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
	public static function data_html_nth_token_substring() {
		return array(
			// Tags.
			'DIV start tag'                 => array( '<div>', 1, '<div>' ),
			'DIV start tag with attributes' => array( '<div class="x" disabled>', 1, '<div class="x" disabled>' ),
			'DIV end tag'                   => array( '</div>', 1, '</div>' ),
			'DIV end tag with attributes'   => array( '</div class="x" disabled>', 1, '</div class="x" disabled>' ),
			'Nested DIV'                    => array( '<div><div b>', 2, '<div b>' ),
			'Sibling DIV'                   => array( '<div></div><div b>', 3, '<div b>' ),
			'DIV after text'                => array( 'text <div>', 2, '<div>' ),
			'DIV before text'               => array( '<div> text', 1, '<div>' ),
			'DIV after comment'             => array( '<!-- comment --><div>', 2, '<div>' ),
			'DIV before comment'            => array( '<div><!-- c --> ', 1, '<div>' ),
			'Start "self-closing" tag'      => array( '<div />', 1, '<div />' ),
			'Void tag'                      => array( '<img src="img.png">', 1, '<img src="img.png">' ),
			'Void tag w/self-closing flag'  => array( '<img src="img.png" />', 1, '<img src="img.png" />' ),
			'Void tag inside DIV'           => array( '<div><img src="img.png"></div>', 2, '<img src="img.png">' ),

			// Special atomic tags.
			'SCRIPT tag'                    => array( '<script>inside text</script>', 1, '<script>inside text</script>' ),
			'SCRIPT double-escape'          => array( '<script><!-- <script> echo "</script>"; </script><div>', 1, '<script><!-- <script> echo "</script>"; </script>' ),

			// Text.
			'Text'                          => array( 'Just text', 1, 'Just text' ),
			'Text in DIV'                   => array( '<div>Text<div>', 2, 'Text' ),
			'Text before DIV'               => array( 'Text<div>', 1, 'Text' ),
			'Text after DIV'                => array( '<div></div>Text', 3, 'Text' ),
			'Text after comment'            => array( '<!-- comment -->Text', 2, 'Text' ),
			'Text before comment'           => array( 'Text<!-- c --> ', 1, 'Text' ),

			// Comments.
			'Comment'                       => array( '<!-- comment -->', 1, '<!-- comment -->' ),
			'Comment in DIV'                => array( '<div><!-- comment --><div>', 2, '<!-- comment -->' ),
			'Comment before DIV'            => array( '<!-- comment --><div>', 1, '<!-- comment -->' ),
			'Comment after DIV'             => array( '<div></div><!-- comment -->', 3, '<!-- comment -->' ),
			'Comment after comment'         => array( '<!-- comment --><!-- comment -->', 2, '<!-- comment -->' ),
			'Comment before comment'        => array( '<!-- comment --><!-- c --> ', 1, '<!-- comment -->' ),
			'Abruptly closed comment'       => array( '<!-->', 1, '<!-->' ),
			'Empty comment'                 => array( '<!---->', 1, '<!---->' ),
			'Funky comment'                 => array( '</_ funk >', 1, '</_ funk >' ),
			'PI lookalike comment'          => array( '<?processing instruction?>', 1, '<?processing instruction?>' ),
			'CDATA lookalike comment'       => array( '<![CDATA[ see? data ]]>', 1, '<![CDATA[ see? data ]]>' ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_next_tag_with_no_arguments_should_find_the_next_existing_tag() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );

		$this->assertTrue( $processor->next_tag(), 'Querying an existing tag did not return true' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_next_tag_should_return_false_for_a_non_existing_tag() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
	}

	/**
	 * @ticket 61545
	 */
	public function test_next_tag_should_not_match_on_substrings_of_a_requested_tag() {
		$processor = new WP_HTML_Tag_Processor( '<p><pic><picture>' );

		$this->assertTrue(
			$processor->next_tag( 'PICTURE' ),
			'Failed to find a tag when requested: check test setup.'
		);

		$this->assertSame(
			'PICTURE',
			$processor->get_tag(),
			'Should have skipped past substring tag matches, directly finding the PICTURE element.'
		);

		$processor = new WP_HTML_Tag_Processor( '<p><pic>' );

		$this->assertFalse(
			$processor->next_tag( 'PICTURE' ),
			"Should not have found any PICTURE element, but found '{$processor->get_token_name()}' instead."
		);
	}

	/**
	 * @ticket 59209
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_next_tag_matches_decoded_class_names() {
		$processor = new WP_HTML_Tag_Processor( '<div class="&lt;egg&gt;">' );

		$this->assertTrue( $processor->next_tag( array( 'class_name' => '<egg>' ) ), 'Failed to find tag with HTML-encoded class name.' );
	}

	/**
	 * @ticket 56299
	 * @ticket 57852
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::is_tag_closer
	 */
	public function test_next_tag_should_stop_on_closers_only_when_requested() {
		$processor = new WP_HTML_Tag_Processor( '<div><img /></div>' );

		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'div' ) ), 'Did not find desired tag opener' );
		$this->assertFalse( $processor->next_tag( array( 'tag_name' => 'div' ) ), 'Visited an unwanted tag, a tag closer' );

		$processor = new WP_HTML_Tag_Processor( '<div><img /></div>' );
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

		$processor = new WP_HTML_Tag_Processor( '<div>' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), "Did not find a tag opener when tag_closers was set to 'visit'" );
		$this->assertFalse( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), "Found a closer where there wasn't one" );
	}

	/**
	 * @ticket 57852
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::is_tag_closer
	 */
	public function test_next_tag_should_stop_on_rcdata_and_script_tag_closers_when_requested() {
		$processor = new WP_HTML_Tag_Processor( '<script>abc</script>' );

		$processor->next_tag();
		$this->assertFalse(
			$processor->next_tag( array( 'tag_closers' => 'visit' ) ),
			'Should not have found closing SCRIPT tag when closing an opener.'
		);

		$processor = new WP_HTML_Tag_Processor( 'abc</script>' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </script> tag closer when there was no tag opener' );

		$processor = new WP_HTML_Tag_Processor( '<textarea>abc</textarea>' );

		$processor->next_tag();
		$this->assertFalse(
			$processor->next_tag( array( 'tag_closers' => 'visit' ) ),
			'Should not have found closing TEXTAREA when closing an opener.'
		);

		$processor = new WP_HTML_Tag_Processor( 'abc</textarea>' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </textarea> tag closer when there was no tag opener' );

		$processor = new WP_HTML_Tag_Processor( '<title>abc</title>' );

		$processor->next_tag();
		$this->assertFalse(
			$processor->next_tag( array( 'tag_closers' => 'visit' ) ),
			'Should not have found closing TITLE when closing an opener.'
		);

		$processor = new WP_HTML_Tag_Processor( 'abc</title>' );
		$this->assertTrue( $processor->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </title> tag closer when there was no tag opener' );
	}

	/**
	 * Verifies that updates to a document before calls to `get_updated_html()` don't
	 * lead to the Tag Processor jumping to the wrong tag after the updates.
	 *
	 * @ticket 58179
	 *
	 * @covers WP_HTML_Tag_Processor::get_updated_html
	 */
	public function test_internal_pointer_returns_to_original_spot_after_inserting_content_before_cursor() {
		$tags = new WP_HTML_Tag_Processor( '<div>outside</div><section><div><img>inside</div></section>' );

		$tags->next_tag();
		$tags->add_class( 'foo' );
		$tags->next_tag( 'section' );

		// Return to this spot after moving ahead.
		$tags->set_bookmark( 'here' );

		// Move ahead.
		$tags->next_tag( 'img' );
		$tags->seek( 'here' );
		$this->assertSame( '<div class="foo">outside</div><section><div><img>inside</div></section>', $tags->get_updated_html() );
		$this->assertSame( 'SECTION', $tags->get_tag() );
		$this->assertFalse( $tags->is_tag_closer() );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_on_a_non_existing_tag_does_not_change_the_markup() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );

		$this->assertFalse( $processor->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertFalse( $processor->next_tag( 'div' ), 'Querying a non-existing tag did not return false' );

		$processor->set_attribute( 'id', 'primary' );

		$this->assertSame(
			self::HTML_SIMPLE,
			$processor->get_updated_html(),
			'Calling get_updated_html after updating a non-existing tag returned an HTML that was different from the original HTML'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 * @covers WP_HTML_Tag_Processor::add_class
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_attribute_ops_on_tag_closer_do_not_change_the_markup() {
		$processor = new WP_HTML_Tag_Processor( '<div id=3></div invalid-id=4>' );
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
		$this->assertFalse( $processor->add_class( 'sneaky' ), "Allowed adding a class on a tag closer when it shouldn't have" );
		$this->assertFalse( $processor->remove_class( 'not-appearing-in-this-test' ), "Allowed removing a class on a tag closer when it shouldn't have" );
		$this->assertSame(
			'<div id=3></div invalid-id=4>',
			$processor->get_updated_html(),
			'Calling get_updated_html after updating a non-existing tag returned an HTML that was different from the original HTML'
		);
	}

	/**
	 * Passing a double quote inside of an attribute value could lead to an XSS attack as follows:
	 *
	 * ```php
	 *     $processor = new WP_HTML_Tag_Processor( '<div class="header"></div>' );
	 *     $processor->next_tag();
	 *     $processor->set_attribute('class', '" onclick="alert');
	 *     echo $p;
	 *     // <div class="" onclick="alert"></div>
	 * ```
	 *
	 * To prevent it, `set_attribute` calls `esc_attr()` on its given values.
	 *
	 * ```php
	 *    <div class="&quot; onclick=&quot;alert"></div>
	 * ```
	 *
	 * @ticket 56299
	 *
	 * @dataProvider data_set_attribute_prevents_xss
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 *
	 * @param string $attribute_value A value with potential XSS exploit.
	 */
	public function test_set_attribute_prevents_xss( $attribute_value ) {
		$processor = new WP_HTML_Tag_Processor( '<div></div>' );
		$processor->next_tag();
		$processor->set_attribute( 'test', $attribute_value );

		/*
		 * Testing the escaping is hard using tools that properly parse
		 * HTML because they might interpret the escaped values. It's hard
		 * with tools that don't understand HTML because they might get
		 * confused by improperly-escaped values.
		 *
		 * Since the input HTML is known, the test will do what looks like
		 * the opposite of what is expected to be done with this library.
		 * But by doing so, the test (a) has full control over the
		 * content and (b) looks at the raw values.
		 */
		$match = null;
		preg_match( '~^<div test=(.*)></div>$~', $processor->get_updated_html(), $match );
		list( , $actual_value ) = $match;

		$this->assertSame( '"' . esc_attr( $attribute_value ) . '"', $actual_value, 'Entities were not properly escaped in the attribute value' );
	}

	/**
	 * Data provider.
	 *
	 * @return string[][].
	 */
	public static function data_set_attribute_prevents_xss() {
		return array(
			array( '"' ),
			array( '&quot;' ),
			array( '&' ),
			array( '&amp;' ),
			array( '&euro;' ),
			array( "'" ),
			array( '<>' ),
			array( '&quot";' ),
			array( '" onclick="alert(\'1\');"><span onclick=""></span><script>alert("1")</script>' ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_a_non_existing_attribute_adds_a_new_attribute_to_the_markup() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-attribute', 'test-value' );

		$this->assertSame(
			'<div test-attribute="test-value" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not include attribute added via set_attribute()'
		);
		$this->assertSame(
			'test-value',
			$processor->get_attribute( 'test-attribute' ),
			'get_attribute() (called after get_updated_html()) did not return attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_updated_values_before_they_are_applied() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-attribute', 'test-value' );

		$this->assertSame(
			'test-value',
			$processor->get_attribute( 'test-attribute' ),
			'get_attribute() (called before get_updated_html()) did not return attribute added via set_attribute()'
		);
		$this->assertSame(
			'<div test-attribute="test-value" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not include attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_updated_values_before_they_are_applied_with_different_name_casing() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-ATTribute', 'test-value' );

		$this->assertSame(
			'test-value',
			$processor->get_attribute( 'test-attribute' ),
			'get_attribute() (called before get_updated_html()) did not return attribute added via set_attribute()'
		);
		$this->assertSame(
			'<div test-ATTribute="test-value" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not include attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_added_class_names_before_they_are_applied() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->add_class( 'my-class' );

		$this->assertSame(
			'my-class',
			$processor->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) did not return class name added via add_class()'
		);
		$this->assertSame(
			'<div class="my-class" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not include class name added via add_class()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_added_class_names_before_they_are_applied_and_retains_classes_from_previous_add_class_calls() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->add_class( 'my-class' );

		$this->assertSame(
			'my-class',
			$processor->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) did not return class name added via add_class()'
		);

		$processor->add_class( 'my-other-class' );

		$this->assertSame(
			'my-class my-other-class',
			$processor->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) did not return class names added via subsequent add_class() calls'
		);
		$this->assertSame(
			'<div class="my-class my-other-class" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not include class names added via subsequent add_class() calls'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_removed_attribute_before_it_is_applied() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertNull(
			$processor->get_attribute( 'id' ),
			'get_attribute() (called before get_updated_html()) returned attribute that was removed by remove_attribute()'
		);
		$this->assertSame(
			'<div ><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML includes attribute that was removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_adding_and_then_removing_an_attribute_before_those_updates_are_applied() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'test-attribute', 'test-value' );
		$processor->remove_attribute( 'test-attribute' );

		$this->assertNull(
			$processor->get_attribute( 'test-attribute' ),
			'get_attribute() (called before get_updated_html()) returned attribute that was added via set_attribute() and then removed by remove_attribute()'
		);
		$this->assertSame(
			self::HTML_SIMPLE,
			$processor->get_updated_html(),
			'Updated HTML includes attribute that was added via set_attribute() and then removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_setting_and_then_removing_an_existing_attribute_before_those_updates_are_applied() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'id', 'test-value' );
		$processor->remove_attribute( 'id' );

		$this->assertNull(
			$processor->get_attribute( 'id' ),
			'get_attribute() (called before get_updated_html()) returned attribute that was overwritten by set_attribute() and then removed by remove_attribute()'
		);
		$this->assertSame(
			'<div ><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML includes attribute that was overwritten by set_attribute() and then removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_removed_class_names_before_they_are_applied() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->remove_class( 'with-border' );

		$this->assertSame(
			'main',
			$processor->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) returned the wrong attribute after calling remove_attribute()'
		);
		$this->assertSame(
			'<div class="main" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML includes wrong attribute after calling remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_setting_and_then_removing_a_class_name_before_those_updates_are_applied() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->add_class( 'foo-class' );
		$processor->remove_class( 'foo-class' );

		$this->assertSame(
			'main with-border',
			$processor->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) returned class name that was added via add_class() and then removed by remove_class()'
		);
		$this->assertSame(
			self::HTML_WITH_CLASSES,
			$processor->get_updated_html(),
			'Updated HTML includes class that was added via add_class() and then removed by remove_class()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_duplicating_and_then_removing_an_existing_class_name_before_those_updates_are_applied() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->add_class( 'with-border' );
		$processor->remove_class( 'with-border' );

		$this->assertSame(
			'main',
			$processor->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) returned class name that was duplicated via add_class() and then removed by remove_class()'
		);
		$this->assertSame(
			'<div class="main" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML includes class that was duplicated via add_class() and then removed by remove_class()'
		);
	}

	/**
	 * According to HTML spec, only the first instance of an attribute counts.
	 * The other ones are ignored.
	 *
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_update_first_attribute_when_duplicated_attributes_exist() {
		$processor = new WP_HTML_Tag_Processor( '<div id="update-me" id="ignored-id"><span id="second">Text</span></div>' );
		$processor->next_tag();
		$processor->set_attribute( 'id', 'updated-id' );

		$this->assertSame(
			'<div id="updated-id" id="ignored-id"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Proper (first) appearance of attribute was not updated when duplicates exist'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_an_existing_attribute_name_updates_its_value_in_the_markup() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'id', 'new-id' );
		$this->assertSame(
			'<div id="new-id"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Existing attribute was not updated'
		);
	}

	/**
	 * Ensures that when setting an attribute multiple times that only
	 * one update flushes out into the updated HTML.
	 *
	 * @ticket 58146
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_case_variants_updates_only_the_original_first_copy() {
		$processor = new WP_HTML_Tag_Processor( '<div data-enabled="5">' );
		$processor->next_tag();
		$processor->set_attribute( 'DATA-ENABLED', 'canary' );
		$processor->set_attribute( 'Data-Enabled', 'canary' );
		$processor->set_attribute( 'dATa-EnABled', 'canary' );

		$this->assertSame( '<div data-enabled="canary">', strtolower( $processor->get_updated_html() ) );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_next_tag_and_set_attribute_in_a_loop_update_all_tags_in_the_markup() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		while ( $processor->next_tag() ) {
			$processor->set_attribute( 'data-foo', 'bar' );
		}

		$this->assertSame(
			'<div data-foo="bar" id="first"><span data-foo="bar" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Not all tags were updated when looping with next_tag() and set_attribute()'
		);
	}

	/**
	 * Removing an attribute that's listed many times, e.g. `<div id="a" id="b" />` should remove
	 * all its instances and output just `<div />`.
	 *
	 * @since 6.3.2 Removes all duplicated attributes as expected.
	 *
	 * @ticket 58119
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_remove_first_when_duplicated_attribute() {
		$processor = new WP_HTML_Tag_Processor( '<div id="update-me" id="ignored-id"><span id="second">Text</span></div>' );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertStringNotContainsString(
			'update-me',
			$processor->get_updated_html(),
			'First attribute (when duplicates exist) was not removed'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_with_an_existing_attribute_name_removes_it_from_the_markup() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertSame(
			'<div ><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Attribute was not removed'
		);
	}

	/**
	 * @ticket 58119
	 *
	 * @since 6.3.2 Removes all duplicated attributes as expected.
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 *
	 * @dataProvider data_html_with_duplicated_attributes
	 */
	public function test_remove_attribute_with_duplicated_attributes_removes_all_of_them( $html_with_duplicate_attributes, $attribute_to_remove ) {
		$processor = new WP_HTML_Tag_Processor( $html_with_duplicate_attributes );
		$processor->next_tag();

		$processor->remove_attribute( $attribute_to_remove );
		$this->assertNull( $processor->get_attribute( $attribute_to_remove ), 'Failed to remove all copies of an attribute when duplicated in modified source.' );

		// Recreate a tag processor with the updated HTML after removing the attribute.
		$processor = new WP_HTML_Tag_Processor( $processor->get_updated_html() );
		$processor->next_tag();
		$this->assertNull( $processor->get_attribute( $attribute_to_remove ), 'Failed to remove all copies of duplicated attributes when getting updated HTML.' );
	}

	/**
	 * @ticket 58119
	 *
	 * @since 6.3.2 Removes all duplicated attributes as expected.
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_previous_duplicated_attributes_are_not_removed_on_successive_tag_removal() {
		$processor = new WP_HTML_Tag_Processor( '<span id=one id=two id=three><span id=four>' );
		$processor->next_tag();
		$processor->next_tag();
		$processor->remove_attribute( 'id' );

		$this->assertSame( '<span id=one id=two id=three><span >', $processor->get_updated_html() );
	}

	/**
	 * Data provider.
	 *
	 * @ticket 58119
	 *
	 * @return array[].
	 */
	public static function data_html_with_duplicated_attributes() {
		return array(
			'Double attributes'               => array( '<div id=one id=two>', 'id' ),
			'Triple attributes'               => array( '<div id=one id=two id=three>', 'id' ),
			'Duplicates around another'       => array( '<img src="test.png" alt="kites flying in the wind" src="kites.jpg">', 'src' ),
			'Case-variants of attribute'      => array( '<button disabled inert DISABLED dISaBled INERT DisABleD>', 'disabled' ),
			'Case-variants of attribute name' => array( '<button disabled inert DISABLED dISaBled INERT DisABleD>', 'DISABLED' ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_with_a_non_existing_attribute_name_does_not_change_the_markup() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->remove_attribute( 'no-such-attribute' );

		$this->assertSame(
			self::HTML_SIMPLE,
			$processor->get_updated_html(),
			'Content was changed when attempting to remove an attribute that did not exist'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_creates_a_class_attribute_when_there_is_none() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->add_class( 'foo-class' );

		$this->assertSame(
			'<div class="foo-class" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not include class name added via add_class()'
		);
		$this->assertSame(
			'foo-class',
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return class name added via add_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_calling_add_class_twice_creates_a_class_attribute_with_both_class_names_when_there_is_no_class_attribute() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->add_class( 'foo-class' );
		$processor->add_class( 'bar-class' );

		$this->assertSame(
			'<div class="foo-class bar-class" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not include class names added via subsequent add_class() calls'
		);
		$this->assertSame(
			'foo-class bar-class',
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return class names added via subsequent add_class() calls"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_remove_class_does_not_change_the_markup_when_there_is_no_class_attribute() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->remove_class( 'foo-class' );

		$this->assertSame(
			self::HTML_SIMPLE,
			$processor->get_updated_html(),
			'Updated HTML includes class name that was removed by remove_class()'
		);
		$this->assertNull(
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return null for class name that was removed by remove_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_appends_class_names_to_the_existing_class_attribute_when_one_already_exists() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->add_class( 'foo-class' );
		$processor->add_class( 'bar-class' );

		$this->assertSame(
			'<div class="main with-border foo-class bar-class" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not reflect class names added to existing class attribute via subsequent add_class() calls'
		);
		$this->assertSame(
			'main with-border foo-class bar-class',
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect class names added to existing class attribute via subsequent add_class() calls"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_remove_class_removes_a_single_class_from_the_class_attribute_when_one_exists() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->remove_class( 'main' );

		$this->assertSame(
			'<div class="with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not reflect class name removed from existing class attribute via remove_class()'
		);
		$this->assertSame(
			'with-border',
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect class name removed from existing class attribute via remove_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_calling_remove_class_with_all_listed_class_names_removes_the_existing_class_attribute_from_the_markup() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->remove_class( 'main' );
		$processor->remove_class( 'with-border' );

		$this->assertSame(
			'<div  id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not reflect class attribute removed via subesequent remove_class() calls'
		);
		$this->assertNull(
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return null for class attribute removed via subesequent remove_class() calls"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_does_not_add_duplicate_class_names() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->add_class( 'with-border' );

		$this->assertSame(
			'<div class="main with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not reflect deduplicated class name added via add_class()'
		);
		$this->assertSame(
			'main with-border',
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect deduplicated class name added via add_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_preserves_class_name_order_when_a_duplicate_class_name_is_added() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->add_class( 'main' );

		$this->assertSame(
			'<div class="main with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not reflect class name order after adding duplicated class name via add_class()'
		);
		$this->assertSame(
			'main with-border',
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect class name order after adding duplicated class name added via add_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_when_there_is_a_class_attribute_with_excessive_whitespaces() {
		$processor = new WP_HTML_Tag_Processor(
			'<div class="   main   with-border   " id="first"><span class="not-main bold with-border" id="second">Text</span></div>'
		);
		$processor->next_tag();
		$processor->add_class( 'foo-class' );

		$this->assertSame(
			'<div class="main   with-border foo-class" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not reflect existing excessive whitespace after adding class name via add_class()'
		);
		$this->assertSame(
			'main   with-border foo-class',
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect existing excessive whitespace after adding class name via add_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_remove_class_preserves_whitespaces_when_there_is_a_class_attribute_with_excessive_whitespaces() {
		$processor = new WP_HTML_Tag_Processor(
			'<div class="   main   with-border   " id="first"><span class="not-main bold with-border" id="second">Text</span></div>'
		);
		$processor->next_tag();
		$processor->remove_class( 'with-border' );

		$this->assertSame(
			'<div class="main" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not reflect existing excessive whitespace after removing class name via remove_class()'
		);
		$this->assertSame(
			'main',
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect existing excessive whitespace after removing class name via removing_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_removing_all_classes_removes_the_existing_class_attribute_from_the_markup_even_when_excessive_whitespaces_are_present() {
		$processor = new WP_HTML_Tag_Processor(
			'<div class="   main   with-border   " id="first"><span class="not-main bold with-border" id="second">Text</span></div>'
		);
		$processor->next_tag();
		$processor->remove_class( 'main' );
		$processor->remove_class( 'with-border' );
		$this->assertSame(
			'<div  id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			'Updated HTML does not reflect removed class attribute after removing all class names via remove_class()'
		);
		$this->assertNull(
			$processor->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return null after removing all class names via remove_class()"
		);
	}

	/**
	 * When add_class( $different_value ) is called _after_ set_attribute( 'class', $value ), the
	 * final class name should be "$value $different_value". In other words, the `add_class` call
	 * should append its class to the one(s) set by `set_attribute`. When `add_class( $different_value )`
	 * is called _before_ `set_attribute( 'class', $value )`, however, the final class name should be
	 * "$value" instead, as any direct updates to the `class` attribute supersede any changes enqueued
	 * via the class builder methods.
	 *
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_takes_priority_over_add_class() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->add_class( 'add_class' );
		$processor->set_attribute( 'class', 'set_attribute' );
		$this->assertSame(
			'<div class="set_attribute" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			"Calling get_updated_html after updating first tag's attributes did not return the expected HTML"
		);
		$this->assertSame(
			'set_attribute',
			$processor->get_attribute( 'class' ),
			"Calling get_attribute after updating first tag's attributes did not return the expected class name"
		);

		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->set_attribute( 'class', 'set_attribute' );
		$processor->add_class( 'add_class' );
		$this->assertSame(
			'<div class="set_attribute add_class" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			"Calling get_updated_html after updating first tag's attributes did not return the expected HTML"
		);
		$this->assertSame(
			'set_attribute add_class',
			$processor->get_attribute( 'class' ),
			"Calling get_attribute after updating first tag's attributes did not return the expected class name"
		);
	}

	/**
	 * When add_class( $different_value ) is called _after_ set_attribute( 'class', $value ), the
	 * final class name should be "$value $different_value". In other words, the `add_class` call
	 * should append its class to the one(s) set by `set_attribute`. When `add_class( $different_value )`
	 * is called _before_ `set_attribute( 'class', $value )`, however, the final class name should be
	 * "$value" instead, as any direct updates to the `class` attribute supersede any changes enqueued
	 * via the class builder methods.
	 *
	 * This is still true when reading enqueued updates before calling `get_updated_html()`.
	 *
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_takes_priority_over_add_class_even_before_updating() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->add_class( 'add_class' );
		$processor->set_attribute( 'class', 'set_attribute' );
		$this->assertSame(
			'set_attribute',
			$processor->get_attribute( 'class' ),
			"Calling get_attribute after updating first tag's attributes did not return the expected class name"
		);
		$this->assertSame(
			'<div class="set_attribute" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			"Calling get_updated_html after updating first tag's attributes did not return the expected HTML"
		);

		$processor = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$processor->next_tag();
		$processor->set_attribute( 'class', 'set_attribute' );
		$processor->add_class( 'add_class' );
		$this->assertSame(
			'set_attribute add_class',
			$processor->get_attribute( 'class' ),
			"Calling get_attribute after updating first tag's attributes did not return the expected class name"
		);
		$this->assertSame(
			'<div class="set_attribute add_class" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$processor->get_updated_html(),
			"Calling get_updated_html after updating first tag's attributes did not return the expected HTML"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_overrides_boolean_class_attribute() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'class', true );
		$processor->add_class( 'add_class' );
		$this->assertSame(
			'<div class="add_class" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			"Updated HTML doesn't reflect class added via add_class that was originally set as boolean attribute"
		);
		$this->assertSame(
			'add_class',
			$processor->get_attribute( 'class' ),
			"get_attribute (called after get_updated_html()) doesn't reflect class added via add_class that was originally set as boolean attribute"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_overrides_boolean_class_attribute_even_before_updating() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$processor->next_tag();
		$processor->set_attribute( 'class', true );
		$processor->add_class( 'add_class' );
		$this->assertSame(
			'add_class',
			$processor->get_attribute( 'class' ),
			"get_attribute (called before get_updated_html()) doesn't reflect class added via add_class that was originally set as boolean attribute"
		);
		$this->assertSame(
			'<div class="add_class" id="first"><span id="second">Text</span></div>',
			$processor->get_updated_html(),
			"Updated HTML doesn't reflect class added via add_class that was originally set as boolean attribute"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 * @covers WP_HTML_Tag_Processor::add_class
	 * @covers WP_HTML_Tag_Processor::remove_class
	 * @covers WP_HTML_Tag_Processor::get_updated_html
	 */
	public function test_advanced_use_case() {
		$input = <<<HTML
<div selected class="merge-message" checked>
	<div class="select-menu d-inline-block">
		<div checked class="BtnGroup MixedCaseHTML position-relative" />
		<div checked class="BtnGroup MixedCaseHTML position-relative">
			<button type="button" class="merge-box-button btn-group-merge rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Merge pull request
			</button>

			<button type="button" class="merge-box-button btn-group-squash rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Squash and merge
			</button>

			<button type="button" class="merge-box-button btn-group-rebase rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Rebase and merge
			</button>

			<button aria-label="Select merge method" disabled="disabled" type="button" data-view-component="true" class="select-menu-button btn BtnGroup-item"></button>
		</div>
	</div>
</div>
HTML;

		$expected_output = <<<HTML
<div data-details="{ &quot;key&quot;: &quot;value&quot; }" selected class="merge-message is-processed" checked>
	<div class="select-menu d-inline-block">
		<div checked class="MixedCaseHTML position-relative button-group Another-Mixed-Case" />
		<div checked class="MixedCaseHTML position-relative button-group Another-Mixed-Case">
			<button type="button" class="merge-box-button btn-group-merge rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Merge pull request
			</button>

			<button type="button" class="merge-box-button btn-group-squash rounded-left-2 btn  BtnGroup-item js-details-target hx_create-pr-button" aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Squash and merge
			</button>

			<button type="button"  aria-expanded="false" data-details-container=".js-merge-pr" disabled="">
			  Rebase and merge
			</button>

			<button aria-label="Select merge method" disabled="disabled" type="button" data-view-component="true" class="select-menu-button btn BtnGroup-item"></button>
		</div>
	</div>
</div>
HTML;

		$processor = new WP_HTML_Tag_Processor( $input );
		$this->assertTrue( $processor->next_tag( 'div' ), 'Did not find first DIV tag in input.' );
		$processor->set_attribute( 'data-details', '{ "key": "value" }' );
		$processor->add_class( 'is-processed' );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'BtnGroup',
				)
			),
			'Did not find the first BtnGroup DIV tag'
		);
		$processor->remove_class( 'BtnGroup' );
		$processor->add_class( 'button-group' );
		$processor->add_class( 'Another-Mixed-Case' );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'BtnGroup',
				)
			),
			'Did not find the second BtnGroup DIV tag'
		);
		$processor->remove_class( 'BtnGroup' );
		$processor->add_class( 'button-group' );
		$processor->add_class( 'Another-Mixed-Case' );
		$this->assertTrue(
			$processor->next_tag(
				array(
					'tag_name'     => 'button',
					'class_name'   => 'btn',
					'match_offset' => 3,
				)
			),
			'Did not find third BUTTON tag with "btn" CSS class'
		);
		$processor->remove_attribute( 'class' );
		$this->assertFalse( $processor->next_tag( 'non-existent' ), "Found a {$processor->get_tag()} tag when none should have been found." );
		$processor->set_attribute( 'class', 'test' );
		$this->assertSame( $expected_output, $processor->get_updated_html(), 'Calling get_updated_html after updating the attributes did not return the expected HTML' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_correctly_parses_html_attributes_wrapped_in_single_quotation_marks() {
		$processor = new WP_HTML_Tag_Processor(
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
			$processor->get_updated_html(),
			'Did not remove single-quoted attribute'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_value_equal_to_true_adds_a_boolean_html_attribute_with_implicit_value() {
		$processor = new WP_HTML_Tag_Processor(
			'<form action="/action_page.php"><input type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>'
		);
		$processor->next_tag( 'input' );
		$processor->set_attribute( 'checked', true );
		$this->assertSame(
			'<form action="/action_page.php"><input checked type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>',
			$processor->get_updated_html(),
			'Did not add "checked" as an expected boolean attribute'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_setting_a_boolean_attribute_to_false_removes_it_from_the_markup() {
		$processor = new WP_HTML_Tag_Processor(
			'<form action="/action_page.php"><input checked type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>'
		);
		$processor->next_tag( 'input' );
		$processor->set_attribute( 'checked', false );
		$this->assertSame(
			'<form action="/action_page.php"><input  type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>',
			$processor->get_updated_html(),
			'Did not remove boolean attribute when set to false'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_setting_a_missing_attribute_to_false_does_not_change_the_markup() {
		$html_input = '<form action="/action_page.php"><input type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>';
		$processor  = new WP_HTML_Tag_Processor( $html_input );
		$processor->next_tag( 'input' );
		$processor->set_attribute( 'checked', false );
		$this->assertSame(
			$html_input,
			$processor->get_updated_html(),
			'Changed the markup unexpectedly when setting a non-existing attribute to false'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_setting_a_boolean_attribute_to_a_string_value_adds_explicit_value_to_the_markup() {
		$processor = new WP_HTML_Tag_Processor(
			'<form action="/action_page.php"><input checked type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>'
		);
		$processor->next_tag( 'input' );
		$processor->set_attribute( 'checked', 'checked' );
		$this->assertSame(
			'<form action="/action_page.php"><input checked="checked" type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>',
			$processor->get_updated_html(),
			'Did not add string value to existing boolean attribute'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::paused_at_incomplete_token
	 */
	public function test_unclosed_script_tag_should_not_cause_an_infinite_loop() {
		$processor = new WP_HTML_Tag_Processor( '<script><div>' );
		$this->assertFalse(
			$processor->next_tag(),
			'Should not have stopped on an opening SCRIPT tag without a proper closing tag in the document.'
		);
		$this->assertTrue(
			$processor->paused_at_incomplete_token(),
			"Should have paused the parser because of the incomplete SCRIPT tag but didn't."
		);

		// Run this to ensure that the test ends (not in an infinite loop).
		$processor->next_tag();
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 *
	 * @dataProvider data_next_tag_ignores_script_tag_contents
	 *
	 * @param string $script_then_div HTML to test.
	 */
	public function test_next_tag_ignores_script_tag_contents( $script_then_div ) {
		$processor = new WP_HTML_Tag_Processor( $script_then_div );
		$processor->next_tag();
		$this->assertSame( 'SCRIPT', $processor->get_tag(), 'The first found tag was not "script"' );
		$processor->next_tag();
		$this->assertSame( 'DIV', $processor->get_tag(), 'The second found tag was not "div"' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_next_tag_ignores_script_tag_contents() {
		return array(
			'Simple script tag'                          => array(
				'<script><span class="d-none d-md-inline">Back to notifications</span></script><div></div>',
			),

			'Simple uppercase script tag'                => array(
				'<script><span class="d-none d-md-inline">Back to notifications</span></SCRIPT><div></div>',
			),

			'Script with a comment opener inside should end at the next script tag closer (dash dash escaped state)' => array(
				'<script class="d-md-none"><!--</script><div></div>-->',
			),

			'Script with a comment opener and a script tag opener inside should end two script tag closer later (double escaped state)' => array(
				'<script class="d-md-none"><!--<script><span1></script><span2></span2></script><div></div>-->',
			),

			'Double escaped script with a tricky opener' => array(
				'<script class="d-md-none"><!--<script attr="</script>"></script>"><div></div>',
			),

			'Double escaped script with a tricky closer' => array(
				'<script class="d-md-none"><!--<script><span></script attr="</script>"><div></div>',
			),

			'Double escaped, then escaped, then double escaped' => array(
				'<script class="d-md-none"><!--<script></script><script></script><span></span></script><div></div>',
			),

			'Script with a commented a script tag opener inside should at the next tag closer (dash dash escaped state)' => array(
				'<script class="d-md-none"><!--<script>--><span></script><div></div>-->',
			),

			'Script closer with another script tag in closer attributes' => array(
				'<script><span class="d-none d-md-inline">Back to notifications</title</span></script <script><div></div>',
			),

			'Script closer with attributes'              => array(
				'<script class="d-md-none"><span class="d-none d-md-inline">Back to notifications</span></script id="test"><div></div>',
			),

			'Script opener with title closer inside'     => array(
				'<script class="d-md-none"></title></script><div></div>',
			),

			'Complex script with many parsing states'    => array(
				'<script class="d-md-none"><!--<script>--><scRipt><span><!--<span><Script</script>--></scripT><div></div>-->',
			),
		);
	}

	/**
	 * Invalid tag names are comments on tag closers.
	 *
	 * @ticket 58007
	 *
	 * @link https://html.spec.whatwg.org/#parse-error-invalid-first-character-of-tag-name
	 *
	 * @dataProvider data_next_tag_ignores_invalid_first_character_of_tag_name_comments
	 *
	 * @param string $html_with_markers HTML containing an invalid tag closer whose element before and
	 *                                  element after contain the "start" and "end" CSS classes.
	 */
	public function test_next_tag_ignores_invalid_first_character_of_tag_name_comments( $html_with_markers ) {
		$processor = new WP_HTML_Tag_Processor( $html_with_markers );
		$processor->next_tag( array( 'class_name' => 'start' ) );
		$processor->next_tag();

		$this->assertSame( 'end', $processor->get_attribute( 'class' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_next_tag_ignores_invalid_first_character_of_tag_name_comments() {
		return array(
			'Invalid tag openers as normal text'           => array(
				'<ul><li><div class=start>I <3 when outflow > inflow</div><img class=end></li></ul>',
			),

			'Invalid tag closers as comments'              => array(
				'<ul><li><div class=start>I </3 when <img> outflow <br class=end> inflow</div></li></ul>',
			),

			'Unexpected question mark instead of tag name' => array(
				'<div class=start><?xml-stylesheet type="text/css" href="style.css"?><hr class=end>',
			),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 *
	 * @dataProvider data_next_tag_ignores_contents_of_rcdata_tag
	 *
	 * @param string $rcdata_then_div HTML with RCDATA before a DIV.
	 * @param string $rcdata_tag      RCDATA tag.
	 */
	public function test_next_tag_ignores_contents_of_rcdata_tag( $rcdata_then_div, $rcdata_tag ) {
		$processor = new WP_HTML_Tag_Processor( $rcdata_then_div );
		$processor->next_tag();
		$this->assertSame( $rcdata_tag, $processor->get_tag(), "The first found tag was not '$rcdata_tag'" );
		$processor->next_tag();
		$this->assertSame( 'DIV', $processor->get_tag(), "The second found tag was not 'div'" );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_next_tag_ignores_contents_of_rcdata_tag() {
		return array(
			'simple textarea'                          => array(
				'rcdata_then_div' => '<textarea><span class="d-none d-md-inline">Back to notifications</span></textarea><div></div>',
				'rcdata_tag'      => 'TEXTAREA',
			),
			'simple title'                             => array(
				'rcdata_then_div' => '<title><span class="d-none d-md-inline">Back to notifications</title</span></title><div></div>',
				'rcdata_tag'      => 'TITLE',
			),
			'comment opener inside a textarea tag should be ignored' => array(
				'rcdata_then_div' => '<textarea class="d-md-none"><!--</textarea><div></div>-->',
				'rcdata_tag'      => 'TEXTAREA',
			),
			'textarea closer with another textarea tag in closer attributes' => array(
				'rcdata_then_div' => '<textarea><span class="d-none d-md-inline">Back to notifications</title</span></textarea <textarea><div></div>',
				'rcdata_tag'      => 'TEXTAREA',
			),
			'textarea closer with attributes'          => array(
				'rcdata_then_div' => '<textarea class="d-md-none"><span class="d-none d-md-inline">Back to notifications</span></textarea id="test"><div></div>',
				'rcdata_tag'      => 'TEXTAREA',
			),
			'textarea opener with title closer inside' => array(
				'rcdata_then_div' => '<textarea class="d-md-none"></title></textarea><div></div>',
				'rcdata_tag'      => 'TEXTAREA',
			),
		);
	}

	/**
	 * Ensures matching elements inside NOSCRIPT elements.
	 *
	 * In a browser when the scripting flag is enabled, everything inside
	 * the NOSCRIPT element will be ignored and treated at RAW TEXT. This
	 * means that it's valid to send what looks like incomplete or partial
	 * HTML syntax without impacting a rendered page. The Tag Processor is
	 * a parser with the scripting flag disabled, however, and needs to
	 * expose all the potential content that some code might want to modify.
	 *
	 * Were it not for this then the NOSCRIPT tag would be handled like the
	 * other tags in the RAW TEXT special group, e.g. NOEMBED or STYLE.
	 *
	 * @ticket 60122
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_processes_inside_of_noscript_elements() {
		$processor = new WP_HTML_Tag_Processor( '<noscript><input type="submit"></noscript><div>' );

		$this->assertTrue( $processor->next_tag( 'INPUT' ), 'Failed to find INPUT element inside NOSCRIPT element.' );
		$this->assertTrue( $processor->next_tag( 'DIV' ), 'Failed to find DIV element after NOSCRIPT element.' );
	}

	/**
	 * @ticket 59292
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 *
	 * @dataProvider data_next_tag_ignores_contents_of_rawtext_tags
	 *
	 * @param string $rawtext_element_then_target_node HTML starting with a RAWTEXT-specifying element such as STYLE,
	 *                                                 then an element afterward containing the "target" attribute.
	 */
	public function test_next_tag_ignores_contents_of_rawtext_tags( $rawtext_element_then_target_node ) {
		$processor = new WP_HTML_Tag_Processor( $rawtext_element_then_target_node );
		$processor->next_tag();

		$processor->next_tag();
		$this->assertNotNull(
			$processor->get_attribute( 'target' ),
			"Expected to find element with target attribute but found {$processor->get_tag()} instead."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public static function data_next_tag_ignores_contents_of_rawtext_tags() {
		return array(
			'IFRAME'           => array( '<iframe><section>Inside</section></iframe><section target>' ),
			'NOEMBED'          => array( '<noembed><p></p></noembed><div target>' ),
			'NOFRAMES'         => array( '<noframes><p>Check the rules here.</p></noframes><div target>' ),
			'STYLE'            => array( '<style>* { margin: 0 }</style><div target>' ),
			'STYLE hiding DIV' => array( '<style>li::before { content: "<div non-target>" }</style><div target>' ),
		);
	}

	/**
	 * @ticket 59209
	 *
	 * @covers WP_HTML_Tag_Processor::class_list
	 */
	public function test_class_list_empty_when_missing_class() {
		$processor = new WP_HTML_Tag_Processor( '<div>' );
		$processor->next_tag();

		$found_classes = false;
		foreach ( $processor->class_list() as $class ) {
			$found_classes = true;
		}

		$this->assertFalse( $found_classes, 'Found classes when none exist.' );
	}

	/**
	 * @ticket 59209
	 *
	 * @covers WP_HTML_Tag_Processor::class_list
	 */
	public function test_class_list_empty_when_class_is_boolean() {
		$processor = new WP_HTML_Tag_Processor( '<div class>' );
		$processor->next_tag();

		$found_classes = false;
		foreach ( $processor->class_list() as $class ) {
			$found_classes = true;
		}

		$this->assertFalse( $found_classes, 'Found classes when none exist.' );
	}

	/**
	 * @ticket 59209
	 *
	 * @covers WP_HTML_Tag_Processor::class_list
	 */
	public function test_class_list_empty_when_class_is_empty() {
		$processor = new WP_HTML_Tag_Processor( '<div class="">' );
		$processor->next_tag();

		$found_classes = false;
		foreach ( $processor->class_list() as $class ) {
			$found_classes = true;
		}

		$this->assertFalse( $found_classes, 'Found classes when none exist.' );
	}

	/**
	 * @ticket 59209
	 *
	 * @covers WP_HTML_Tag_Processor::class_list
	 */
	public function test_class_list_visits_each_class_in_order() {
		$processor = new WP_HTML_Tag_Processor( '<div class="one two three">' );
		$processor->next_tag();

		$found_classes = array();
		foreach ( $processor->class_list() as $class ) {
			$found_classes[] = $class;
		}

		$this->assertSame( array( 'one', 'two', 'three' ), $found_classes, 'Failed to visit the class names in their original order.' );
	}

	/**
	 * @ticket 59209
	 *
	 * @covers WP_HTML_Tag_Processor::class_list
	 */
	public function test_class_list_decodes_class_names() {
		$processor = new WP_HTML_Tag_Processor( '<div class="&notin;-class &lt;egg&gt; &#xff03;">' );
		$processor->next_tag();

		$found_classes = array();
		foreach ( $processor->class_list() as $class ) {
			$found_classes[] = $class;
		}

		$this->assertSame( array( '∉-class', '<egg>', "\u{ff03}" ), $found_classes, 'Failed to report class names in their decoded form.' );
	}

	/**
	 * @ticket 59209
	 *
	 * @covers WP_HTML_Tag_Processor::class_list
	 */
	public function test_class_list_visits_unique_class_names_only_once() {
		$processor = new WP_HTML_Tag_Processor( '<div class="one one &#x6f;ne">' );
		$processor->next_tag();

		$found_classes = array();
		foreach ( $processor->class_list() as $class ) {
			$found_classes[] = $class;
		}

		$this->assertSame( array( 'one' ), $found_classes, 'Visited multiple copies of the same class name when it should have skipped the duplicates.' );
	}

	/**
	 * Ensures that null bytes are replaced with the replacement character (U+FFFD) in class_list.
	 *
	 * @ticket 61531
	 *
	 * @covers WP_HTML_Tag_Processor::class_list
	 */
	public function test_class_list_null_bytes_replaced() {
		$processor = new WP_HTML_Tag_Processor( "<div class='a \0 b\0 \0c\0'>" );
		$processor->next_tag();

		$found_classes = iterator_to_array( $processor->class_list() );

		$this->assertSame( array( 'a', "\u{FFFD}", "b\u{FFFD}", "\u{FFFD}c\u{FFFD}" ), $found_classes );
	}

	/**
	 * Ensures that the tag processor matches class names with null bytes correctly.
	 *
	 * @ticket 61531
	 *
	 * @covers WP_HTML_Tag_Processor::has_class
	 */
	public function test_has_class_null_byte_class_name() {
		$processor = new WP_HTML_Tag_Processor( "<div class='null-byte-\0-there'>" );
		$processor->next_tag();
		$this->assertTrue( $processor->has_class( 'null-byte-�-there' ) );
	}

	/**
	 * @ticket 59209
	 *
	 * @covers WP_HTML_Tag_Processor::has_class
	 *
	 * @dataProvider data_html_with_variations_of_class_values_and_sought_class_names
	 *
	 * @param string $html         Contains a tag optionally containing a `class` attribute.
	 * @param string $sought_class Name of class to find in the input tag's `class`.
	 * @param bool   $has_class    Whether the sought class exists in the given HTML.
	 */
	public function test_has_class_handles_expected_class_name_variations( $html, $sought_class, $has_class ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();

		if ( $has_class ) {
			$this->assertTrue( $processor->has_class( $sought_class ), "Failed to find expected class {$sought_class}." );
		} else {
			$this->assertFalse( $processor->has_class( $sought_class ), "Found class {$sought_class} when it doesn't exist." );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_html_with_variations_of_class_values_and_sought_class_names() {
		return array(
			'Tag without any classes'      => array( '<div>', 'foo', false ),
			'Tag with boolean class'       => array( '<img class>', 'foo', false ),
			'Tag with empty class'         => array( '<p class="">', 'foo', false ),
			'Tag with exact match'         => array( '<button class="foo">', 'foo', true ),
			'Tag with duplicate matches'   => array( '<span class="foo bar foo">', 'foo', true ),
			'Tag with non-initial match'   => array( '<section class="bar foo">', 'foo', true ),
			'Tag with encoded match'       => array( '<main class="&hellip;">', '…', true ),
			'Class with tab separator'     => array( "<div class='one\ttwo'>", 'two', true ),
			'Class with newline separator' => array( "<div class='one\ntwo\n'>", 'two', true ),
			'False duplicate attribute'    => array( '<img class=dog class=cat>', 'cat', false ),
		);
	}

	/**
	 * Ensures that the invalid comment closing syntax "--!>" properly closes a comment.
	 *
	 * @ticket 58007
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 *
	 */
	public function test_allows_incorrectly_closed_comments() {
		$processor = new WP_HTML_Tag_Processor( '<img id=before><!-- <img id=inside> --!><img id=after>--><img id=final>' );

		$processor->next_tag();
		$this->assertSame( 'before', $processor->get_attribute( 'id' ), 'Did not find starting tag.' );

		$processor->next_tag();
		$this->assertSame( 'after', $processor->get_attribute( 'id' ), 'Did not properly close improperly-closed comment.' );

		$processor->next_tag();
		$this->assertSame( 'final', $processor->get_attribute( 'id' ), 'Did not skip over unopened comment-closer.' );
	}

	/**
	 * Ensures that unclosed and invalid comments don't trigger warnings or errors.
	 *
	 * @ticket 58007
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::paused_at_incomplete_token
	 *
	 * @dataProvider data_html_with_unclosed_comments
	 *
	 * @param string $html_ending_before_comment_close HTML with opened comments that aren't closed.
	 */
	public function test_documents_may_end_with_unclosed_comment( $html_ending_before_comment_close ) {
		$processor = new WP_HTML_Tag_Processor( $html_ending_before_comment_close );

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
	public static function data_html_with_unclosed_comments() {
		return array(
			'Shortest open valid comment'      => array( '<!--' ),
			'Basic truncated comment'          => array( '<!-- this ends --' ),
			'Comment with closer look-alike'   => array( '<!-- this ends --x' ),
			'Comment with closer look-alike 2' => array( '<!-- this ends --!x' ),
			'Invalid tag-closer comment'       => array( '</(when will this madness end?)' ),
			'Invalid tag-closer comment 2'     => array( '</(when will this madness end?)--' ),
		);
	}

	/**
	 * Ensures that abruptly-closed empty comments are properly closed.
	 *
	 * @ticket 58007
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 *
	 * @dataProvider data_abruptly_closed_empty_comments
	 *
	 * @param string $html_with_after_marker HTML to test with "id=after" on element immediately following an abruptly closed comment.
	 */
	public function test_closes_abrupt_closing_of_empty_comment( $html_with_after_marker ) {
		$processor = new WP_HTML_Tag_Processor( $html_with_after_marker );
		$processor->next_tag();
		$processor->next_tag();

		$this->assertSame( 'after', $processor->get_attribute( 'id' ), 'Did not find tag after closing abruptly-closed comment' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_abruptly_closed_empty_comments() {
		return array(
			'Empty comment with two dashes only' => array( '<hr><!--><hr id=after>' ),
			'Empty comment with two dashes only, improperly closed' => array( '<hr><!--!><hr id=inside>--><hr id=after>' ),
			'Comment with two dashes only, improperly closed twice' => array( '<hr><!--!><hr id=inside>--!><hr id=after>' ),
			'Empty comment with three dashes'    => array( '<hr><!---><hr id=after>' ),
			'Empty comment with three dashes, improperly closed' => array( '<hr><!---!><hr id=inside>--><hr id=after>' ),
			'Comment with three dashes, improperly closed twice' => array( '<hr><!---!><hr id=inside>--!><hr id=after>' ),
			'Empty comment with four dashes'     => array( '<hr><!----><hr id=after>' ),
			'Empty comment with four dashes, improperly closed' => array( '<hr><!----!><hr id=after>--><hr id=final>' ),
			'Comment with four dashes, improperly closed twice' => array( '<hr><!----!><hr id=after>--!><hr id=final>' ),
			'Comment with almost-closer inside'  => array( '<hr><!-- ---!><hr id=after>--!><hr id=final>' ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 *
	 * @dataProvider data_skips_contents_of_script_and_rcdata_regions
	 *
	 * @param $input_html HTML with multiple divs, one of which carries the "target" attribute.
	 */
	public function test_skips_contents_of_script_and_rcdata_regions( $input_html ) {
		$processor = new WP_HTML_Tag_Processor( $input_html );
		$processor->next_tag( 'div' );

		$this->assertTrue(
			$processor->get_attribute( 'target' ),
			'Did not properly skip over script and rcdata regions; incorrectly found tags inside'
		);
	}

	/**
	 * Data provider
	 *
	 * @return array[]
	 */
	public static function data_skips_contents_of_script_and_rcdata_regions() {
		return array(
			'Balanced SCRIPT tags'                => array( '<script>console.log("<div>");</script><div target><div>' ),
			'Unexpected SCRIPT closer after DIV'  => array( 'console.log("<div target>")</script><div><div>' ),
			'Unexpected SCRIPT closer before DIV' => array( 'console.log("<span>")</script><div target><div>' ),
			'Missing SCRIPT closer'               => array( '<script>console.log("<div>");<div><div></script><div target>' ),
			'TITLE before DIV'                    => array( '<title><div></title><div target><div>' ),
			'SCRIPT inside TITLE'                 => array( '<title><script><div></title><div target><div></script><div>' ),
			'TITLE in TEXTAREA'                   => array( '<textarea><div><title><div></textarea><div target></title><div>' ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_can_query_and_update_wrongly_nested_tags() {
		$processor = new WP_HTML_Tag_Processor(
			'<span>123<p>456</span>789</p>'
		);
		$processor->next_tag( 'span' );
		$processor->set_attribute( 'class', 'span-class' );
		$processor->next_tag( 'p' );
		$processor->set_attribute( 'class', 'p-class' );
		$this->assertSame(
			'<span class="span-class">123<p class="p-class">456</span>789</p>',
			$processor->get_updated_html(),
			'Did not find overlapping p tag'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_removing_specific_attributes_in_malformed_html() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_MALFORMED );
		$processor->next_tag( 'span' );
		$processor->remove_attribute( 'Notifications<' );
		$this->assertSame(
			'<div><span class="d-md-none" /span><span class="d-none d-md-inline">Back to notifications</span></div>',
			$processor->get_updated_html(),
			'Did not remove "Notifications<" attribute in malformed input'
		);
	}

	/**
	 * Ensures that no tags are matched in a document containing only non-tag content.
	 *
	 * @ticket 60122
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::paused_at_incomplete_token
	 *
	 * @dataProvider data_html_without_tags
	 *
	 * @param string $html_without_tags HTML without any tags in it.
	 */
	public function test_next_tag_returns_false_when_there_are_no_tags( $html_without_tags ) {
		$processor = new WP_HTML_Tag_Processor( $html_without_tags );

		$this->assertFalse(
			$processor->next_tag(),
			"Shouldn't have found any tags but found {$processor->get_tag()}."
		);

		$this->assertFalse(
			$processor->paused_at_incomplete_token(),
			'Should have indicated that end of document was reached without evidence that elements were truncated.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_html_without_tags() {
		return array(
			'DOCTYPE declaration'    => array( '<!DOCTYPE html>Just some HTML' ),
			'No tags'                => array( 'this is nothing more than a text node' ),
			'Text with comments'     => array( 'One <!-- sneaky --> comment.' ),
			'Empty tag closer'       => array( '</>' ),
			'CDATA as HTML comment'  => array( '<![CDATA[this closes at the first &gt;]>' ),
			'Processing instruction' => array( '<?xml version="1.0"?>' ),
			'Combination XML-like'   => array( '<!DOCTYPE xml><?xml version=""?><!-- this is not a real document. --><![CDATA[it only serves as a test]]>' ),
		);
	}

	/**
	 * Ensures that the processor doesn't attempt to match an incomplete token.
	 *
	 * @ticket 58637
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::paused_at_incomplete_token
	 *
	 * @dataProvider data_incomplete_syntax_elements
	 *
	 * @param string $incomplete_html HTML text containing some kind of incomplete syntax.
	 */
	public function test_next_tag_returns_false_for_incomplete_syntax_elements( $incomplete_html ) {
		$processor = new WP_HTML_Tag_Processor( $incomplete_html );

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
			'Incomplete tag (no attributes)'       => array( '<div' ),
			'Incomplete tag (attributes)'          => array( '<div inert title="test"' ),
			'Incomplete attribute (unquoted)'      => array( '<button disabled' ),
			'Incomplete attribute (single quoted)' => array( "<li class='just-another class" ),
			'Incomplete attribute (double quoted)' => array( '<iframe src="https://www.example.com/embed/abcdef' ),
			'Incomplete comment (normative)'       => array( '<!-- without end' ),
			'Incomplete comment (missing --)'      => array( '<!-- without end --' ),
			'Incomplete comment (--!)'             => array( '<!-- without end --!' ),
			'Incomplete comment (bogus comment)'   => array( '</3 is not a tag' ),
			'Incomplete DOCTYPE'                   => array( '<!DOCTYPE html' ),
			'Partial DOCTYPE'                      => array( '<!DOCTY' ),
			'Incomplete CDATA'                     => array( '<![CDATA[something inside of here needs to get out' ),
			'Partial CDATA'                        => array( '<![CDA' ),
			'Partially closed CDATA]'              => array( '<![CDATA[cannot escape]' ),
			'Unclosed IFRAME'                      => array( '<iframe><div>' ),
			'Unclosed NOEMBED'                     => array( '<noembed><div>' ),
			'Unclosed NOFRAMES'                    => array( '<noframes><div>' ),
			'Unclosed SCRIPT'                      => array( '<script><div>' ),
			'Unclosed STYLE'                       => array( '<style><div>' ),
			'Unclosed TEXTAREA'                    => array( '<textarea><div>' ),
			'Unclosed TITLE'                       => array( '<title><div>' ),
			'Unclosed XMP'                         => array( '<xmp><div>' ),
			'Partially closed IFRAME'              => array( '<iframe><div></iframe' ),
			'Partially closed NOEMBED'             => array( '<noembed><div></noembed' ),
			'Partially closed NOFRAMES'            => array( '<noframes><div></noframes' ),
			'Partially closed SCRIPT'              => array( '<script><div></script' ),
			'Partially closed STYLE'               => array( '<style><div></style' ),
			'Partially closed TEXTAREA'            => array( '<textarea><div></textarea' ),
			'Partially closed TITLE'               => array( '<title><div></title' ),
			'Partially closed XMP'                 => array( '<xmp><div></xmp' ),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_updating_specific_attributes_in_malformed_html() {
		$processor = new WP_HTML_Tag_Processor( self::HTML_MALFORMED );
		$processor->next_tag( 'span' );
		$processor->set_attribute( 'id', 'first' );
		$processor->next_tag( 'span' );
		$processor->set_attribute( 'id', 'second' );
		$this->assertSame(
			'<div><span id="first" class="d-md-none" Notifications</span><span id="second" class="d-none d-md-inline">Back to notifications</span></div>',
			$processor->get_updated_html(),
			'Did not add id attributes properly to malformed input'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 *
	 * @dataProvider data_updating_attributes
	 *
	 * @param string $html     HTML to process.
	 * @param string $expected Expected updated HTML.
	 */
	public function test_updating_attributes( $html, $expected ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();
		$processor->set_attribute( 'foo', 'bar' );
		$processor->add_class( 'firstTag' );
		$processor->next_tag();
		$processor->add_class( 'secondTag' );

		$this->assertSame(
			$expected,
			$processor->get_updated_html(),
			'Did not properly add attributes and class names'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_updating_attributes() {
		return array(
			'tags inside of a comment' => array(
				'input'    => '<!-- this is a comment. no <strong>tags</strong> allowed --><span>test</span>',
				'expected' => '<!-- this is a comment. no <strong>tags</strong> allowed --><span class="firstTag" foo="bar">test</span>',
			),
			'does not parse <3'        => array(
				'input'    => '<3 is a heart but <t3> is a tag.<span>test</span>',
				'expected' => '<3 is a heart but <t3 class="firstTag" foo="bar"> is a tag.<span class="secondTag">test</span>',
			),
			'does not parse <*'        => array(
				'input'    => 'The applicative operator <* works well in Haskell; is what?<span>test</span>',
				'expected' => 'The applicative operator <* works well in Haskell; is what?<span class="firstTag" foo="bar">test</span>',
			),
			'</> in content'           => array(
				'input'    => '</><span>test</span>',
				'expected' => '</><span class="firstTag" foo="bar">test</span>',
			),
			'custom asdf attribute'    => array(
				'input'    => '<hr asdf="test"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" asdf="test"><span class="secondTag">test</span>',
			),
			'custom data-* attribute'  => array(
				'input'    => '<div data-foo="bar"><p>Some content for a <span>test</span></p></div>',
				'expected' => '<div class="firstTag" foo="bar" data-foo="bar"><p class="secondTag">Some content for a <span>test</span></p></div>',
			),
			'tag inside of CDATA'      => array(
				'input'    => '<![CDATA[This <is> a <strong id="yes">HTML Tag</strong>]]><span>test</span>',
				'expected' => '<![CDATA[This <is> a <strong class="firstTag" foo="bar" id="yes">HTML Tag</strong>]]><span class="secondTag">test</span>',
			),
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 *
	 * @dataProvider data_updating_attributes_in_malformed_html
	 *
	 * @param string $html     HTML to process.
	 * @param string $expected Expected updated HTML.
	 */
	public function test_updating_attributes_in_malformed_html( $html, $expected ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag(), 'Could not find first tag.' );
		$processor->set_attribute( 'foo', 'bar' );
		$processor->add_class( 'firstTag' );
		$processor->next_tag();
		$processor->add_class( 'secondTag' );

		$this->assertSame(
			$expected,
			$processor->get_updated_html(),
			'Did not properly update attributes and classnames given malformed input'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_updating_attributes_in_malformed_html() {
		return array(
			'Invalid entity inside attribute value'        => array(
				'input'    => '<img src="https://s0.wp.com/i/atat.png" title="&; First &lt;title&gt; is &notit;" TITLE="second title" title="An Imperial &imperial; AT-AT"><span>test</span>',
				'expected' => '<img class="firstTag" foo="bar" src="https://s0.wp.com/i/atat.png" title="&; First &lt;title&gt; is &notit;" TITLE="second title" title="An Imperial &imperial; AT-AT"><span class="secondTag">test</span>',
			),
			'HTML tag opening inside attribute value'      => array(
				'input'    => '<pre id="<code" class="wp-block-code <code is poetry&gt;"><code>This &lt;is> a &lt;strong is="true">thing.</code></pre><span>test</span>',
				'expected' => '<pre foo="bar" id="<code" class="wp-block-code &lt;code is poetry&gt; firstTag"><code class="secondTag">This &lt;is> a &lt;strong is="true">thing.</code></pre><span>test</span>',
			),
			'HTML tag brackets in attribute values and data markup' => array(
				'input'    => '<pre id="<code-&gt;-block-&gt;" class="wp-block-code <code is poetry&gt;"><code>This &lt;is> a &lt;strong is="true">thing.</code></pre><span>test</span>',
				'expected' => '<pre foo="bar" id="<code-&gt;-block-&gt;" class="wp-block-code &lt;code is poetry&gt; firstTag"><code class="secondTag">This &lt;is> a &lt;strong is="true">thing.</code></pre><span>test</span>',
			),
			'Single and double quotes in attribute value'  => array(
				'input'    => '<p title="Demonstrating how to use single quote (\') and double quote (&quot;)"><span>test</span>',
				'expected' => '<p class="firstTag" foo="bar" title="Demonstrating how to use single quote (\') and double quote (&quot;)"><span class="secondTag">test</span>',
			),
			'Unquoted attribute values'                    => array(
				'input'    => '<hr a=1 a=2 a=3 a=5 /><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" a=1 a=2 a=3 a=5 /><span class="secondTag">test</span>',
			),
			'Double-quotes escaped in double-quote attribute value' => array(
				'input'    => '<hr title="This is a &quot;double-quote&quot;"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" title="This is a &quot;double-quote&quot;"><span class="secondTag">test</span>',
			),
			'Unquoted attribute value'                     => array(
				'input'    => '<hr id=code><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id=code><span class="secondTag">test</span>',
			),
			'Unquoted attribute value with tag-like value' => array(
				'input'    => '<hr id= 	<code> ><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id= 	<code> ><span class="secondTag">test</span>',
			),
			'Unquoted attribute value with tag-like value followed by tag-like data' => array(
				'input'    => '<hr id=code>><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id=code>><span class="secondTag">test</span>',
			),
			'id=&quo;code'                                 => array(
				'input'    => '<hr id=&quo;code><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id=&quo;code><span class="secondTag">test</span>',
			),
			'id/test=5'                                    => array(
				'input'    => '<hr id/test=5><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id/test=5><span class="secondTag">test</span>',
			),
			'<hr> as the id value'                         => array(
				'input'    => '<hr title="<hr>"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" title="<hr>"><span class="secondTag">test</span>',
			),
			'id=>code'                                     => array(
				'input'    => '<hr id=>code><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id=>code><span class="secondTag">test</span>',
			),
			'id"quo="test"'                                => array(
				'input'    => '<hr id"quo="test"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id"quo="test"><span class="secondTag">test</span>',
			),
			'id without double quotation marks around null byte' => array(
				'input'    => "<hr id\x00zero=\"test\"><span>test</span>",
				'expected' => "<hr class=\"firstTag\" foo=\"bar\" id\x00zero=\"test\"><span class=\"secondTag\">test</span>",
			),
			'Unexpected > before an attribute'             => array(
				'input'    => '<hr >id="test"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" >id="test"><span class="secondTag">test</span>',
			),
			'Unexpected = before an attribute'             => array(
				'input'    => '<hr =id="test"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" =id="test"><span class="secondTag">test</span>',
			),
			'Unexpected === before an attribute'           => array(
				'input'    => '<hr ===name="value"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" ===name="value"><span class="secondTag">test</span>',
			),
			'Missing closing data-tag tag'                 => array(
				'input'    => 'The applicative operator <* works well in Haskell; <data-tag> is what?<span>test</span>',
				'expected' => 'The applicative operator <* works well in Haskell; <data-tag class="firstTag" foo="bar"> is what?<span class="secondTag">test</span>',
			),
			'Missing closing t3 tag'                       => array(
				'input'    => '<3 is a heart but <t3> is a tag.<span>test</span>',
				'expected' => '<3 is a heart but <t3 class="firstTag" foo="bar"> is a tag.<span class="secondTag">test</span>',
			),
			'invalid comment opening tag'                  => array(
				'input'    => '<?comment --><span>test</span>',
				'expected' => '<?comment --><span class="firstTag" foo="bar">test</span>',
			),
			'=asdf as attribute name'                      => array(
				'input'    => '<hr =asdf="tes"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" =asdf="tes"><span class="secondTag">test</span>',
			),
			'== as attribute name with value'              => array(
				'input'    => '<hr ==="test"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" ==="test"><span class="secondTag">test</span>',
			),
			'=5 as attribute'                              => array(
				'input'    => '<hr =5><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" =5><span class="secondTag">test</span>',
			),
			'= as attribute'                               => array(
				'input'    => '<hr =><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" =><span class="secondTag">test</span>',
			),
			'== as attribute'                              => array(
				'input'    => '<hr ==><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" ==><span class="secondTag">test</span>',
			),
			'=== as attribute'                             => array(
				'input'    => '<hr ===><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" ===><span class="secondTag">test</span>',
			),
			'unsupported disabled attribute'               => array(
				'input'    => '<hr disabled><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" disabled><span class="secondTag">test</span>',
			),
			'malformed custom attributes'                  => array(
				'input'    => '<hr a"sdf="test"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" a"sdf="test"><span class="secondTag">test</span>',
			),
			'Multiple unclosed tags treated as a single tag' => array(
				'input'    => <<<HTML
					<hr id=">"code
					<hr id="value>"code
					<hr id="/>"code
					<hr id="value/>"code
					/>
					<span>test</span>
HTML
				,
				'expected' => <<<HTML
					<hr class="firstTag" foo="bar" id=">"code
					<hr id="value>"code
					<hr id="/>"code
					<hr id="value/>"code
					/>
					<span class="secondTag">test</span>
HTML
			,
			),
			'<hr id   =5>'                                 => array(
				'input'    => '<hr id   =5><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id   =5><span class="secondTag">test</span>',
			),
			'<hr id a  =5>'                                => array(
				'input'    => '<hr id a  =5><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id a  =5><span class="secondTag">test</span>',
			),
		);
	}

	/**
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_handles_malformed_taglike_open_short_html() {
		$processor = new WP_HTML_Tag_Processor( '<' );
		$result    = $processor->next_tag();
		$this->assertFalse( $result, 'Did not handle "<" html properly.' );
	}

	/**
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_handles_malformed_taglike_close_short_html() {
		$processor = new WP_HTML_Tag_Processor( '</ ' );
		$result    = $processor->next_tag();
		$this->assertFalse( $result, 'Did not handle "</ " html properly.' );
	}

	/**
	 * Ensures that non-tag syntax starting with `<` is consumed inside a text node.
	 *
	 * @ticket 60385
	 */
	public function test_single_text_node_with_taglike_text() {
		$processor = new WP_HTML_Tag_Processor( 'test< /A>' );
		$processor->next_token();
		$this->assertSame( '#text', $processor->get_token_type(), 'Did not find text node.' );
		$this->assertSame( 'test< /A>', $processor->get_modifiable_text(), 'Did not find complete text node.' );
	}

	/**
	 * Ensures that updates which are enqueued in front of the cursor
	 * are applied before moving forward in the document.
	 *
	 * @ticket 60697
	 */
	public function test_applies_updates_before_proceeding() {
		$html = '<div><img></div><div><img></div>';

		$subclass = new class( $html ) extends WP_HTML_Tag_Processor {
			/**
			 * Inserts raw text after the current token.
			 *
			 * @param string $new_html Raw text to insert.
			 */
			public function insert_after( $new_html ) {
				$this->set_bookmark( 'here' );
				$this->lexical_updates[] = new WP_HTML_Text_Replacement(
					$this->bookmarks['here']->start + $this->bookmarks['here']->length,
					0,
					$new_html
				);
			}
		};

		$subclass->next_tag( 'img' );
		$subclass->insert_after( '<p>snow-capped</p>' );

		$subclass->next_tag();
		$this->assertSame(
			'P',
			$subclass->get_tag(),
			'Should have matched inserted HTML as next tag.'
		);

		$subclass->next_tag( 'img' );
		$subclass->set_attribute( 'alt', 'mountain' );

		$this->assertSame(
			'<div><img><p>snow-capped</p></div><div><img alt="mountain"></div>',
			$subclass->get_updated_html(),
			'Should have properly applied the update from in front of the cursor.'
		);
	}

	/**
	 * Test an infinite loop bugfix in incomplete script tag parsing.
	 *
	 * @small
	 *
	 * @ticket 61810
	 */
	public function test_script_tag_processing_no_infinite_loop_final_dash() {
		$processor = new WP_HTML_Tag_Processor( '<script>-' );

		$this->assertFalse( $processor->next_tag() );
		$this->assertTrue( $processor->paused_at_incomplete_token() );
	}

	/**
	 * Test an infinite loop bugfix in incomplete script tag parsing.
	 *
	 * @small
	 *
	 * @ticket 61810
	 */
	public function test_script_tag_processing_no_infinite_loop_final_left_angle_bracket() {
		$processor = new WP_HTML_Tag_Processor( '<script><' );

		$this->assertFalse( $processor->next_tag() );
		$this->assertTrue( $processor->paused_at_incomplete_token() );
	}

	/**
	 * Test a bugfix where the input ends abruptly with a funky comment started.
	 *
	 * @ticket 61831
	 */
	public function test_unclosed_funky_comment_input_too_short() {
		$processor = new WP_HTML_Tag_Processor( '</#' );
		$this->assertFalse( $processor->next_tag() );
		$this->assertTrue( $processor->paused_at_incomplete_token() );
	}

	/**
	 * Test basic DOCTYPE handling.
	 *
	 * @ticket 61576
	 */
	public function test_doctype_doc_name() {
		$processor = new WP_HTML_Tag_Processor( '<!DOCTYPE html>' );
		$this->assertTrue( $processor->next_token() );
		$doctype = $processor->get_doctype_info();
		$this->assertNotNull( $doctype );
		$this->assertSame( 'html', $doctype->name );
		$this->assertSame( 'no-quirks', $doctype->indicated_compatability_mode );
		$this->assertNull( $doctype->public_identifier );
		$this->assertNull( $doctype->system_identifier );
	}
}
