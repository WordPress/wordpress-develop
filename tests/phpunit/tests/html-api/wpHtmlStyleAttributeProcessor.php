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
	 * @covers ::__construct
	 * @covers ::next_declaration
	 * @covers ::get_updated_style
	 *
	 * @dataProvider data_html_style_attribute_values
	 *
	 * @param string      $html           HTML containing a first div.
	 * @param string      $expected_style Expected normalized style input.
	 * @param string|null $property_name  Expected first property name.
	 */
	public function test_constructor_accepts_html_tag_processor_style_attribute_values( string $html, string $expected_style, ?string $property_name ) {
		$tags = new WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $tags->next_tag( 'div' ) );

		$processor = new WP_HTML_Style_Attribute_Processor( $tags->get_attribute( 'style' ) );

		$this->assertSame( $expected_style, $processor->get_updated_style() );

		if ( null === $property_name ) {
			$this->assertFalse( $processor->next_declaration() );
		} else {
			$this->assertTrue( $processor->next_declaration() );
			$this->assertSame( $property_name, $processor->get_property_name() );
		}
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::get_property_name
	 * @covers ::get_value
	 */
	public function test_next_declaration_reads_properties_in_order_and_preserves_duplicates() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red; color: color(display-p3 1 0 0); background: blue' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertSame( 'red', $processor->get_value() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertSame( 'color(display-p3 1 0 0)', $processor->get_value() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertSame( 'blue', $processor->get_value() );

		$this->assertFalse( $processor->next_declaration() );
	}

	/**
	 * @covers ::next_declaration
	 */
	public function test_next_declaration_query_visits_matching_duplicate_properties() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red; background: white; COLOR: blue;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertSame( 'red', $processor->get_value() );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertSame( 'blue', $processor->get_value() );

		$this->assertFalse( $processor->next_declaration( 'color' ) );

		$processor = new WP_HTML_Style_Attribute_Processor( '--Tone: warm; --tone: cool;' );

		$this->assertTrue( $processor->next_declaration( '--tone' ) );
		$this->assertSame( 'cool', $processor->get_value() );

		$this->assertFalse( $processor->next_declaration( '--tone' ) );
	}

	/**
	 * @covers ::is_important
	 * @covers ::get_value
	 */
	public function test_important_priority_is_inspected_separately_from_value() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red !important; --tone: blue !IMPORTANT; background: white;' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'red', $processor->get_value() );
		$this->assertTrue( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'blue', $processor->get_value() );
		$this->assertTrue( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'white', $processor->get_value() );
		$this->assertFalse( $processor->is_important() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::get_updated_style
	 */
	public function test_set_value_updates_current_declaration_without_collapsing_duplicates() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red !important; color: color(display-p3 1 0 0);' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_value( 'green', false ) );

		$this->assertSame( 'color: green; color: color(display-p3 1 0 0);', $processor->get_updated_style() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::get_value
	 * @covers ::is_important
	 */
	public function test_getters_reflect_current_declaration_after_set_value() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_value( 'green', true ) );

		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertSame( 'green', $processor->get_value() );
		$this->assertTrue( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::get_updated_style
	 */
	public function test_remove_declaration_removes_only_current_duplicate() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red; color: blue; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );

		$this->assertSame( 'color: red; background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::get_property_name
	 * @covers ::get_value
	 */
	public function test_getters_return_null_after_removing_current_declaration_until_cursor_advances() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );

		$this->assertNull( $processor->get_property_name() );
		$this->assertNull( $processor->get_value() );
		$this->assertFalse( $processor->is_important() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::get_updated_style
	 */
	public function test_remove_declaration_removes_adjacent_duplicate_declarations() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red; color: blue; background: white;' );

		while ( $processor->next_declaration( 'color' ) ) {
			$this->assertTrue( $processor->remove_declaration() );
		}

		$this->assertSame( 'background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_adds_duplicate_declaration() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red;' );

		$this->assertTrue( $processor->append_declaration( 'color', 'color(display-p3 1 0 0)', true ) );

		$this->assertSame( 'color: red; color: color(display-p3 1 0 0) !important;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_preserves_multiple_appends_in_order() {
		$processor = new WP_HTML_Style_Attribute_Processor( '' );

		$this->assertTrue( $processor->append_declaration( 'color', 'red' ) );
		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );

		$this->assertSame( 'color: red; background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_treats_comments_as_trivia_for_separators() {
		$processor = new WP_HTML_Style_Attribute_Processor( '/*keep*/' );

		$this->assertTrue( $processor->append_declaration( 'color', 'red' ) );
		$this->assertSame( '/*keep*/ color: red;', $processor->get_updated_style() );

		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red;/*keep*/' );

		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( 'color: red;/*keep*/ background: white;', $processor->get_updated_style() );

		$processor = new WP_HTML_Style_Attribute_Processor( 'color: var(--x;/*keep*/);' );

		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( 'color: var(--x;/*keep*/); background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_rejects_unclosed_component_value_append_points() {
		$style     = 'color: var(--x;/*keep*/';
		$processor = new WP_HTML_Style_Attribute_Processor( $style );

		$this->assertFalse( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( $style, $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::next_declaration
	 */
	public function test_appended_declarations_can_be_inspected_by_the_cursor() {
		$processor = new WP_HTML_Style_Attribute_Processor( '' );

		$this->assertTrue( $processor->append_declaration( 'color', 'red' ) );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertSame( 'red', $processor->get_value() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::next_declaration
	 * @covers ::get_property_name
	 */
	public function test_append_declaration_preserves_exhausted_cursor_until_it_advances() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red;' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertFalse( $processor->next_declaration() );
		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );

		$this->assertNull( $processor->get_property_name() );
		$this->assertNull( $processor->get_value() );
		$this->assertFalse( $processor->set_value( 'green' ) );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertSame( 'white', $processor->get_value() );
		$this->assertSame( 'color: red; background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::remove_declaration
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_append_declaration_after_removing_only_declaration() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red;   ' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );
		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );

		$this->assertSame( 'background: white;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::get_updated_style
	 */
	public function test_invalid_fragments_are_skipped_and_preserved() {
		$style     = 'color red; @media (min-width: 1px) { color: green; } background: white;';
		$processor = new WP_HTML_Style_Attribute_Processor( $style );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertSame( 'white', $processor->get_value() );
		$this->assertFalse( $processor->next_declaration() );
		$this->assertSame( $style, $processor->get_updated_style() );
	}

	/**
	 * @covers ::next_declaration
	 */
	public function test_stray_right_brace_consumes_bad_declaration_until_semicolon() {
		$processor = new WP_HTML_Style_Attribute_Processor( '} color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background', $processor->get_property_name() );
		$this->assertSame( 'white', $processor->get_value() );
		$this->assertFalse( $processor->next_declaration() );
	}

	/**
	 * @covers ::next_declaration
	 * @covers ::set_value
	 * @covers ::get_updated_style
	 */
	public function test_escaped_property_names_match_decoded_names_and_preserve_raw_spelling() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'c\\6f lor: red; --t\\6f ne: cool; --Tone: warm;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertTrue( $processor->set_value( 'green' ) );
		$this->assertSame( 'c\\6f lor: green; --t\\6f ne: cool; --Tone: warm;', $processor->get_updated_style() );

		$this->assertTrue( $processor->next_declaration( '--tone' ) );
		$this->assertSame( '--tone', $processor->get_property_name() );
		$this->assertSame( 'cool', $processor->get_value() );

		$this->assertFalse( $processor->next_declaration( '--tone' ) );
	}

	/**
	 * @covers ::is_important
	 * @covers ::get_value
	 */
	public function test_important_inside_unclosed_function_is_not_declaration_priority() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: var(--x, red !important' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'var(--x, red !important', $processor->get_value() );
		$this->assertFalse( $processor->is_important() );
	}

	/**
	 * @covers ::is_important
	 * @covers ::get_value
	 *
	 * @dataProvider data_important_priority_syntax
	 *
	 * @param string $style     Style attribute value.
	 * @param string $value     Expected declaration value.
	 * @param bool   $important Expected importance.
	 */
	public function test_important_priority_syntax_variants( string $style, string $value, bool $important ) {
		$processor = new WP_HTML_Style_Attribute_Processor( $style );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( $value, $processor->get_value() );
		$this->assertSame( $important, $processor->is_important() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_declaration_values_reject_top_level_semicolons() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red;' );

		$this->assertFalse( $processor->append_declaration( 'background', 'blue; color: green' ) );
		$this->assertSame( 'color: red;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::set_value
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_declaration_values_reject_top_level_important_priority() {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertFalse( $processor->set_value( 'green ! important', false ) );
		$this->assertFalse( $processor->append_declaration( 'background', 'white !important' ) );

		$this->assertSame( 'color: red;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 *
	 * @dataProvider data_malformed_declaration_values
	 *
	 * @param string $value Malformed CSS declaration value.
	 */
	public function test_declaration_values_reject_malformed_eof_tokens( string $value ) {
		$processor = new WP_HTML_Style_Attribute_Processor( 'color: red;' );

		$this->assertFalse( $processor->append_declaration( 'background', $value ) );
		$this->assertSame( 'color: red;', $processor->get_updated_style() );
	}

	/**
	 * @covers ::get_updated_style
	 */
	public function test_updated_style_can_be_set_on_html_tag_processor() {
		$tags = new WP_HTML_Tag_Processor( '<div style="color: red; color: color(display-p3 1 0 0)">Text</div>' );
		$this->assertTrue( $tags->next_tag( 'div' ) );

		$styles = new WP_HTML_Style_Attribute_Processor( $tags->get_attribute( 'style' ) );
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
	 * @return array<string, array{string,string,string|null}>
	 */
	public static function data_html_style_attribute_values(): array {
		return array(
			'missing style attribute' => array( '<div>Text</div>', '', null ),
			'boolean style attribute' => array( '<div style>Text</div>', '', null ),
			'empty style attribute'   => array( '<div style="">Text</div>', '', null ),
			'valued style attribute'  => array( '<div style="color: red">Text</div>', 'color: red', 'color' ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string,string,bool}>
	 */
	public static function data_important_priority_syntax(): array {
		return array(
			'no whitespace'         => array( 'color: red!important;', 'red', true ),
			'whitespace after bang' => array( 'color: red ! important;', 'red', true ),
			'comment after bang'    => array( 'color: red ! /*x*/ important;', 'red', true ),
			'escaped important'     => array( 'color: red !\\69mportant;', 'red', true ),
			'extra trailing token'  => array( 'color: red ! important foo;', 'red ! important foo', false ),
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

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_property_names_reject_eof_escapes() {
		$processor = new WP_HTML_Style_Attribute_Processor( '' );

		$this->assertFalse( $processor->append_declaration( 'color\\', 'red' ) );
		$this->assertSame( '', $processor->get_updated_style() );
	}
}
