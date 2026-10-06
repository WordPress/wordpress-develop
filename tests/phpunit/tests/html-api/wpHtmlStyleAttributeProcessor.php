<?php
/**
 * Unit tests covering WP_HTML_Style_Attribute_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since {WP_VERSION}
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Style_Attribute_Processor
 */
class Tests_HtmlApi_WpHtmlStyleAttributeProcessor extends WP_UnitTestCase {
	/**
	 * @covers ::create
	 * @covers ::get_updated_style
	 */
	public function test_create_accepts_decoded_css_text_strings() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red' );

		$this->assertSame( 'color: red', $processor->get_updated_style() );
	}

	/**
	 * @covers ::create
	 *
	 * @dataProvider data_non_string_style_attribute_values
	 *
	 * @param mixed $style_attribute_value Non-string style attribute value.
	 */
	public function test_create_rejects_non_string_style_attribute_values( $style_attribute_value ) {
		$this->expectException( TypeError::class );

		WP_HTML_Style_Attribute_Processor::create( $style_attribute_value );
	}

	/**
	 * @coversNothing
	 */
	public function test_constructor_is_not_public() {
		$reflection  = new ReflectionClass( WP_HTML_Style_Attribute_Processor::class );
		$constructor = $reflection->getConstructor();

		$this->assertNotNull( $constructor );
		$this->assertFalse( $constructor->isPublic() );
	}

	/**
	 * @coversNothing
	 */
	public function test_raw_value_getter_is_not_public_api() {
		$this->assertFalse( method_exists( WP_HTML_Style_Attribute_Processor::class, 'get_value' ) );
		$this->assertFalse( method_exists( WP_HTML_Style_Attribute_Processor::class, 'get_raw_value' ) );
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::get_property_name
	 * @covers ::is_important
	 */
	public function test_next_declaration_reads_properties_in_order_and_preserves_duplicates() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'COLOR: red; color: blue !important; background: white' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertFalse( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertTrue( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );

		$this->assertFalse( $processor->next_declaration() );
		$this->assertNull( $processor->get_property_name() );
		$this->assertNull( $processor->is_important() );
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::get_property_name
	 */
	public function test_next_declaration_query_visits_matching_duplicate_properties() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white; COLOR: blue;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertSame( 'color', $processor->get_property_name() );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertSame( 'color', $processor->get_property_name() );

		$this->assertFalse( $processor->next_declaration( 'color' ) );
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::get_property_name
	 */
	public function test_custom_property_queries_match_exact_decoded_casing() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '--Tone: warm; --tone: cool;' );

		$this->assertTrue( $processor->next_declaration( '--tone' ) );
		$this->assertSame( '--tone', $processor->get_property_name() );

		$this->assertFalse( $processor->next_declaration( '--tone' ) );
	}

	/**
	 * @covers ::next_declaration
	 */
	public function test_next_declaration_accepts_named_property_name_argument() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'background' ) );
		$this->assertSame( 'background', $processor->get_property_name() );
	}

	/**
	 * @covers ::is_important
	 *
	 * @dataProvider data_important_priority_syntax
	 *
	 * @param string $style     Style attribute value.
	 * @param bool   $important Expected importance.
	 */
	public function test_important_priority_syntax_variants( string $style, bool $important ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( $important, $processor->is_important() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::get_updated_style
	 */
	public function test_set_value_updates_current_declaration_without_collapsing_duplicates() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red !important; color: color(display-p3 1 0 0);' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_value( 'green', false ) );

		$this->assertSame( 'color: green; color: color(display-p3 1 0 0);', $processor->get_updated_style() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_declaration_values_allow_leading_and_trailing_comments_as_trivia() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_value( '/* before */ green /* after */' ) );
		$this->assertTrue( $processor->append_declaration( 'background', '/* before */ white /* after */' ) );

		$this->assertSame( 'color: /* before */ green /* after */; background: /* before */ white /* after */;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::is_important
	 */
	public function test_set_value_preserves_sets_and_clears_importance() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red !important; background: white; border-color: black;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_value( 'green' ) );
		$this->assertTrue( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration( 'background' ) );
		$this->assertTrue( $processor->set_value( 'blue', true ) );
		$this->assertTrue( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration( 'border-color' ) );
		$this->assertTrue( $processor->set_value( 'currentColor', false ) );
		$this->assertFalse( $processor->is_important() );

		$this->assertSame(
			'color: green !important; background: blue !important; border-color: currentColor;',
			$processor->get_updated_style()
		);
	}

	/**
	 * @covers ::set_value
	 * @covers ::remove_declaration
	 * @covers ::get_property_name
	 * @covers ::is_important
	 */
	public function test_set_value_with_empty_string_removes_current_declaration() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_value( '' ) );

		$this->assertNull( $processor->get_property_name() );
		$this->assertNull( $processor->is_important() );
		$this->assertFalse( $processor->set_value( 'green' ) );
		$this->assertFalse( $processor->remove_declaration() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertSame( 'background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::get_updated_style
	 */
	public function test_set_value_rejects_css_whitespace_only_values_without_removing_declaration() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertFalse( $processor->set_value( " \t\n\r\f" ) );
		$this->assertSame( 'color: red;', $processor->get_updated_style() );
		$this->assertSame( 'color', $processor->get_property_name() );
	}

	/**
	 * @covers ::set_important
	 * @covers ::is_important
	 * @covers ::get_updated_style
	 */
	public function test_set_important_sets_and_clears_priority() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white ! /*x*/ important;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertTrue( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration( 'background' ) );
		$this->assertTrue( $processor->set_important( false ) );
		$this->assertFalse( $processor->is_important() );

		$this->assertSame( 'color: red !important; background: white ;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::set_important
	 * @covers ::get_updated_style
	 */
	public function test_set_important_can_repair_unclosed_eof_component_values() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: var(--x' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertTrue( $processor->is_important() );
		$this->assertSame( 'color: var(--x) !important', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: var(--x /*c*/' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertTrue( $processor->is_important() );
		$this->assertSame( 'color: var(--x /*c*/) !important', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'background: url(foo' );

		$this->assertTrue( $processor->next_declaration( 'background' ) );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertTrue( $processor->is_important() );
		$this->assertSame( 'background: url(foo) !important', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: var(--x ' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertTrue( $processor->is_important() );
		$this->assertSame( 'color: var(--x ) !important', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: var(--x /*c*/ ' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertTrue( $processor->is_important() );
		$this->assertSame( 'color: var(--x /*c*/ ) !important', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'background: url(foo ' );

		$this->assertTrue( $processor->next_declaration( 'background' ) );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertTrue( $processor->is_important() );
		$this->assertSame( 'background: url(foo ) !important', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'background: url(foo\\)' );

		$this->assertTrue( $processor->next_declaration( 'background' ) );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertTrue( $processor->is_important() );
		$this->assertSame( 'background: url(foo\\)) !important', $processor->get_updated_style() );
	}

	/**
	 * @covers ::set_important
	 */
	public function test_set_important_returns_false_without_current_declaration() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertFalse( $processor->set_important( true ) );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::get_updated_style
	 */
	public function test_remove_declaration_removes_only_current_duplicate() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; color: blue; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );

		$this->assertSame( 'color: red; background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::get_updated_style
	 */
	public function test_remove_declaration_removes_adjacent_duplicate_declarations() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; color: blue; background: white;' );

		while ( $processor->next_declaration( 'color' ) ) {
			$this->assertTrue( $processor->remove_declaration() );
		}

		$this->assertSame( 'background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::get_property_name
	 * @covers ::is_important
	 */
	public function test_getters_return_null_after_removing_current_declaration_until_cursor_advances() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );

		$this->assertNull( $processor->get_property_name() );
		$this->assertNull( $processor->is_important() );
		$this->assertFalse( $processor->remove_declaration() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::get_updated_style
	 */
	public function test_remove_declaration_preserves_surrounding_comments() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '/*keep*/ color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );
		$this->assertSame( '/*keep*/ background: white;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; /*keep*/ background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );
		$this->assertSame( '/*keep*/ background: white;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; /*keep*/ background: white;' );

		$this->assertTrue( $processor->next_declaration( 'background' ) );
		$this->assertTrue( $processor->remove_declaration() );
		$this->assertSame( 'color: red; /*keep*/', $processor->get_updated_style() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::get_updated_style
	 */
	public function test_remove_declaration_with_invalid_fragments_preserves_remaining_structure() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; invalid; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );
		$this->assertSame( 'invalid; background: white;', $processor->get_updated_style() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_adds_duplicate_declaration() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertTrue( $processor->append_declaration( 'color', 'color(display-p3 1 0 0)', true ) );

		$this->assertSame( 'color: red; color: color(display-p3 1 0 0) !important;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_preserves_multiple_appends_in_order() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '' );

		$this->assertTrue( $processor->append_declaration( 'color', 'red' ) );
		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );

		$this->assertSame( 'color: red; background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_property_name
	 * @covers ::get_updated_style
	 * @covers ::is_important
	 * @covers ::next_declaration
	 * @covers ::remove_declaration
	 * @covers ::set_important
	 * @covers ::set_value
	 */
	public function test_mixed_mutations_preserve_the_logical_cursor_and_declaration_order() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_value( 'green', true ) );
		$this->assertTrue( $processor->set_important( false ) );
		$this->assertTrue( $processor->append_declaration( 'border', '1px', true ) );

		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertFalse( $processor->is_important() );
		$this->assertTrue( $processor->set_value( 'blue' ) );
		$this->assertSame( 'color: blue; background: white; border: 1px !important;', $processor->get_updated_style() );

		$this->assertTrue( $processor->remove_declaration() );
		$this->assertNull( $processor->get_property_name() );
		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'border', $processor->get_property_name() );
		$this->assertTrue( $processor->is_important() );
		$this->assertSame( 'background: white; border: 1px !important;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_treats_comments_as_trivia_for_separators() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '/*keep*/' );

		$this->assertTrue( $processor->append_declaration( 'color', 'red' ) );
		$this->assertSame( '/*keep*/ color: red;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;/*keep*/' );

		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( 'color: red;/*keep*/ background: white;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: var(--x;/*keep*/);' );

		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( 'color: var(--x;/*keep*/); background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_lowercases_ordinary_properties_and_preserves_custom_properties() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '' );

		$this->assertTrue( $processor->append_declaration( 'BackgroundColor', 'red' ) );
		$this->assertTrue( $processor->append_declaration( '--Tone', 'warm' ) );

		$this->assertSame( 'backgroundcolor: red; --Tone: warm;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::next_declaration
	 * @covers ::get_property_name
	 */
	public function test_appended_declarations_can_be_inspected_by_the_cursor() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '' );

		$this->assertTrue( $processor->append_declaration( 'color', 'red' ) );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'color', $processor->get_property_name() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::next_declaration
	 * @covers ::get_property_name
	 * @covers ::set_value
	 */
	public function test_append_declaration_preserves_exhausted_cursor_until_it_advances() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertFalse( $processor->next_declaration() );
		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );

		$this->assertNull( $processor->get_property_name() );
		$this->assertNull( $processor->is_important() );
		$this->assertFalse( $processor->set_value( 'green' ) );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertSame( 'color: red; background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_after_removing_only_declaration() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;   ' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );
		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );

		$this->assertSame( 'background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_repairs_unclosed_eof_component_values() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: var(--x' );

		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( 'color: var(--x); background: white;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'width: calc(1px + var(--gap' );

		$this->assertTrue( $processor->append_declaration( 'color', 'red' ) );
		$this->assertSame( 'width: calc(1px + var(--gap)); color: red;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'background: url(foo' );

		$this->assertTrue( $processor->append_declaration( 'color', 'red' ) );
		$this->assertSame( 'background: url(foo); color: red;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'background: url(foo ' );

		$this->assertTrue( $processor->append_declaration( 'border', '0' ) );
		$this->assertSame( 'background: url(foo ); border: 0;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'background: url(foo\\)' );

		$this->assertTrue( $processor->append_declaration( 'border', '0' ) );
		$this->assertSame( 'background: url(foo\\)); border: 0;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_rejects_eof_repairs_that_cannot_be_precise() {
		$style     = 'color: var(--x;/*keep';
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertFalse( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( $style, $processor->get_updated_style() );
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::get_updated_style
	 */
	public function test_invalid_fragments_are_skipped_and_preserved() {
		$style     = 'color red; @media (min-width: 1px) { color: green; } background: white;';
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertFalse( $processor->next_declaration() );
		$this->assertSame( $style, $processor->get_updated_style() );
	}

	/**
	 * @covers ::next_declaration
	 */
	public function test_stray_right_brace_consumes_bad_declaration_until_semicolon() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '} color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertFalse( $processor->next_declaration() );
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::get_updated_style
	 *
	 * @dataProvider data_declarations_with_values_css_ignores
	 *
	 * @param string $style Style attribute value whose first declaration CSS ignores.
	 */
	public function test_declarations_with_values_css_ignores_are_skipped_and_preserved( string $style ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertFalse( $processor->next_declaration() );
		$this->assertSame( $style, $processor->get_updated_style() );
	}

	/**
	 * @covers ::next_declaration
	 */
	public function test_custom_property_values_may_mix_blocks_with_other_tokens() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '--x: { a } b; color: red;' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( '--x', $processor->get_property_name() );
		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'color', $processor->get_property_name() );
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::set_value
	 * @covers ::get_updated_style
	 */
	public function test_escaped_property_names_match_decoded_names_and_preserve_raw_spelling() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'c\\6f lor: red; --t\\6f ne: cool; --Tone: warm;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertTrue( $processor->set_value( 'green' ) );
		$this->assertSame( 'c\\6f lor: green; --t\\6f ne: cool; --Tone: warm;', $processor->get_updated_style() );

		$this->assertTrue( $processor->next_declaration( '--tone' ) );
		$this->assertSame( '--tone', $processor->get_property_name() );
		$this->assertTrue( $processor->set_value( 'cold' ) );
		$this->assertSame( 'c\\6f lor: green; --t\\6f ne: cold; --Tone: warm;', $processor->get_updated_style() );

		$this->assertFalse( $processor->next_declaration( '--tone' ) );
	}

	/**
	 * @covers ::is_important
	 */
	public function test_important_inside_unclosed_function_is_not_declaration_priority() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: var(--x, red !important' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertFalse( $processor->is_important() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_declaration_values_reject_top_level_semicolons_and_important_priority() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertFalse( $processor->set_value( 'blue; color: green' ) );
		$this->assertFalse( $processor->set_value( 'green ! important', false ) );
		$this->assertFalse( $processor->set_value( 'green ! important foo', false ) );
		$this->assertFalse( $processor->append_declaration( 'background', 'blue; color: green' ) );
		$this->assertFalse( $processor->append_declaration( 'background', 'white !important' ) );
		$this->assertFalse( $processor->append_declaration( 'background', 'white !important foo' ) );

		$this->assertSame( 'color: red;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_rejects_empty_values() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertFalse( $processor->append_declaration( 'background', '' ) );
		$this->assertFalse( $processor->append_declaration( 'background', '/* comment */' ) );
		$this->assertSame( 'color: red;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::get_updated_style
	 */
	public function test_set_value_rejects_comment_only_values_without_removing_declaration() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertFalse( $processor->set_value( '/* comment */' ) );
		$this->assertSame( 'color: red;', $processor->get_updated_style() );
		$this->assertSame( 'color', $processor->get_property_name() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::set_value
	 * @covers ::get_updated_style
	 *
	 * @dataProvider data_malformed_declaration_values
	 *
	 * @param string $value Malformed CSS declaration value.
	 */
	public function test_declaration_values_reject_malformed_eof_tokens( string $value ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertFalse( $processor->set_value( $value ) );
		$this->assertFalse( $processor->append_declaration( 'background', $value ) );
		$this->assertSame( 'color: red;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_property_names_reject_escaped_source_identifiers() {
		$processor = WP_HTML_Style_Attribute_Processor::create( '' );

		$this->assertFalse( $processor->append_declaration( 'color\\', 'red' ) );
		$this->assertFalse( $processor->append_declaration( 'c\\6f lor', 'red' ) );
		$this->assertSame( '', $processor->get_updated_style() );
	}

	/**
	 * @coversNothing
	 */
	public function test_whitespace_constant_is_not_public_api() {
		$reflection = new ReflectionClass( WP_HTML_Style_Attribute_Processor::class );

		$this->assertFalse( $reflection->hasConstant( 'WHITESPACE' ) && $reflection->getReflectionConstant( 'WHITESPACE' )->isPublic() );
	}

	/**
	 * @covers ::get_updated_style
	 */
	public function test_updated_style_can_be_set_on_html_tag_processor_after_string_check() {
		$tags = new WP_HTML_Tag_Processor( '<div style="color: red; color: color(display-p3 1 0 0)">Text</div>' );
		$this->assertTrue( $tags->next_tag( 'div' ) );

		$style = $tags->get_attribute( 'style' );
		$this->assertIsString( $style );

		$styles = WP_HTML_Style_Attribute_Processor::create( $style );
		$this->assertTrue( $styles->append_declaration( 'background', 'white' ) );
		$this->assertTrue( $tags->set_attribute( 'style', $styles->get_updated_style() ) );

		$this->assertSame(
			'<div style="color: red; color: color(display-p3 1 0 0); background: white;">Text</div>',
			$tags->get_updated_html()
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function data_non_string_style_attribute_values(): array {
		return array(
			'missing style attribute' => array( null ),
			'boolean style attribute' => array( true ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string,bool}>
	 */
	public static function data_important_priority_syntax(): array {
		return array(
			'no whitespace'         => array( 'color: red!important;', true ),
			'whitespace after bang' => array( 'color: red ! important;', true ),
			'comment after bang'    => array( 'color: red ! /*x*/ important;', true ),
			'escaped important'     => array( 'color: red !\\69mportant;', true ),
			'nested important'      => array( 'color: var(--x, red !important);', false ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_declarations_with_values_css_ignores(): array {
		return array(
			'bad string'                      => array( "font-family: \"a\n; color: red;" ),
			'bad url'                         => array( 'background: url(a b); color: red;' ),
			'nested bad url'                  => array( 'background: image-set(url(a b) 1x); color: red;' ),
			'unmatched right paren'           => array( 'width: 1px ); color: red;' ),
			'unmatched right bracket'         => array( 'width: 1px ]; color: red;' ),
			'unmatched right brace'           => array( 'width: 1px }; color: red;' ),
			'mismatched closer in function'   => array( 'width: calc(1px } + 2px); color: red;' ),
			'custom property unmatched brace' => array( '--x: a } b; color: red;' ),
			'bang before important'           => array( 'padding: 0 ! 1px !important; color: red;' ),
			'important before other tokens'   => array( 'padding: 0 !important 1px; color: red;' ),
			'custom property bang'            => array( '--x: a ! b; color: red;' ),
			'block mixed with other tokens'   => array( 'width: 1px { x }; color: red;' ),
			'block before other tokens'       => array( 'width: { x } 1px; color: red;' ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_malformed_declaration_values(): array {
		return array(
			'eof string'                      => array( '"unterminated' ),
			'eof string escaped end'          => array( '"unterminated\\' ),
			'eof string apparent end escaped' => array( '"unterminated\\"' ),
			'eof comment'                     => array( '/*' ),
			'eof url'                         => array( 'url(foo' ),
			'eof url escaped end'             => array( 'url(foo\\' ),
			'eof url apparent end escaped'    => array( 'url(foo\\)' ),
			'eof escape'                      => array( 'red\\' ),
		);
	}
}
