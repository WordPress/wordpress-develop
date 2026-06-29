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
