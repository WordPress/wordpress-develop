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
class Tests_HtmlApi_wpHtmlTagProcessor extends WP_UnitTestCase {
	const HTML_SIMPLE       = '<div id="first"><span id="second">Text</span></div>';
	const HTML_WITH_CLASSES = '<div class="main with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>';
	const HTML_MALFORMED    = '<div><span class="d-md-none" Notifications</span><span class="d-none d-md-inline">Back to notifications</span></div>';

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_null_before_finding_tags() {
		$p = new WP_HTML_Tag_Processor( '<div>Test</div>' );

		$this->assertNull( $p->get_tag(), 'Calling get_tag() without selecting a tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_null_when_not_in_open_tag() {
		$p = new WP_HTML_Tag_Processor( '<div>Test</div>' );

		$this->assertFalse( $p->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertNull( $p->get_tag(), 'Accessing a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_tag
	 */
	public function test_get_tag_returns_open_tag_name() {
		$p = new WP_HTML_Tag_Processor( '<div>Test</div>' );

		$this->assertTrue( $p->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertSame( 'DIV', $p->get_tag(), 'Accessing an existing tag name did not return "div"' );
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
		$p = new WP_HTML_Tag_Processor( $html );
		$p->next_tag( array( 'tag_closers' => 'visit' ) );

		if ( $flag_is_set ) {
			$this->assertTrue( $p->has_self_closing_flag(), 'Did not find the self-closing tag when it was present.' );
		} else {
			$this->assertFalse( $p->has_self_closing_flag(), 'Found the self-closing tag when it was absent.' );
		}
	}

	/**
	 * Data provider. HTML tags which might have a self-closing flag, and an indicator if they do.
	 *
	 * @return array[]
	 */
	public function data_has_self_closing_flag() {
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
		$p = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertNull( $p->get_attribute( 'class' ), 'Accessing an attribute without selecting a tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_not_in_open_tag() {
		$p = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertFalse( $p->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertNull( $p->get_attribute( 'class' ), 'Accessing an attribute of a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_in_closing_tag() {
		$p = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $p->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertTrue( $p->next_tag( array( 'tag_closers' => 'visit' ) ), 'Querying an existing closing tag did not return true' );
		$this->assertNull( $p->get_attribute( 'class' ), 'Accessing an attribute of a closing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_null_when_attribute_missing() {
		$p = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $p->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertNull( $p->get_attribute( 'test-id' ), 'Accessing a non-existing attribute did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_attribute_value() {
		$p = new WP_HTML_Tag_Processor( '<div class="test">Test</div>' );

		$this->assertTrue( $p->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$this->assertSame( 'test', $p->get_attribute( 'class' ), 'Accessing a class="test" attribute value did not return "test"' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_true_for_boolean_attribute() {
		$p = new WP_HTML_Tag_Processor( '<div enabled class="test">Test</div>' );

		$this->assertTrue( $p->next_tag( array( 'class_name' => 'test' ) ), 'Querying an existing tag did not return true' );
		$this->assertTrue( $p->get_attribute( 'enabled' ), 'Accessing a boolean "enabled" attribute value did not return true' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_string_for_truthy_attributes() {
		$p = new WP_HTML_Tag_Processor( '<div enabled=enabled checked=1 hidden="true" class="test">Test</div>' );

		$this->assertTrue( $p->next_tag(), 'Querying an existing tag did not return true' );
		$this->assertSame( 'enabled', $p->get_attribute( 'enabled' ), 'Accessing a boolean "enabled" attribute value did not return true' );
		$this->assertSame( '1', $p->get_attribute( 'checked' ), 'Accessing a checked=1 attribute value did not return "1"' );
		$this->assertSame( 'true', $p->get_attribute( 'hidden' ), 'Accessing a hidden="true" attribute value did not return "true"' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_decodes_html_character_references() {
		$p = new WP_HTML_Tag_Processor( '<div id="the &quot;grande&quot; is &lt; &#x033;&#50;oz&dagger;"></div>' );
		$p->next_tag();

		$this->assertSame( 'the "grande" is < 32oz†', $p->get_attribute( 'id' ), 'HTML Attribute value was returned without decoding character references' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_attributes_parser_treats_slash_as_attribute_separator() {
		$p = new WP_HTML_Tag_Processor( '<div a/b/c/d/e="test">Test</div>' );

		$this->assertTrue( $p->next_tag(), 'Querying an existing tag did not return true' );
		$this->assertTrue( $p->get_attribute( 'a' ), 'Accessing an existing attribute did not return true' );
		$this->assertTrue( $p->get_attribute( 'b' ), 'Accessing an existing attribute did not return true' );
		$this->assertTrue( $p->get_attribute( 'c' ), 'Accessing an existing attribute did not return true' );
		$this->assertTrue( $p->get_attribute( 'd' ), 'Accessing an existing attribute did not return true' );
		$this->assertSame( 'test', $p->get_attribute( 'e' ), 'Accessing an existing e="test" did not return "test"' );
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
		$p = new WP_HTML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
		$p->next_tag();

		$this->assertSame(
			'true',
			$p->get_attribute( $attribute_name ),
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
		$p = new WP_HTML_Tag_Processor( '<div DATA-enabled>Test</div>' );
		$p->next_tag();

		$this->assertTrue(
			$p->get_attribute( $attribute_name ),
			'Accessing an attribute by a differently-cased name did not return its value'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_attribute_name_case_variants() {
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
		$p = new WP_HTML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
		$p->next_tag();
		$p->remove_attribute( 'data-enabled' );

		$this->assertSame( '<div >Test</div>', $p->get_updated_html(), 'A case-insensitive remove_attribute call did not remove the attribute' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_is_case_insensitive() {
		$p = new WP_HTML_Tag_Processor( '<div DATA-enabled="true">Test</div>' );
		$p->next_tag();
		$p->set_attribute( 'data-enabled', 'abc' );

		$this->assertSame( '<div data-enabled="abc">Test</div>', $p->get_updated_html(), 'A case-insensitive set_attribute call did not update the existing attribute' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_before_finding_tags() {
		$p = new WP_HTML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$this->assertNull(
			$p->get_attribute_names_with_prefix( 'data-' ),
			'Accessing attributes by their prefix did not return null when no tag was selected'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_when_not_in_open_tag() {
		$p = new WP_HTML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$p->next_tag( 'p' );
		$this->assertNull( $p->get_attribute_names_with_prefix( 'data-' ), 'Accessing attributes of a non-existing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_null_when_in_closing_tag() {
		$p = new WP_HTML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$p->next_tag( 'div' );
		$p->next_tag( array( 'tag_closers' => 'visit' ) );

		$this->assertNull( $p->get_attribute_names_with_prefix( 'data-' ), 'Accessing attributes of a closing tag did not return null' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_empty_array_when_no_attributes_present() {
		$p = new WP_HTML_Tag_Processor( '<div>Test</div>' );
		$p->next_tag( 'div' );

		$this->assertSame( array(), $p->get_attribute_names_with_prefix( 'data-' ), 'Accessing the attributes on a tag without any did not return an empty array' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_matching_attribute_names_in_lowercase() {
		$p = new WP_HTML_Tag_Processor( '<div DATA-enabled class="test" data-test-ID="14">Test</div>' );
		$p->next_tag();

		$this->assertSame(
			array( 'data-enabled', 'data-test-id' ),
			$p->get_attribute_names_with_prefix( 'data-' ),
			'Accessing attributes by their prefix did not return their lowercase names'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute_names_with_prefix
	 */
	public function test_get_attribute_names_with_prefix_returns_attribute_added_by_set_attribute() {
		$p = new WP_HTML_Tag_Processor( '<div data-foo="bar">Test</div>' );
		$p->next_tag();
		$p->set_attribute( 'data-test-id', '14' );

		$this->assertSame(
			'<div data-test-id="14" data-foo="bar">Test</div>',
			$p->get_updated_html(),
			"Updated HTML doesn't include attribute added via set_attribute"
		);
		$this->assertSame(
			array( 'data-test-id', 'data-foo' ),
			$p->get_attribute_names_with_prefix( 'data-' ),
			"Accessing attribute names doesn't find attribute added via set_attribute"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::__toString
	 */
	public function test_to_string_returns_updated_html() {
		$p = new WP_HTML_Tag_Processor( '<hr id="remove" /><div enabled class="test">Test</div><span id="span-id"></span>' );
		$p->next_tag();
		$p->remove_attribute( 'id' );

		$p->next_tag();
		$p->set_attribute( 'id', 'div-id-1' );
		$p->add_class( 'new_class_1' );

		$this->assertSame(
			$p->get_updated_html(),
			(string) $p,
			'get_updated_html() returned a different value than __toString()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_updated_html
	 */
	public function test_get_updated_html_applies_the_updates_so_far_and_keeps_the_processor_on_the_current_tag() {
		$p = new WP_HTML_Tag_Processor( '<hr id="remove" /><div enabled class="test">Test</div><span id="span-id"></span>' );
		$p->next_tag();
		$p->remove_attribute( 'id' );

		$p->next_tag();
		$p->set_attribute( 'id', 'div-id-1' );
		$p->add_class( 'new_class_1' );

		$this->assertSame(
			'<hr  /><div id="div-id-1" enabled class="test new_class_1">Test</div><span id="span-id"></span>',
			$p->get_updated_html(),
			'Calling get_updated_html after updating the attributes of the second tag returned different HTML than expected'
		);

		$p->set_attribute( 'id', 'div-id-2' );
		$p->add_class( 'new_class_2' );

		$this->assertSame(
			'<hr  /><div id="div-id-2" enabled class="test new_class_1 new_class_2">Test</div><span id="span-id"></span>',
			$p->get_updated_html(),
			'Calling get_updated_html after updating the attributes of the second tag for the second time returned different HTML than expected'
		);

		$p->next_tag();
		$p->remove_attribute( 'id' );

		$this->assertSame(
			'<hr  /><div id="div-id-2" enabled class="test new_class_1 new_class_2">Test</div><span ></span>',
			$p->get_updated_html(),
			'Calling get_updated_html after removing the id attribute of the third tag returned different HTML than expected'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_updated_html
	 */
	public function test_get_updated_html_without_updating_any_attributes_returns_the_original_html() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );

		$this->assertSame(
			self::HTML_SIMPLE,
			$p->get_updated_html(),
			'Casting WP_HTML_Tag_Processor to a string without performing any updates did not return the initial HTML snippet'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_next_tag_with_no_arguments_should_find_the_next_existing_tag() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );

		$this->assertTrue( $p->next_tag(), 'Querying an existing tag did not return true' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_next_tag_should_return_false_for_a_non_existing_tag() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );

		$this->assertFalse( $p->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
	}

	/**
	 * @ticket 56299
	 * @ticket 57852
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::is_tag_closer
	 */
	public function test_next_tag_should_stop_on_closers_only_when_requested() {
		$p = new WP_HTML_Tag_Processor( '<div><img /></div>' );

		$this->assertTrue( $p->next_tag( array( 'tag_name' => 'div' ) ), 'Did not find desired tag opener' );
		$this->assertFalse( $p->next_tag( array( 'tag_name' => 'div' ) ), 'Visited an unwanted tag, a tag closer' );

		$p = new WP_HTML_Tag_Processor( '<div><img /></div>' );
		$p->next_tag(
			array(
				'tag_name'    => 'div',
				'tag_closers' => 'visit',
			)
		);

		$this->assertFalse( $p->is_tag_closer(), 'Indicated a tag opener is a tag closer' );
		$this->assertTrue(
			$p->next_tag(
				array(
					'tag_name'    => 'div',
					'tag_closers' => 'visit',
				)
			),
			'Did not stop at desired tag closer'
		);
		$this->assertTrue( $p->is_tag_closer(), 'Indicated a tag closer is a tag opener' );

		$p = new WP_HTML_Tag_Processor( '<div>' );
		$this->assertTrue( $p->next_tag( array( 'tag_closers' => 'visit' ) ), "Did not find a tag opener when tag_closers was set to 'visit'" );
		$this->assertFalse( $p->next_tag( array( 'tag_closers' => 'visit' ) ), "Found a closer where there wasn't one" );
	}

	/**
	 * @ticket 57852
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::is_tag_closer
	 */
	public function test_next_tag_should_stop_on_rcdata_and_script_tag_closers_when_requested() {
		$p = new WP_HTML_Tag_Processor( '<script>abc</script>' );

		$p->next_tag();
		$this->assertTrue( $p->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </script> tag closer' );
		$this->assertTrue( $p->is_tag_closer(), 'Indicated a <script> tag opener is a tag closer' );

		$p = new WP_HTML_Tag_Processor( 'abc</script>' );
		$this->assertTrue( $p->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </script> tag closer when there was no tag opener' );

		$p = new WP_HTML_Tag_Processor( '<textarea>abc</textarea>' );

		$p->next_tag();
		$this->assertTrue( $p->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </textarea> tag closer' );
		$this->assertTrue( $p->is_tag_closer(), 'Indicated a <textarea> tag opener is a tag closer' );

		$p = new WP_HTML_Tag_Processor( 'abc</textarea>' );
		$this->assertTrue( $p->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </textarea> tag closer when there was no tag opener' );

		$p = new WP_HTML_Tag_Processor( '<title>abc</title>' );

		$p->next_tag();
		$this->assertTrue( $p->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </title> tag closer' );
		$this->assertTrue( $p->is_tag_closer(), 'Indicated a <title> tag opener is a tag closer' );

		$p = new WP_HTML_Tag_Processor( 'abc</title>' );
		$this->assertTrue( $p->next_tag( array( 'tag_closers' => 'visit' ) ), 'Did not find the </title> tag closer when there was no tag opener' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_on_a_non_existing_tag_does_not_change_the_markup() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );

		$this->assertFalse( $p->next_tag( 'p' ), 'Querying a non-existing tag did not return false' );
		$this->assertFalse( $p->next_tag( 'div' ), 'Querying a non-existing tag did not return false' );

		$p->set_attribute( 'id', 'primary' );

		$this->assertSame(
			self::HTML_SIMPLE,
			$p->get_updated_html(),
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
		$p = new WP_HTML_Tag_Processor( '<div id=3></div invalid-id=4>' );
		$p->next_tag(
			array(
				'tag_name'    => 'div',
				'tag_closers' => 'visit',
			)
		);

		$this->assertFalse( $p->is_tag_closer(), 'Skipped tag opener' );

		$p->next_tag(
			array(
				'tag_name'    => 'div',
				'tag_closers' => 'visit',
			)
		);

		$this->assertTrue( $p->is_tag_closer(), 'Skipped tag closer' );
		$this->assertFalse( $p->set_attribute( 'id', 'test' ), "Allowed setting an attribute on a tag closer when it shouldn't have" );
		$this->assertFalse( $p->remove_attribute( 'invalid-id' ), "Allowed removing an attribute on a tag closer when it shouldn't have" );
		$this->assertFalse( $p->add_class( 'sneaky' ), "Allowed adding a class on a tag closer when it shouldn't have" );
		$this->assertFalse( $p->remove_class( 'not-appearing-in-this-test' ), "Allowed removing a class on a tag closer when it shouldn't have" );
		$this->assertSame(
			'<div id=3></div invalid-id=4>',
			$p->get_updated_html(),
			'Calling get_updated_html after updating a non-existing tag returned an HTML that was different from the original HTML'
		);
	}

	/**
	 * Passing a double quote inside of an attribute value could lead to an XSS attack as follows:
	 *
	 * ```php
	 *     $p = new WP_HTML_Tag_Processor( '<div class="header"></div>' );
	 *     $p->next_tag();
	 *     $p->set_attribute('class', '" onclick="alert');
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
		$p = new WP_HTML_Tag_Processor( '<div></div>' );
		$p->next_tag();
		$p->set_attribute( 'test', $attribute_value );

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
		preg_match( '~^<div test=(.*)></div>$~', $p->get_updated_html(), $match );
		list( , $actual_value ) = $match;

		$this->assertSame( '"' . esc_attr( $attribute_value ) . '"', $actual_value, 'Entities were not properly escaped in the attribute value' );
	}

	/**
	 * Data provider.
	 *
	 * @return string[][].
	 */
	public function data_set_attribute_prevents_xss() {
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
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->set_attribute( 'test-attribute', 'test-value' );

		$this->assertSame(
			'<div test-attribute="test-value" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not include attribute added via set_attribute()'
		);
		$this->assertSame(
			'test-value',
			$p->get_attribute( 'test-attribute' ),
			'get_attribute() (called after get_updated_html()) did not return attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_updated_values_before_they_are_applied() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->set_attribute( 'test-attribute', 'test-value' );

		$this->assertSame(
			'test-value',
			$p->get_attribute( 'test-attribute' ),
			'get_attribute() (called before get_updated_html()) did not return attribute added via set_attribute()'
		);
		$this->assertSame(
			'<div test-attribute="test-value" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not include attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_returns_updated_values_before_they_are_applied_with_different_name_casing() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->set_attribute( 'test-ATTribute', 'test-value' );

		$this->assertSame(
			'test-value',
			$p->get_attribute( 'test-attribute' ),
			'get_attribute() (called before get_updated_html()) did not return attribute added via set_attribute()'
		);
		$this->assertSame(
			'<div test-ATTribute="test-value" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not include attribute added via set_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_added_class_names_before_they_are_applied() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->add_class( 'my-class' );

		$this->assertSame(
			'my-class',
			$p->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) did not return class name added via add_class()'
		);
		$this->assertSame(
			'<div class="my-class" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not include class name added via add_class()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_added_class_names_before_they_are_applied_and_retains_classes_from_previous_add_class_calls() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->add_class( 'my-class' );

		$this->assertSame(
			'my-class',
			$p->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) did not return class name added via add_class()'
		);

		$p->add_class( 'my-other-class' );

		$this->assertSame(
			'my-class my-other-class',
			$p->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) did not return class names added via subsequent add_class() calls'
		);
		$this->assertSame(
			'<div class="my-class my-other-class" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not include class names added via subsequent add_class() calls'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_removed_attribute_before_it_is_applied() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->remove_attribute( 'id' );

		$this->assertNull(
			$p->get_attribute( 'id' ),
			'get_attribute() (called before get_updated_html()) returned attribute that was removed by remove_attribute()'
		);
		$this->assertSame(
			'<div ><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML includes attribute that was removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_adding_and_then_removing_an_attribute_before_those_updates_are_applied() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->set_attribute( 'test-attribute', 'test-value' );
		$p->remove_attribute( 'test-attribute' );

		$this->assertNull(
			$p->get_attribute( 'test-attribute' ),
			'get_attribute() (called before get_updated_html()) returned attribute that was added via set_attribute() and then removed by remove_attribute()'
		);
		$this->assertSame(
			self::HTML_SIMPLE,
			$p->get_updated_html(),
			'Updated HTML includes attribute that was added via set_attribute() and then removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_setting_and_then_removing_an_existing_attribute_before_those_updates_are_applied() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->set_attribute( 'id', 'test-value' );
		$p->remove_attribute( 'id' );

		$this->assertNull(
			$p->get_attribute( 'id' ),
			'get_attribute() (called before get_updated_html()) returned attribute that was overwritten by set_attribute() and then removed by remove_attribute()'
		);
		$this->assertSame(
			'<div ><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML includes attribute that was overwritten by set_attribute() and then removed by remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_removed_class_names_before_they_are_applied() {
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->remove_class( 'with-border' );

		$this->assertSame(
			'main',
			$p->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) returned the wrong attribute after calling remove_attribute()'
		);
		$this->assertSame(
			'<div class="main" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML includes wrong attribute after calling remove_attribute()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_setting_and_then_removing_a_class_name_before_those_updates_are_applied() {
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->add_class( 'foo-class' );
		$p->remove_class( 'foo-class' );

		$this->assertSame(
			'main with-border',
			$p->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) returned class name that was added via add_class() and then removed by remove_class()'
		);
		$this->assertSame(
			self::HTML_WITH_CLASSES,
			$p->get_updated_html(),
			'Updated HTML includes class that was added via add_class() and then removed by remove_class()'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::get_attribute
	 */
	public function test_get_attribute_reflects_duplicating_and_then_removing_an_existing_class_name_before_those_updates_are_applied() {
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->add_class( 'with-border' );
		$p->remove_class( 'with-border' );

		$this->assertSame(
			'main',
			$p->get_attribute( 'class' ),
			'get_attribute() (called before get_updated_html()) returned class name that was duplicated via add_class() and then removed by remove_class()'
		);
		$this->assertSame(
			'<div class="main" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
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
		$p = new WP_HTML_Tag_Processor( '<div id="update-me" id="ignored-id"><span id="second">Text</span></div>' );
		$p->next_tag();
		$p->set_attribute( 'id', 'updated-id' );

		$this->assertSame(
			'<div id="updated-id" id="ignored-id"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Proper (first) appearance of attribute was not updated when duplicates exist'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_an_existing_attribute_name_updates_its_value_in_the_markup() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->set_attribute( 'id', 'new-id' );
		$this->assertSame(
			'<div id="new-id"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Existing attribute was not updated'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_next_tag_and_set_attribute_in_a_loop_update_all_tags_in_the_markup() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		while ( $p->next_tag() ) {
			$p->set_attribute( 'data-foo', 'bar' );
		}

		$this->assertSame(
			'<div data-foo="bar" id="first"><span data-foo="bar" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Not all tags were updated when looping with next_tag() and set_attribute()'
		);
	}

	/**
	 * Removing an attribute that's listed many times, e.g. `<div id="a" id="b" />` should remove
	 * all its instances and output just `<div />`.
	 *
	 * Today, however, WP_HTML_Tag_Processor only removes the first such attribute. It seems like a corner case
	 * and introducing additional complexity to correctly handle this scenario doesn't seem to be worth it.
	 * Let's revisit if and when this becomes a problem.
	 *
	 * This test is in place to confirm this behavior, which while incorrect, is well-defined.
	 * A later fix introduced to the Tag Processor should update this test to reflect the
	 * wanted and correct behavior.
	 *
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_remove_first_when_duplicated_attribute() {
		$p = new WP_HTML_Tag_Processor( '<div id="update-me" id="ignored-id"><span id="second">Text</span></div>' );
		$p->next_tag();
		$p->remove_attribute( 'id' );

		$this->assertSame(
			'<div  id="ignored-id"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'First attribute (when duplicates exist) was not removed'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_with_an_existing_attribute_name_removes_it_from_the_markup() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->remove_attribute( 'id' );

		$this->assertSame(
			'<div ><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Attribute was not removed'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_attribute
	 */
	public function test_remove_attribute_with_a_non_existing_attribute_name_does_not_change_the_markup() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->remove_attribute( 'no-such-attribute' );

		$this->assertSame(
			self::HTML_SIMPLE,
			$p->get_updated_html(),
			'Content was changed when attempting to remove an attribute that did not exist'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_creates_a_class_attribute_when_there_is_none() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->add_class( 'foo-class' );

		$this->assertSame(
			'<div class="foo-class" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not include class name added via add_class()'
		);
		$this->assertSame(
			'foo-class',
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return class name added via add_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_calling_add_class_twice_creates_a_class_attribute_with_both_class_names_when_there_is_no_class_attribute() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->add_class( 'foo-class' );
		$p->add_class( 'bar-class' );

		$this->assertSame(
			'<div class="foo-class bar-class" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not include class names added via subsequent add_class() calls'
		);
		$this->assertSame(
			'foo-class bar-class',
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return class names added via subsequent add_class() calls"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_remove_class_does_not_change_the_markup_when_there_is_no_class_attribute() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->remove_class( 'foo-class' );

		$this->assertSame(
			self::HTML_SIMPLE,
			$p->get_updated_html(),
			'Updated HTML includes class name that was removed by remove_class()'
		);
		$this->assertNull(
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return null for class name that was removed by remove_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_appends_class_names_to_the_existing_class_attribute_when_one_already_exists() {
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->add_class( 'foo-class' );
		$p->add_class( 'bar-class' );

		$this->assertSame(
			'<div class="main with-border foo-class bar-class" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not reflect class names added to existing class attribute via subsequent add_class() calls'
		);
		$this->assertSame(
			'main with-border foo-class bar-class',
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect class names added to existing class attribute via subsequent add_class() calls"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_remove_class_removes_a_single_class_from_the_class_attribute_when_one_exists() {
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->remove_class( 'main' );

		$this->assertSame(
			'<div class=" with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not reflect class name removed from existing class attribute via remove_class()'
		);
		$this->assertSame(
			' with-border',
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect class name removed from existing class attribute via remove_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_calling_remove_class_with_all_listed_class_names_removes_the_existing_class_attribute_from_the_markup() {
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->remove_class( 'main' );
		$p->remove_class( 'with-border' );

		$this->assertSame(
			'<div  id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not reflect class attribute removed via subesequent remove_class() calls'
		);
		$this->assertNull(
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) did not return null for class attribute removed via subesequent remove_class() calls"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_does_not_add_duplicate_class_names() {
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->add_class( 'with-border' );

		$this->assertSame(
			'<div class="main with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not reflect deduplicated class name added via add_class()'
		);
		$this->assertSame(
			'main with-border',
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect deduplicated class name added via add_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_preserves_class_name_order_when_a_duplicate_class_name_is_added() {
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->add_class( 'main' );

		$this->assertSame(
			'<div class="main with-border" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not reflect class name order after adding duplicated class name via add_class()'
		);
		$this->assertSame(
			'main with-border',
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect class name order after adding duplicated class name added via add_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_when_there_is_a_class_attribute_with_excessive_whitespaces() {
		$p = new WP_HTML_Tag_Processor(
			'<div class="   main   with-border   " id="first"><span class="not-main bold with-border" id="second">Text</span></div>'
		);
		$p->next_tag();
		$p->add_class( 'foo-class' );

		$this->assertSame(
			'<div class="   main   with-border foo-class" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not reflect existing excessive whitespace after adding class name via add_class()'
		);
		$this->assertSame(
			'   main   with-border foo-class',
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect existing excessive whitespace after adding class name via add_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_remove_class_preserves_whitespaces_when_there_is_a_class_attribute_with_excessive_whitespaces() {
		$p = new WP_HTML_Tag_Processor(
			'<div class="   main   with-border   " id="first"><span class="not-main bold with-border" id="second">Text</span></div>'
		);
		$p->next_tag();
		$p->remove_class( 'with-border' );

		$this->assertSame(
			'<div class="   main" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not reflect existing excessive whitespace after removing class name via remove_class()'
		);
		$this->assertSame(
			'   main',
			$p->get_attribute( 'class' ),
			"get_attribute( 'class' ) does not reflect existing excessive whitespace after removing class name via removing_class()"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::remove_class
	 */
	public function test_removing_all_classes_removes_the_existing_class_attribute_from_the_markup_even_when_excessive_whitespaces_are_present() {
		$p = new WP_HTML_Tag_Processor(
			'<div class="   main   with-border   " id="first"><span class="not-main bold with-border" id="second">Text</span></div>'
		);
		$p->next_tag();
		$p->remove_class( 'main' );
		$p->remove_class( 'with-border' );
		$this->assertSame(
			'<div  id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			'Updated HTML does not reflect removed class attribute after removing all class names via remove_class()'
		);
		$this->assertNull(
			$p->get_attribute( 'class' ),
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
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->add_class( 'add_class' );
		$p->set_attribute( 'class', 'set_attribute' );
		$this->assertSame(
			'<div class="set_attribute" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			"Calling get_updated_html after updating first tag's attributes did not return the expected HTML"
		);
		$this->assertSame(
			'set_attribute',
			$p->get_attribute( 'class' ),
			"Calling get_attribute after updating first tag's attributes did not return the expected class name"
		);

		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->set_attribute( 'class', 'set_attribute' );
		$p->add_class( 'add_class' );
		$this->assertSame(
			'<div class="set_attribute add_class" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			"Calling get_updated_html after updating first tag's attributes did not return the expected HTML"
		);
		$this->assertSame(
			'set_attribute add_class',
			$p->get_attribute( 'class' ),
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
		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->add_class( 'add_class' );
		$p->set_attribute( 'class', 'set_attribute' );
		$this->assertSame(
			'set_attribute',
			$p->get_attribute( 'class' ),
			"Calling get_attribute after updating first tag's attributes did not return the expected class name"
		);
		$this->assertSame(
			'<div class="set_attribute" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			"Calling get_updated_html after updating first tag's attributes did not return the expected HTML"
		);

		$p = new WP_HTML_Tag_Processor( self::HTML_WITH_CLASSES );
		$p->next_tag();
		$p->set_attribute( 'class', 'set_attribute' );
		$p->add_class( 'add_class' );
		$this->assertSame(
			'set_attribute add_class',
			$p->get_attribute( 'class' ),
			"Calling get_attribute after updating first tag's attributes did not return the expected class name"
		);
		$this->assertSame(
			'<div class="set_attribute add_class" id="first"><span class="not-main bold with-border" id="second">Text</span></div>',
			$p->get_updated_html(),
			"Calling get_updated_html after updating first tag's attributes did not return the expected HTML"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_overrides_boolean_class_attribute() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->set_attribute( 'class', true );
		$p->add_class( 'add_class' );
		$this->assertSame(
			'<div class="add_class" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
			"Updated HTML doesn't reflect class added via add_class that was originally set as boolean attribute"
		);
		$this->assertSame(
			'add_class',
			$p->get_attribute( 'class' ),
			"get_attribute (called after get_updated_html()) doesn't reflect class added via add_class that was originally set as boolean attribute"
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::add_class
	 */
	public function test_add_class_overrides_boolean_class_attribute_even_before_updating() {
		$p = new WP_HTML_Tag_Processor( self::HTML_SIMPLE );
		$p->next_tag();
		$p->set_attribute( 'class', true );
		$p->add_class( 'add_class' );
		$this->assertSame(
			'add_class',
			$p->get_attribute( 'class' ),
			"get_attribute (called before get_updated_html()) doesn't reflect class added via add_class that was originally set as boolean attribute"
		);
		$this->assertSame(
			'<div class="add_class" id="first"><span id="second">Text</span></div>',
			$p->get_updated_html(),
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
		<div checked class=" MixedCaseHTML position-relative button-group Another-Mixed-Case" />
		<div checked class=" MixedCaseHTML position-relative button-group Another-Mixed-Case">
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

		$p = new WP_HTML_Tag_Processor( $input );
		$this->assertTrue( $p->next_tag( 'div' ), 'Querying an existing tag did not return true' );
		$p->set_attribute( 'data-details', '{ "key": "value" }' );
		$p->add_class( 'is-processed' );
		$this->assertTrue(
			$p->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'BtnGroup',
				)
			),
			'Querying an existing tag did not return true'
		);
		$p->remove_class( 'BtnGroup' );
		$p->add_class( 'button-group' );
		$p->add_class( 'Another-Mixed-Case' );
		$this->assertTrue(
			$p->next_tag(
				array(
					'tag_name'   => 'div',
					'class_name' => 'BtnGroup',
				)
			),
			'Querying an existing tag did not return true'
		);
		$p->remove_class( 'BtnGroup' );
		$p->add_class( 'button-group' );
		$p->add_class( 'Another-Mixed-Case' );
		$this->assertTrue(
			$p->next_tag(
				array(
					'tag_name'     => 'button',
					'class_name'   => 'btn',
					'match_offset' => 3,
				)
			),
			'Querying an existing tag did not return true'
		);
		$p->remove_attribute( 'class' );
		$this->assertFalse( $p->next_tag( 'non-existent' ), 'Querying a non-existing tag did not return false' );
		$p->set_attribute( 'class', 'test' );
		$this->assertSame( $expected_output, $p->get_updated_html(), 'Calling get_updated_html after updating the attributes did not return the expected HTML' );
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_correctly_parses_html_attributes_wrapped_in_single_quotation_marks() {
		$p = new WP_HTML_Tag_Processor(
			'<div id=\'first\'><span id=\'second\'>Text</span></div>'
		);
		$p->next_tag(
			array(
				'tag_name' => 'div',
				'id'       => 'first',
			)
		);
		$p->remove_attribute( 'id' );
		$p->next_tag(
			array(
				'tag_name' => 'span',
				'id'       => 'second',
			)
		);
		$p->set_attribute( 'id', 'single-quote' );
		$this->assertSame(
			'<div ><span id="single-quote">Text</span></div>',
			$p->get_updated_html(),
			'Did not remove single-quoted attribute'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_set_attribute_with_value_equal_to_true_adds_a_boolean_html_attribute_with_implicit_value() {
		$p = new WP_HTML_Tag_Processor(
			'<form action="/action_page.php"><input type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>'
		);
		$p->next_tag( 'input' );
		$p->set_attribute( 'checked', true );
		$this->assertSame(
			'<form action="/action_page.php"><input checked type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>',
			$p->get_updated_html(),
			'Did not add "checked" as an expected boolean attribute'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_setting_a_boolean_attribute_to_false_removes_it_from_the_markup() {
		$p = new WP_HTML_Tag_Processor(
			'<form action="/action_page.php"><input checked type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>'
		);
		$p->next_tag( 'input' );
		$p->set_attribute( 'checked', false );
		$this->assertSame(
			'<form action="/action_page.php"><input  type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>',
			$p->get_updated_html(),
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
		$p          = new WP_HTML_Tag_Processor( $html_input );
		$p->next_tag( 'input' );
		$p->set_attribute( 'checked', false );
		$this->assertSame(
			$html_input,
			$p->get_updated_html(),
			'Changed the markup unexpectedly when setting a non-existing attribute to false'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_setting_a_boolean_attribute_to_a_string_value_adds_explicit_value_to_the_markup() {
		$p = new WP_HTML_Tag_Processor(
			'<form action="/action_page.php"><input checked type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>'
		);
		$p->next_tag( 'input' );
		$p->set_attribute( 'checked', 'checked' );
		$this->assertSame(
			'<form action="/action_page.php"><input checked="checked" type="checkbox" name="vehicle" value="Bike"><label for="vehicle">I have a bike</label></form>',
			$p->get_updated_html(),
			'Did not add string value to existing boolean attribute'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 */
	public function test_unclosed_script_tag_should_not_cause_an_infinite_loop() {
		$p = new WP_HTML_Tag_Processor( '<script>' );
		$p->next_tag();
		$this->assertSame( 'SCRIPT', $p->get_tag(), 'Did not find script tag' );
		$p->next_tag();
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
		$p = new WP_HTML_Tag_Processor( $script_then_div );
		$p->next_tag();
		$this->assertSame( 'SCRIPT', $p->get_tag(), 'The first found tag was not "script"' );
		$p->next_tag();
		$this->assertSame( 'DIV', $p->get_tag(), 'The second found tag was not "div"' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[].
	 */
	public function data_next_tag_ignores_script_tag_contents() {
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
		$p = new WP_HTML_Tag_Processor( $rcdata_then_div );
		$p->next_tag();
		$this->assertSame( $rcdata_tag, $p->get_tag(), "The first found tag was not '$rcdata_tag'" );
		$p->next_tag();
		$this->assertSame( 'DIV', $p->get_tag(), "The second found tag was not 'div'" );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_next_tag_ignores_contents_of_rcdata_tag() {
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
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::next_tag
	 *
	 * @dataProvider data_skips_contents_of_script_and_rcdata_regions
	 *
	 * @param $input_html HTML with multiple divs, one of which carries the "target" attribute.
	 */
	public function test_skips_contents_of_script_and_rcdata_regions( $input_html ) {
		$p = new WP_HTML_Tag_Processor( $input_html );
		$p->next_tag( 'div' );

		$this->assertTrue(
			$p->get_attribute( 'target' ),
			'Did not properly skip over script and rcdata regions; incorrectly found tags inside'
		);
	}

	/**
	 * Data provider
	 *
	 * @return array[]
	 */
	public function data_skips_contents_of_script_and_rcdata_regions() {
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
		$p = new WP_HTML_Tag_Processor(
			'<span>123<p>456</span>789</p>'
		);
		$p->next_tag( 'span' );
		$p->set_attribute( 'class', 'span-class' );
		$p->next_tag( 'p' );
		$p->set_attribute( 'class', 'p-class' );
		$this->assertSame(
			'<span class="span-class">123<p class="p-class">456</span>789</p>',
			$p->get_updated_html(),
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
		$p = new WP_HTML_Tag_Processor( self::HTML_MALFORMED );
		$p->next_tag( 'span' );
		$p->remove_attribute( 'Notifications<' );
		$this->assertSame(
			'<div><span class="d-md-none" /span><span class="d-none d-md-inline">Back to notifications</span></div>',
			$p->get_updated_html(),
			'Did not remove "Notifications<" attribute in malformed input'
		);
	}

	/**
	 * @ticket 56299
	 *
	 * @covers WP_HTML_Tag_Processor::set_attribute
	 */
	public function test_updating_specific_attributes_in_malformed_html() {
		$p = new WP_HTML_Tag_Processor( self::HTML_MALFORMED );
		$p->next_tag( 'span' );
		$p->set_attribute( 'id', 'first' );
		$p->next_tag( 'span' );
		$p->set_attribute( 'id', 'second' );
		$this->assertSame(
			'<div><span id="first" class="d-md-none" Notifications</span><span id="second" class="d-none d-md-inline">Back to notifications</span></div>',
			$p->get_updated_html(),
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
		$p = new WP_HTML_Tag_Processor( $html );
		$p->next_tag();
		$p->set_attribute( 'foo', 'bar' );
		$p->add_class( 'firstTag' );
		$p->next_tag();
		$p->add_class( 'secondTag' );

		$this->assertSame(
			$expected,
			$p->get_updated_html(),
			'Did not properly add attributes and class names'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_updating_attributes() {
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
				'expected' => '<![CDATA[This <is> a <strong id="yes">HTML Tag</strong>]]><span class="firstTag" foo="bar">test</span>',
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
		$p = new WP_HTML_Tag_Processor( $html );
		$p->next_tag();
		$p->set_attribute( 'foo', 'bar' );
		$p->add_class( 'firstTag' );
		$p->next_tag();
		$p->add_class( 'secondTag' );

		$this->assertSame(
			$expected,
			$p->get_updated_html(),
			'Did not properly update attributes and classnames given malformed input'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_updating_attributes_in_malformed_html() {
		$null_byte = chr( 0 );

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
				'input'    => '<hr id' . $null_byte . 'zero="test"><span>test</span>',
				'expected' => '<hr class="firstTag" foo="bar" id' . $null_byte . 'zero="test"><span class="secondTag">test</span>',
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
}
