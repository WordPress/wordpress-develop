<?php
/**
 * Tests the parser-based implementation of safecss_filter_attr().
 *
 * @group kses
 *
 * @covers ::safecss_filter_attr
 * @covers ::_safecss_filter_attr_declarations
 */
class Tests_Kses_SafecssFilterAttr extends WP_UnitTestCase {

	/**
	 * @ticket 65738
	 */
	public function test_drops_property_not_in_allowed_list() {
		$this->assertSame( '', safecss_filter_attr( 'foo:bar' ) );
		$this->assertSame( 'color:red;', safecss_filter_attr( 'color:red' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_property_names_match_case_insensitively() {
		$this->assertSame( 'text-transform:capitalize;', safecss_filter_attr( 'Text-transform: capitalize' ) );
		$this->assertSame( 'color:red;', safecss_filter_attr( 'COLOR:red' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_custom_property_name_must_match_grammar() {
		$this->assertSame( '--a_b-1:red;', safecss_filter_attr( '--a_b-1: red' ) );
		$this->assertSame( '--miXeD-CAse:red;', safecss_filter_attr( '--miXeD-CAse: red' ) );
		// `\.` decodes to `.`, which the grammar rejects, though the declaration parses.
		$this->assertSame( '', safecss_filter_attr( '--a\.b: red' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_custom_property_requires_wildcard_in_allowed_list() {
		$without_wildcard = static function ( $allowed ) {
			return array_diff( $allowed, array( '--*' ) );
		};
		add_filter( 'safe_style_css', $without_wildcard );
		$actual = safecss_filter_attr( '--x: red; color: red' );
		remove_filter( 'safe_style_css', $without_wildcard );

		$this->assertSame( 'color:red;', $actual );
	}

	/**
	 * @ticket 65738
	 */
	public function test_function_must_be_in_allowed_list() {
		$this->assertSame( 'width:calc(1px);', safecss_filter_attr( 'width: calc(1px)' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: foo(1px)' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: calcmax(100px + 50%)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_nested_function_must_be_in_allowed_list() {
		$this->assertSame( 'width:calc(min(1px));', safecss_filter_attr( 'width: calc(min(1px))' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: calc(foo(1px))' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_disallowed_function_inside_gradient_is_rejected() {
		$this->assertSame( '', safecss_filter_attr( 'background-image: linear-gradient(red, expression(alert))' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_function_name_matches_case_insensitively() {
		$this->assertSame( 'transform:skewX(30deg);', safecss_filter_attr( 'transform: skewX(30deg)' ) );
		$this->assertSame( 'width:CALC(1px);', safecss_filter_attr( 'width: CALC(1px)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_functions_nest_to_any_depth() {
		$this->assertSame(
			'background-image:linear-gradient(red 0%, blue calc(50% + var(--x)));',
			safecss_filter_attr( 'background-image: linear-gradient(red 0%, blue calc(50% + var(--x)))' )
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_color_functions_gradients_and_urls_are_allowed_on_every_property() {
		$this->assertSame( 'color:rgb(1, 2, 3);', safecss_filter_attr( 'color: rgb(1, 2, 3)' ) );
		$this->assertSame( 'color:linear-gradient(red,yellow);', safecss_filter_attr( 'color: linear-gradient(red,yellow)' ) );
		$this->assertSame( 'aspect-ratio:url("https://example.com/a.jpg");', safecss_filter_attr( 'aspect-ratio: url(https://example.com/a.jpg)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_url_must_be_non_empty() {
		$this->assertSame( '', safecss_filter_attr( 'background: url()' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url("")' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url( " " )' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_url_must_pass_protocol_check() {
		$this->assertSame( 'background:url("https://example.com/a.png");', safecss_filter_attr( 'background: url(https://example.com/a.png)' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url(bad://example.com/a.png)' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url("bad://example.com/a.png")' ) );
		$this->assertSame( '', safecss_filter_attr( "background: url('  \tbad://example.com/a.png ')" ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_url_nested_in_function_must_pass_protocol_check() {
		$this->assertSame(
			'background-image:linear-gradient(url("https://example.com/a.png"), red);',
			safecss_filter_attr( 'background-image: linear-gradient(url(https://example.com/a.png), red)' )
		);
		$this->assertSame( '', safecss_filter_attr( 'background-image: linear-gradient(url(bad://example.com/a.png), red)' ) );
		$this->assertSame( '', safecss_filter_attr( 'background-image: linear-gradient(url("bad://example.com/a.png"), red)' ) );
		$this->assertSame( '', safecss_filter_attr( 'background-image: linear-gradient(url(), red)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_url_function_accepts_only_one_string_argument() {
		$this->assertSame( '', safecss_filter_attr( 'background: url("a.png" "b.png")' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url(a b)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_bare_parenthesis_block_is_rejected_in_standard_property() {
		$this->assertSame( '', safecss_filter_attr( 'width: (1px)' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: (3em + (10px * 2))' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: linear-gradient(red) (1px)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_bare_parenthesis_block_is_kept_inside_function() {
		$this->assertSame( 'width:calc(3em + (10px * 2));', safecss_filter_attr( 'width: calc(3em + (10px * 2))' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_bare_parenthesis_block_is_kept_in_custom_property() {
		$this->assertSame( '--x:(1px);', safecss_filter_attr( '--x: (1px)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_unterminated_string_is_rejected_in_standard_property() {
		$this->assertSame( '', safecss_filter_attr( 'color: "abc' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_unterminated_string_is_kept_in_custom_property() {
		$this->assertSame( '--x:"abc";', safecss_filter_attr( '--x: "abc' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_terminated_string_at_end_of_input_is_kept() {
		$this->assertSame( 'font-family:"abc";', safecss_filter_attr( 'font-family: "abc"' ) );
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_string_is_unterminated
	 * @covers ::_safecss_filter_attr_string_is_unterminated
	 *
	 * @param string $source   String token source bytes.
	 * @param bool   $expected Whether the string is unterminated.
	 */
	public function test_string_is_unterminated( $source, $expected ) {
		$token = array(
			'type'   => WP_CSS_Token_Processor::TOKEN_STRING,
			'value'  => null,
			'start'  => 0,
			'length' => strlen( $source ),
			'end'    => strlen( $source ),
		);
		$this->assertSame( $expected, _safecss_filter_attr_string_is_unterminated( $source, $token ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function data_string_is_unterminated() {
		return array(
			'no closing quote'             => array( '"abc', true ),
			'closing quote'                => array( '"abc"', false ),
			'lone quote'                   => array( '"', true ),
			'escaped closing quote'        => array( '"abc\\"', true ),
			'escaped backslash then quote' => array( '"abc\\\\"', false ),
			'empty string'                 => array( '""', false ),
			'single quotes'                => array( "'abc", true ),
		);
	}

	/**
	 * @ticket 65738
	 * @covers ::_safecss_filter_attr_string_is_unterminated
	 */
	public function test_string_not_at_end_of_input_is_terminated() {
		$source = '"abc" red';
		$token  = array(
			'type'   => WP_CSS_Token_Processor::TOKEN_STRING,
			'value'  => 'abc',
			'start'  => 0,
			'length' => 5,
			'end'    => 5,
		);
		$this->assertFalse( _safecss_filter_attr_string_is_unterminated( $source, $token ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_important_is_preserved() {
		$this->assertSame( 'color:red !important;', safecss_filter_attr( 'color: red !important' ) );
		$this->assertSame( 'color:red !important;', safecss_filter_attr( 'color: red!important' ) );
		$this->assertSame( 'color:red !important;', safecss_filter_attr( 'color: red ! important ;' ) );
		$this->assertSame( 'color:red !important;width:1px;', safecss_filter_attr( 'color: red !important; width: 1px' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_comments_are_never_emitted() {
		$this->assertSame( 'color:red;', safecss_filter_attr( 'color: /* a */ red /* b */' ) );
		$this->assertSame( 'color:red blue;', safecss_filter_attr( 'color: red/* a */blue' ) );
		$this->assertSame( 'color:red;', safecss_filter_attr( '/* a */ color /* b */ : red' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_whitespace_runs_become_one_space() {
		$this->assertSame( 'color:red blue;', safecss_filter_attr( "color:   red \n\t blue  " ) );
		$this->assertSame( 'aspect-ratio:calc( 16 / 9 );', safecss_filter_attr( 'aspect-ratio: calc( 16 / 9 );' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_escapes_are_decoded_before_checks() {
		// Property name.
		$this->assertSame( 'color:red;', safecss_filter_attr( '\63 olor: red' ) );
		// Allowed function name.
		$this->assertSame( 'width:calc(1px);', safecss_filter_attr( 'width: c\61 lc(1px)' ) );
		// Disallowed function name.
		$this->assertSame( '', safecss_filter_attr( 'width: f\6f o(1px)' ) );
		// URL scheme.
		$this->assertSame( '', safecss_filter_attr( 'background: url("j\61 vascript:alert")' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url(j\61 vascript:alert)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_escapes_are_re_escaped_in_output() {
		$this->assertSame( 'margin-top:\2 px;', safecss_filter_attr( 'margin-top: \2px' ) );
		// An escape of a letter decodes and re-serializes without the escape.
		$this->assertSame( 'color:red;', safecss_filter_attr( 'color: r\65 d' ) );
		$this->assertSame( 'font-family:"a\3C b";', safecss_filter_attr( 'font-family: "a<b"' ) );
		$this->assertSame( 'font-family:"a\22 b";', safecss_filter_attr( "font-family: 'a\"b'" ) );
	}

	/**
	 * @ticket 65738
	 * @covers ::_safecss_filter_attr_value_has_open_block
	 */
	public function test_block_left_open_at_end_of_input_is_rejected() {
		$this->assertSame( '', safecss_filter_attr( 'width: calc(1px' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: var(--a, var(--b' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: calc(3em + (10px * 2)' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url("a.png"' ) );
		// The `;` is inside the open function, so there is one declaration and it is rejected.
		$this->assertSame( '', safecss_filter_attr( 'width: calc(1px; color: red' ) );
	}

	/**
	 * @ticket 65738
	 * @covers ::_safecss_filter_attr_value_has_open_block
	 */
	public function test_block_left_open_at_end_of_input_is_rejected_in_custom_property() {
		$this->assertSame( '', safecss_filter_attr( '--x: calc(1px' ) );
		$this->assertSame( '', safecss_filter_attr( '--x: (1px' ) );
		$this->assertSame( '', safecss_filter_attr( '--x: [a' ) );
		$this->assertSame( '', safecss_filter_attr( '--x: {a:b' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_closed_block_at_end_of_input_is_kept() {
		$this->assertSame( 'width:calc(1px);', safecss_filter_attr( 'width: calc(1px)' ) );
		$this->assertSame( '--x:(1px);', safecss_filter_attr( '--x: (1px)' ) );
		$this->assertSame( '--x:{a:b};', safecss_filter_attr( '--x: {a:b}' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_declaration_css_drops_is_dropped() {
		// Unmatched `)`.
		$this->assertSame( '', safecss_filter_attr( 'width: clamp(min(100px, 350px), 50%, 500px), 600px)' ) );
		$this->assertSame( '', safecss_filter_attr( 'margin-bottom: 2px)' ) );
		// Item with no colon.
		$this->assertSame( 'width:1px;', safecss_filter_attr( 'color; width: 1px' ) );
		// Newline inside a string.
		$this->assertSame( 'width:1px;', safecss_filter_attr( "font-family: \"a\nb; width: 1px" ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_output_format() {
		$this->assertSame( 'margin-top:2px;', safecss_filter_attr( 'margin-top: 2px' ) );
		$this->assertSame( 'font-family:"a";', safecss_filter_attr( "font-family: 'a'" ) );
		$this->assertSame( 'background:url("a.png");', safecss_filter_attr( 'background: url(a.png)' ) );
		$this->assertSame( 'background:url( "a.png" );', safecss_filter_attr( "background: url( 'a.png' )" ) );
		$this->assertSame( 'font-weight:bold;font-size:15px;', safecss_filter_attr( 'font-weight: bold; font-size: 15px' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_null_bytes_are_not_emitted() {
		$actual = safecss_filter_attr( "color: a\0b" );
		$this->assertStringNotContainsString( "\0", $actual );
		$this->assertSame( "color:a\u{FFFD}b;", $actual );
	}

	/**
	 * @ticket 65738
	 */
	public function test_empty_allowed_list_skips_checks_but_still_serializes() {
		add_filter( 'safe_style_css', '__return_empty_array' );
		$arbitrary  = safecss_filter_attr( 'foo: expression(1); width: (1px)' );
		$invalid    = safecss_filter_attr( 'color; width: 1px); height: 2px' );
		$open_block = safecss_filter_attr( 'width: calc(1px' );
		remove_filter( 'safe_style_css', '__return_empty_array' );

		$this->assertSame( 'foo:expression(1);width:(1px);', $arbitrary );
		$this->assertSame( 'height:2px;', $invalid );
		// The open-block rule does not depend on the allowed list.
		$this->assertSame( '', $open_block );
	}

	/**
	 * @ticket 65738
	 */
	public function test_use_legacy_filter_selects_legacy_output() {
		$seen   = array();
		$legacy = static function ( $use_legacy, $css ) use ( &$seen ) {
			$seen[] = array( $use_legacy, $css );
			return true;
		};
		add_filter( 'safecss_filter_attr_use_legacy', $legacy, 10, 2 );
		$actual = safecss_filter_attr( 'margin-top: 2px; color: rgb(1,2,3)' );
		remove_filter( 'safecss_filter_attr_use_legacy', $legacy );

		$this->assertSame( 'margin-top: 2px', $actual );
		$this->assertSame( array( array( false, 'margin-top: 2px; color: rgb(1,2,3)' ) ), $seen );
	}

	/**
	 * @ticket 65738
	 */
	public function test_allow_css_filter_is_not_applied() {
		$calls = new MockAction();
		add_filter( 'safecss_filter_attr_allow_css', array( $calls, 'filter' ) );
		safecss_filter_attr( 'margin-top: 2px' );
		remove_filter( 'safecss_filter_attr_allow_css', array( $calls, 'filter' ) );

		$this->assertSame( 0, $calls->get_call_count() );
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_idempotence
	 *
	 * @param string $css Input.
	 */
	public function test_filtering_twice_changes_nothing( $css ) {
		$once = safecss_filter_attr( $css );
		$this->assertSame( $once, safecss_filter_attr( $once ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_idempotence() {
		return array(
			'simple'                   => array( 'margin-top: 2px' ),
			'escaped ident'            => array( 'margin-top: \2px' ),
			'open function, rejected'  => array( 'width: calc(3em + 10px' ),
			'custom open string'       => array( '--x: "abc' ),
			'custom bare block'        => array( '--x: (1px)' ),
			'custom brace block'       => array( '--x: {a:b}' ),
			'gradient with url'        => array( "background-image: linear-gradient(135deg, rgb(255,0,0) 0%, rgb(0,0,255) 100%), url('https://example.com/image.jpg')" ),
			'url with spaces'          => array( "background-image: url( '  http://example.com/valid.gif ' );" ),
			'url with newline'         => array( "background-image: url(\n'http://example.com/valid.gif' );" ),
			'important'                => array( 'color: red ! important' ),
			'comments'                 => array( 'color: /* a */ red /* b */ blue' ),
			'string with quote'        => array( "font-family: 'a\"b'" ),
			'backslash before newline' => array( "width: 1px \\\n 2px" ),
			'null byte'                => array( "color: a\0b" ),
			'hash'                     => array( 'color: #ff0' ),
			'square brackets'          => array( 'background-color: var(--wp-var, [pink])' ),
		);
	}
}
