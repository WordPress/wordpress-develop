<?php
/**
 * Unit tests covering WP_HTML_Style_Attribute_Processor functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 7.3.0
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
		$this->assertFalse( method_exists( WP_HTML_Style_Attribute_Processor::class, 'get_value_source' ) );
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
	 * @ticket 65738
	 *
	 * @covers ::get_value_tokens
	 */
	public function test_get_value_tokens_returns_null_when_not_on_a_declaration() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white' );

		$this->assertNull( $processor->get_value_tokens(), 'Expected null before the first declaration.' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertNotNull( $processor->get_value_tokens() );
		$this->assertTrue( $processor->next_declaration() );
		$this->assertFalse( $processor->next_declaration() );

		$this->assertNull( $processor->get_value_tokens(), 'Expected null after the last declaration.' );
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::get_value_tokens
	 *
	 * @dataProvider data_value_tokens
	 *
	 * @param string $style           Style text.
	 * @param array  $expected_tokens Expected value tokens.
	 */
	public function test_get_value_tokens_covers_only_the_declaration_value( string $style, array $expected_tokens ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertTrue( $processor->next_declaration() );
		$tokens = $processor->get_value_tokens();

		$this->assertContainsOnlyInstancesOf( WP_CSS_Token::class, $tokens );
		$this->assertSame( $expected_tokens, array_map( array( self::class, 'token_fields' ), $tokens ) );
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::get_value_tokens
	 */
	public function test_get_value_tokens_decodes_values_and_keeps_source_offsets() {
		$style     = 'content: "a\\"b"';
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertTrue( $processor->next_declaration() );
		$tokens = $processor->get_value_tokens();

		$this->assertCount( 1, $tokens );
		$this->assertSame( WP_CSS_Token_Processor::TOKEN_STRING, $tokens[0]->get_type() );
		$this->assertSame( 'a"b', $tokens[0]->get_value() );
		$this->assertSame( '"a\\"b"', substr( $style, $tokens[0]->get_start(), $tokens[0]->get_length() ) );
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::get_value_tokens
	 * @covers ::set_value
	 * @covers ::remove_declaration
	 */
	public function test_get_value_tokens_follow_the_updated_style_after_mutations() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->set_value( 'green' ) );
		$this->assertSame(
			array( self::token( WP_CSS_Token_Processor::TOKEN_IDENT, 'green', 7, 5 ) ),
			array_map( array( self::class, 'token_fields' ), $processor->get_value_tokens() )
		);

		$this->assertTrue( $processor->remove_declaration() );
		$this->assertNull( $processor->get_value_tokens() );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( 'background: white', $processor->get_updated_style() );
		$this->assertSame(
			array( self::token( WP_CSS_Token_Processor::TOKEN_IDENT, 'white', 12, 5 ) ),
			array_map( array( self::class, 'token_fields' ), $processor->get_value_tokens() )
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::get_value_tokens
	 *
	 * @dataProvider data_value_tokens_source_slices
	 *
	 * @param string $style Style text.
	 */
	public function test_get_value_tokens_match_the_retokenized_source_slice( string $style ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertTrue( $processor->next_declaration() );
		$tokens = $processor->get_value_tokens();
		$this->assertNotEmpty( $tokens );

		$first = $tokens[0];
		$last  = $tokens[ count( $tokens ) - 1 ];
		$slice = substr( $style, $first->get_start(), $last->get_start() + $last->get_length() - $first->get_start() );

		$expected  = array();
		$tokenizer = WP_CSS_Token_Processor::create( $slice );
		while ( $tokenizer->next_token() ) {
			$token      = $tokenizer->get_token();
			$expected[] = array( $token->get_type(), $token->get_flag(), $token->get_value(), $token->get_unit(), $token->is_terminated() );
		}

		$actual = array_map(
			static function ( WP_CSS_Token $token ): array {
				return array( $token->get_type(), $token->get_flag(), $token->get_value(), $token->get_unit(), $token->is_terminated() );
			},
			$tokens
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::serialize_declaration
	 * @covers ::remove_declaration
	 */
	public function test_serialize_declaration_returns_null_when_not_on_a_declaration() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white' );

		$this->assertNull( $processor->serialize_declaration(), 'Expected null before the first declaration.' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertTrue( $processor->remove_declaration() );
		$this->assertNull( $processor->serialize_declaration(), 'Expected null after removing the declaration.' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertFalse( $processor->next_declaration() );
		$this->assertNull( $processor->serialize_declaration(), 'Expected null after the last declaration.' );
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::serialize_declaration
	 *
	 * @dataProvider data_serialized_declarations
	 *
	 * @param string $style    Style text.
	 * @param string $expected Expected text of the first declaration.
	 */
	public function test_serialize_declaration_writes_the_current_declaration( string $style, string $expected ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertSame( $expected, $processor->serialize_declaration() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string,string}>
	 */
	public static function data_serialized_declarations(): array {
		return array(
			'single ident'                     => array( 'color: red', 'color: red;' ),
			'important'                        => array( 'color : red ! important ;', 'color: red !important;' ),
			'important after a comment'        => array( 'color: red /* x */ !important', 'color: red !important;' ),
			'lowercased name'                  => array( 'COLOR: Red', 'color: Red;' ),
			'escaped name'                     => array( 'c\\6f lor: red', 'color: red;' ),
			'custom property keeps its case'   => array( '--Foo: a b', '--Foo: a b;' ),
			'custom property name escape'      => array( '--a\\ b: 1', '--a\\20 b: 1;' ),
			'comments and whitespace runs'     => array( "margin: 1px /* a */ \t 2px", 'margin: 1px 2px;' ),
			'comment between tokens'           => array( 'margin: 1px/**/2px', 'margin: 1px/**/2px;' ),
			'empty value'                      => array( 'color:;', 'color: ;' ),
			'string'                           => array( "font-family: 'a\"b'", 'font-family: "a\\22 b";' ),
			'value ending in an escaped space' => array( '--x: a\\ ', '--x: a\\20 ;' ),
			'backslash before a newline'       => array( "--x: a\\\n; width: 1px", "--x: a\\\n;" ),
			'block open at the end'            => array( 'width: calc(1px + (2px', 'width: calc(1px + (2px));' ),
			'url open at the end'              => array( 'background: url(a.png', 'background: url(a.png);' ),
			'semicolon inside an open block'   => array( 'width: calc(1px; color: red', 'width: calc(1px; color: red);' ),
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::serialize_declaration
	 *
	 * @dataProvider data_value_tokens_source_slices
	 *
	 * @param string $style Style text.
	 */
	public function test_serialize_declaration_reads_back_as_the_same_declaration( string $style ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );
		$this->assertTrue( $processor->next_declaration() );

		$reread = WP_HTML_Style_Attribute_Processor::create( $processor->serialize_declaration() );
		$this->assertTrue( $reread->next_declaration() );

		$this->assertSame( $processor->get_property_name(), $reread->get_property_name() );
		$this->assertSame( $processor->is_important(), $reread->is_important() );
		$this->assertSame(
			self::significant_tokens( $processor->get_value_tokens() ),
			self::significant_tokens( $reread->get_value_tokens() )
		);
		$this->assertFalse( $reread->next_declaration(), 'Expected exactly one declaration.' );
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::serialize_declaration
	 * @covers ::set_value
	 */
	public function test_serialize_declaration_follows_mutations() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertTrue( $processor->set_value( 'green /* c */', true ) );
		$this->assertSame( 'color: green !important;', $processor->serialize_declaration() );
	}

	/**
	 * Reads the fields that give a value its meaning, closing blocks left open.
	 *
	 * @param WP_CSS_Token[] $tokens Value tokens.
	 * @return array[] Type, flag, value and unit of each token except whitespace and comments.
	 */
	private static function significant_tokens( array $tokens ): array {
		$closers = array(
			WP_CSS_Token_Processor::TOKEN_FUNCTION     => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
			WP_CSS_Token_Processor::TOKEN_LEFT_PAREN   => WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN,
			WP_CSS_Token_Processor::TOKEN_LEFT_BRACKET => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACKET,
			WP_CSS_Token_Processor::TOKEN_LEFT_BRACE   => WP_CSS_Token_Processor::TOKEN_RIGHT_BRACE,
		);
		$open    = array();
		$fields  = array();

		foreach ( $tokens as $token ) {
			$type = $token->get_type();
			if ( WP_CSS_Token_Processor::TOKEN_WHITESPACE === $type || WP_CSS_Token_Processor::TOKEN_COMMENT === $type ) {
				continue;
			}

			if ( isset( $closers[ $type ] ) ) {
				$open[] = $closers[ $type ];
			} elseif ( in_array( $type, $closers, true ) ) {
				array_pop( $open );
			}

			$fields[] = array( $type, $token->get_flag(), $token->get_value(), $token->get_unit() );
		}

		foreach ( array_reverse( $open ) as $type ) {
			$fields[] = array( $type, null, null, null );
		}

		return $fields;
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

		$this->assertSame( 'color: green; background: white;', $processor->get_updated_style() );
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::set_value
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 *
	 * @dataProvider data_values_ending_in_escaped_whitespace_or_a_backslash_delimiter
	 *
	 * @param string $value   Declaration value.
	 * @param string $written Expected value text in the updated style.
	 */
	public function test_trimming_a_value_keeps_its_final_escape_or_backslash_delimiter( string $value, string $written ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( '--x: 1; width: 1px' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertTrue( $processor->set_value( $value ), 'Expected set_value() to succeed.' );
		$this->assertSame( "--x: {$written}; width: 1px", $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'width: 1px' );

		$this->assertTrue( $processor->append_declaration( '--x', $value ), 'Expected append_declaration() to succeed.' );
		$this->assertSame( "width: 1px; --x: {$written};", $processor->get_updated_style() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_ending_in_escaped_whitespace_or_a_backslash_delimiter() {
		return array(
			'escaped space'                   => array( 'a\\ ', 'a\\20 ' ),
			'escaped space, then whitespace'  => array( "a\\  \t", 'a\\20 ' ),
			'escaped tab'                     => array( "a\\\t", 'a\\9 ' ),
			'backslash before a newline'      => array( "a\\\n", "a\\\n" ),
			'backslash, newline, then spaces' => array( "a\\\n  ", "a\\\n" ),
			'escaped backslash'               => array( 'a\\\\ ', 'a\\5c ' ),
			'escaped backslash, then a delim' => array( "a\\\\\\\n", "a\\5c \\\n" ),
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::set_value
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 *
	 * @dataProvider data_values_written_from_tokens
	 *
	 * @param string $value   Declaration value.
	 * @param string $written Expected value text in the updated style.
	 */
	public function test_set_value_and_append_declaration_write_the_value_from_its_tokens( string $value, string $written ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( '--x: 1; width: 1px' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertTrue( $processor->set_value( $value ), 'Expected set_value() to succeed.' );
		$this->assertSame( "--x: {$written}; width: 1px", $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( 'width: 1px' );

		$this->assertTrue( $processor->append_declaration( '--x', $value ), 'Expected append_declaration() to succeed.' );
		$this->assertSame( "width: 1px; --x: {$written};", $processor->get_updated_style() );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_values_written_from_tokens() {
		return array(
			'comments and whitespace runs'  => array( " \t/* a */ a \t/* b */  b /* c */ ", 'a b' ),
			'comment between tokens'        => array( 'a/**/b', 'a/**/b' ),
			'escaped ident'                 => array( '\\66 oo', 'foo' ),
			'single-quoted string'          => array( "'a'", '"a"' ),
			'control character in a string' => array( "\"a\x01b\"", '"a\\1 b"' ),
			'url with spaces and ampersand' => array( 'url( a&b )', 'url(a\\26 b)' ),
			'non-ident code point in ident' => array( "a\u{A0}b", 'a\\a0 b' ),
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::set_value
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 */
	public function test_set_value_and_append_declaration_write_the_name_as_an_identifier() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'C\\6f LOR: red' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertTrue( $processor->set_value( 'blue' ) );
		$this->assertSame( 'color: blue;', $processor->get_updated_style() );

		$processor = WP_HTML_Style_Attribute_Processor::create( '' );

		$this->assertTrue( $processor->append_declaration( "--a\u{A0}b", '1' ) );
		$this->assertSame( '--a\\a0 b: 1;', $processor->get_updated_style() );
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
	 * @covers ::get_value_tokens
	 */
	public function test_getters_return_null_after_removing_current_declaration_until_cursor_advances() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'color: red; background: white;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertTrue( $processor->remove_declaration() );

		$this->assertNull( $processor->get_property_name() );
		$this->assertNull( $processor->is_important() );
		$this->assertNull( $processor->get_value_tokens() );
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
	 * @ticket 65738
	 *
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 *
	 * @dataProvider data_styles_ending_inside_a_string_or_comment
	 *
	 * @param string $style    Style text.
	 * @param string $expected Updated style text.
	 */
	public function test_append_declaration_closes_strings_and_comments_left_open( string $style, string $expected ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertTrue( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( $expected, $processor->get_updated_style() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function data_styles_ending_inside_a_string_or_comment(): array {
		return array(
			'comment inside an open function' => array( 'color: var(--x;/*keep', 'color: var(--x;/*keep*/); background: white;' ),
			'comment after a declaration'     => array( 'color: red /* keep', 'color: red /* keep*/; background: white;' ),
			'comment ending in a slash'       => array( 'color: red /*/', 'color: red /*/*/; background: white;' ),
			'comment ending in a backslash'   => array( 'color: red /* \\', 'color: red /* \\*/; background: white;' ),
			'double-quoted string'            => array( 'font-family: "a', 'font-family: "a"; background: white;' ),
			'single-quoted string'            => array( "font-family: 'a", "font-family: 'a'; background: white;" ),
			'string inside an open function'  => array( 'width: var(--x, "a', 'width: var(--x, "a"); background: white;' ),
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @covers ::set_important
	 * @covers ::get_updated_style
	 */
	public function test_set_important_closes_a_string_left_open() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'font-family: "a' );

		$this->assertTrue( $processor->next_declaration() );
		$this->assertTrue( $processor->set_important( true ) );
		$this->assertSame( 'font-family: "a" !important', $processor->get_updated_style() );
	}

	/**
	 * @covers ::append_declaration
	 * @covers ::get_updated_style
	 *
	 * @dataProvider data_styles_ending_in_a_backslash
	 *
	 * @param string $style Style text.
	 */
	public function test_append_declaration_rejects_eof_repairs_that_cannot_be_precise( string $style ) {
		$processor = WP_HTML_Style_Attribute_Processor::create( $style );

		$this->assertFalse( $processor->append_declaration( 'background', 'white' ) );
		$this->assertSame( $style, $processor->get_updated_style() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_styles_ending_in_a_backslash(): array {
		return array(
			'backslash after an ident'  => array( 'color: x\\' ),
			'backslash inside a string' => array( 'content: "a\\' ),
		);
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
	public function test_escaped_property_names_match_decoded_names_and_are_written_as_identifiers() {
		$processor = WP_HTML_Style_Attribute_Processor::create( 'c\\6f lor: red; --t\\6f ne: cool; --Tone: warm;' );

		$this->assertTrue( $processor->next_declaration( 'color' ) );
		$this->assertSame( 'color', $processor->get_property_name() );
		$this->assertTrue( $processor->set_value( 'green' ) );
		$this->assertSame( 'color: green; --t\\6f ne: cool; --Tone: warm;', $processor->get_updated_style() );

		$this->assertTrue( $processor->next_declaration( '--tone' ) );
		$this->assertSame( '--tone', $processor->get_property_name() );
		$this->assertTrue( $processor->set_value( 'cold' ) );
		$this->assertSame( 'color: green; --tone: cold; --Tone: warm;', $processor->get_updated_style() );

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
	 * Builds the expected fields of a value token.
	 *
	 * @param string      $type   Token type.
	 * @param string|null $value  Token value.
	 * @param int         $start  Byte offset of the token.
	 * @param int         $length Byte length of the token.
	 * @param string|null $unit   Optional. Dimension unit. Default null.
	 * @return array{type:string, value:string|null, unit:string|null, start:int, length:int}
	 */
	private static function token( string $type, ?string $value, int $start, int $length, ?string $unit = null ): array {
		return array(
			'type'   => $type,
			'value'  => $value,
			'unit'   => $unit,
			'start'  => $start,
			'length' => $length,
		);
	}

	/**
	 * Reads the fields of a value token that the expectations compare.
	 *
	 * @param WP_CSS_Token $token Value token.
	 * @return array{type:string, value:string|null, unit:string|null, start:int, length:int}
	 */
	private static function token_fields( WP_CSS_Token $token ): array {
		return self::token( $token->get_type(), $token->get_value(), $token->get_start(), $token->get_length(), $token->get_unit() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string,array}>
	 */
	public static function data_value_tokens(): array {
		return array(
			'single ident'                     => array(
				'color: red',
				array( self::token( WP_CSS_Token_Processor::TOKEN_IDENT, 'red', 7, 3 ) ),
			),
			'function with nested tokens'      => array(
				'width: calc(1px + 2%)',
				array(
					self::token( WP_CSS_Token_Processor::TOKEN_FUNCTION, 'calc', 7, 5 ),
					self::token( WP_CSS_Token_Processor::TOKEN_DIMENSION, '1', 12, 3, 'px' ),
					self::token( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null, 15, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_DELIM, '+', 16, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null, 17, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_PERCENTAGE, '2', 18, 2 ),
					self::token( WP_CSS_Token_Processor::TOKEN_RIGHT_PAREN, null, 20, 1 ),
				),
			),
			'hash without number sign'         => array(
				'color: #fff',
				array( self::token( WP_CSS_Token_Processor::TOKEN_HASH, 'fff', 7, 4 ) ),
			),
			'important excluded'               => array(
				'color: red !important',
				array( self::token( WP_CSS_Token_Processor::TOKEN_IDENT, 'red', 7, 3 ) ),
			),
			'important without space excluded' => array(
				'color: red!important;',
				array( self::token( WP_CSS_Token_Processor::TOKEN_IDENT, 'red', 7, 3 ) ),
			),
			'leading and trailing trivia'      => array(
				"color: /* a */ \t red /* b */ ;",
				array( self::token( WP_CSS_Token_Processor::TOKEN_IDENT, 'red', 17, 3 ) ),
			),
			'trivia inside the value kept'     => array(
				'margin: 1px /* a */ 2px',
				array(
					self::token( WP_CSS_Token_Processor::TOKEN_DIMENSION, '1', 8, 3, 'px' ),
					self::token( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null, 11, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_COMMENT, null, 12, 7 ),
					self::token( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null, 19, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_DIMENSION, '2', 20, 3, 'px' ),
				),
			),
			'custom property'                  => array(
				'--x: 1 2 3',
				array(
					self::token( WP_CSS_Token_Processor::TOKEN_NUMBER, '1', 5, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null, 6, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_NUMBER, '2', 7, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_WHITESPACE, null, 8, 1 ),
					self::token( WP_CSS_Token_Processor::TOKEN_NUMBER, '3', 9, 1 ),
				),
			),
			'dimension unit'                   => array(
				'width: 10px',
				array( self::token( WP_CSS_Token_Processor::TOKEN_DIMENSION, '10', 7, 4, 'px' ) ),
			),
			'dimension unit decoded'           => array(
				'width: 1p\\78',
				array( self::token( WP_CSS_Token_Processor::TOKEN_DIMENSION, '1', 7, 5, 'px' ) ),
			),
			'number has no unit'               => array(
				'--x: 10',
				array( self::token( WP_CSS_Token_Processor::TOKEN_NUMBER, '10', 5, 2 ) ),
			),
			'percentage has no unit'           => array(
				'width: 10%',
				array( self::token( WP_CSS_Token_Processor::TOKEN_PERCENTAGE, '10', 7, 3 ) ),
			),
			'empty value'                      => array( 'color:;', array() ),
			'only important'                   => array( 'color: !important', array() ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_value_tokens_source_slices(): array {
		return array(
			'single ident'          => array( 'color: red' ),
			'function'              => array( 'width: calc(1px + 2%)' ),
			'string with escape'    => array( 'content: "a\\"b"' ),
			'trivia and important'  => array( 'color: /* a */ red /* b */ !important;' ),
			'custom property'       => array( '--x: 1 2 3' ),
			'custom property block' => array( '--x: { a } b' ),
			'comma list'            => array( 'font: 12px/1.5 "Foo Bar", sans-serif' ),
			'urls'                  => array( 'background: url(a.png) no-repeat, image-set("b.png" 2x)' ),
			'unclosed function'     => array( 'color: var(--x' ),
			'eof escape'            => array( 'color: red\\' ),
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
