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
	public function test_allowed_list_entries_match_case_insensitively() {
		$add = static function ( $allowed ) {
			$allowed[] = 'Foo-Bar';
			return $allowed;
		};
		add_filter( 'safe_style_css', $add );
		$actual = safecss_filter_attr( 'Foo-Bar: baz; foo-bar: baz; FOO-BAR: baz' );
		remove_filter( 'safe_style_css', $add );

		$this->assertSame( 'foo-bar:baz;foo-bar:baz;foo-bar:baz;', $actual );
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
	 *
	 * @dataProvider data_filter_functions
	 *
	 * @param string $css CSS declaration.
	 */
	public function test_filter_functions_are_allowed( $css ) {
		$this->assertSame( $css, safecss_filter_attr( $css ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_filter_functions() {
		return array(
			'blur'        => array( 'filter:blur(5px);' ),
			'brightness'  => array( 'filter:brightness(0.4);' ),
			'contrast'    => array( 'filter:contrast(200%);' ),
			'drop-shadow' => array( 'filter:drop-shadow(16px 16px 20px rgb(0 0 255 / 50%));' ),
			'grayscale'   => array( 'filter:grayscale(50%);' ),
			'hue-rotate'  => array( 'filter:hue-rotate(90deg);' ),
			'invert'      => array( 'filter:invert(75%);' ),
			'opacity'     => array( 'filter:opacity(25%);' ),
			'saturate'    => array( 'filter:saturate(30%);' ),
			'sepia'       => array( 'filter:sepia(60%);' ),
			'chained'     => array( 'filter:blur(5px) brightness(0.4);' ),
		);
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
	public function test_brace_block_is_rejected_in_standard_property() {
		$this->assertSame( '', safecss_filter_attr( 'color: {a:b}' ) );
		$this->assertSame( '', safecss_filter_attr( 'color: {}' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: {1px}' ) );
		$this->assertSame( 'color:red;', safecss_filter_attr( 'width: {}; color: red' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_brace_block_is_kept_in_custom_property() {
		$this->assertSame( '--x:{a:b};', safecss_filter_attr( '--x: {a:b}' ) );
		$this->assertSame( '--x:{};', safecss_filter_attr( '--x: {}' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_square_bracket_block_is_kept_in_standard_property() {
		$this->assertSame( 'grid-template-columns:[a] 1fr [b];', safecss_filter_attr( 'grid-template-columns: [a] 1fr [b]' ) );
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
	 * @covers ::_safecss_filter_attr_ends_inside_token
	 * @dataProvider data_input_ending_inside_a_token
	 *
	 * @param string $css      Input whose last token is a comment, string or url token cut off by the end of the input.
	 * @param string $expected Expected output.
	 */
	public function test_input_ending_inside_a_token_drops_the_last_declaration( $css, $expected ) {
		$this->assertSame( $expected, safecss_filter_attr( $css ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_input_ending_inside_a_token() {
		return array(
			'comment in the value'               => array( 'color: red /*', '' ),
			'comment after the last declaration' => array( 'color: red; /*', '' ),
			'comment in the second value'        => array( 'color: red; width: 1px /*', 'color:red;' ),
			'comment "/*/"'                      => array( 'color: red; width: 1px /*/', 'color:red;' ),
			'string in the second value'         => array( 'color: red; width: "abc', 'color:red;' ),
			'string in a standard property'      => array( 'color: "abc', '' ),
			'string with escaped quote'          => array( 'color: red; font-family: "a\\"', 'color:red;' ),
			'url token'                          => array( 'background: url(http://x/a.png', '' ),
			'url token with trailing whitespace' => array( 'background: url(http://x/a.png ', '' ),
			'url token in the second value'      => array( 'color: red; background: url(http://x/a.png', 'color:red;' ),
			'string in a custom property'        => array( '--x: "abc', '' ),
			'comment in a custom property'       => array( '--x: red /*', '' ),
			'url token in a custom property'     => array( '--x: url(a', '' ),
			'string after a rejected value'      => array( 'color: red; foo: "abc', '' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_token_terminated_at_end_of_input_is_kept() {
		$this->assertSame( 'color:red;', safecss_filter_attr( 'color: red /* ok */' ) );
		$this->assertSame( 'color:red;', safecss_filter_attr( 'color: red /**/' ) );
		$this->assertSame( 'color:red;', safecss_filter_attr( 'color: red; /**/' ) );
		$this->assertSame( 'font-family:"abc";', safecss_filter_attr( 'font-family: "abc"' ) );
		$this->assertSame( 'font-family:"a\\5C ";', safecss_filter_attr( 'font-family: "a\\\\"' ) );
		$this->assertSame( '--x:"abc";', safecss_filter_attr( '--x: "abc"' ) );
		$this->assertSame( 'background:url("http://x/a.png");', safecss_filter_attr( 'background: url(http://x/a.png)' ) );
		$this->assertSame( 'background:url("http://x/a.png");', safecss_filter_attr( 'background: url(http://x/a.png )' ) );
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
	 * @dataProvider data_empty_allowed_list
	 *
	 * @param callable $callback Callback for `safe_style_css` that returns an empty list or a non-array.
	 */
	public function test_empty_allowed_list_returns_the_input( $callback ) {
		$input = 'color: red /* keep me */; width: calc(1px; foo: bar';

		add_filter( 'safe_style_css', $callback );
		$actual = safecss_filter_attr( $input );
		$legacy = safecss_filter_attr( "color:\0 red;\nwidth: 1px" );
		remove_filter( 'safe_style_css', $callback );

		$this->assertSame( $input, $actual );
		$this->assertSame( 'color: red;width: 1px', $legacy );
	}

	/**
	 * @return array<string, array{callable}>
	 */
	public function data_empty_allowed_list() {
		return array(
			'empty array' => array( '__return_empty_array' ),
			'null'        => array( '__return_null' ),
			'false'       => array( '__return_false' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_empty_allowed_list_returns_the_input_on_the_legacy_path() {
		add_filter( 'safe_style_css', '__return_empty_array' );
		add_filter( 'wp_kses_force_legacy_css_parser', '__return_true' );
		$actual = safecss_filter_attr( 'color: red /* keep me */; width: calc(1px' );
		remove_filter( 'wp_kses_force_legacy_css_parser', '__return_true' );
		remove_filter( 'safe_style_css', '__return_empty_array' );

		$this->assertSame( 'color: red /* keep me */; width: calc(1px', $actual );
	}

	/**
	 * @ticket 65738
	 */
	public function test_force_legacy_css_parser_filter_selects_legacy_output() {
		$seen   = array();
		$legacy = static function ( $force_legacy_css_parser ) use ( &$seen ) {
			$seen[] = $force_legacy_css_parser;
			return true;
		};
		add_filter( 'wp_kses_force_legacy_css_parser', $legacy );
		$actual = safecss_filter_attr( 'margin-top: 2px; color: rgb(1,2,3)' );
		remove_filter( 'wp_kses_force_legacy_css_parser', $legacy );

		$this->assertSame( 'margin-top: 2px', $actual );
		$this->assertSame( array( false ), $seen );
	}

	/**
	 * @ticket 65738
	 * @expectedDeprecated safecss_filter_attr_allow_css
	 */
	public function test_allow_css_filter_is_deprecated_and_not_applied() {
		$calls = new MockAction();
		add_filter( 'safecss_filter_attr_allow_css', array( $calls, 'filter' ) );
		$actual = safecss_filter_attr( 'margin-top: 2px' );
		remove_filter( 'safecss_filter_attr_allow_css', array( $calls, 'filter' ) );

		$this->assertSame( 0, $calls->get_call_count() );
		$this->assertSame( 'margin-top:2px;', $actual );
	}

	/**
	 * @ticket 65738
	 */
	public function test_allow_css_filter_does_not_change_the_output() {
		$this->setExpectedDeprecated( 'safecss_filter_attr_allow_css' );

		$inputs  = array( 'margin-top: 2px', 'width: foo(1px)', 'color: red; width: calc(1px' );
		$without = array_map( 'safecss_filter_attr', $inputs );

		add_filter( 'safecss_filter_attr_allow_css', '__return_true' );
		$allow = array_map( 'safecss_filter_attr', $inputs );
		remove_filter( 'safecss_filter_attr_allow_css', '__return_true' );

		add_filter( 'safecss_filter_attr_allow_css', '__return_false' );
		$deny = array_map( 'safecss_filter_attr', $inputs );
		remove_filter( 'safecss_filter_attr_allow_css', '__return_false' );

		$this->assertSame( $without, $allow );
		$this->assertSame( $without, $deny );
	}

	/**
	 * @ticket 65738
	 */
	public function test_allow_css_filter_is_not_reported_when_no_callback_is_attached() {
		$this->assertSame( 'margin-top:2px;', safecss_filter_attr( 'margin-top: 2px' ) );
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_delimiters_with_html_meaning
	 *
	 * @param string $css Input with a `&`, `<`, `>` or `=` delimiter or a `<!--` or `-->` token.
	 */
	public function test_delimiters_with_html_meaning_are_rejected( $css ) {
		$this->assertSame( '', safecss_filter_attr( $css ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_delimiters_with_html_meaning() {
		return array(
			'ampersand top level'       => array( 'color: red & blue' ),
			'less than top level'       => array( 'color: red < blue' ),
			'greater than top level'    => array( 'color: red > blue' ),
			'equals top level'          => array( 'color: a = b' ),
			'ampersand in calc'         => array( 'width: calc(1px & 2px)' ),
			'less than in calc'         => array( 'width: calc(1px < 2px)' ),
			'greater than in calc'      => array( 'width: calc(1px > 2px)' ),
			'equals in calc'            => array( 'width: calc(1px = 2px)' ),
			'ampersand in custom'       => array( '--x: a & b' ),
			'less than in custom'       => array( '--x: a < b' ),
			'greater than in custom'    => array( '--x: a > b' ),
			'equals in custom'          => array( '--x: a = b' ),
			'ampersand in var fallback' => array( 'color: var(--x, a&b)' ),
			'CDO top level'             => array( 'color: red <!--' ),
			'CDC top level'             => array( 'color: red -->' ),
			'CDO in custom'             => array( '--x: <!-- a' ),
			'CDC in calc'               => array( 'width: calc(1px -->)' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_delimiter_with_html_meaning_rejects_only_its_own_declaration() {
		$this->assertSame( 'color:red;', safecss_filter_attr( 'color: red; width: 1px &' ) );
		$this->assertSame( 'width:1px;', safecss_filter_attr( 'color: a = b; width: 1px' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_delimiters_with_html_meaning_are_escaped_inside_strings_and_urls() {
		$this->assertSame( 'font-family:"a\26 b\3C c\3E d=e";', safecss_filter_attr( 'font-family: "a&b<c>d=e"' ) );
		$this->assertSame( 'background:url("a?x=1\26 y=2");', safecss_filter_attr( 'background: url(a?x=1&y=2)' ) );
		$this->assertSame( 'background:url("a?x=1\26 y=2");', safecss_filter_attr( 'background: url("a?x=1&y=2")' ) );
		$this->assertSame( '--x:"\\3C !--";', safecss_filter_attr( '--x: "<!--"' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_kses_rejects_ampersand_delimiter_in_style_attribute() {
		$this->assertSame( '<p style="width:1px;">x</p>', wp_kses_post( '<p style="color: a &amp; b; width: 1px">x</p>' ) );
	}

	/**
	 * The serialized value must re-tokenize to the tokens the filter checked
	 * and be unchanged by a second filtering.
	 *
	 * @ticket 65738
	 * @covers ::_safecss_filter_attr_serialize_value
	 * @covers ::_safecss_filter_attr_serialize_hash_value
	 * @dataProvider data_serialized_tokens
	 *
	 * @param string $css Input.
	 */
	public function test_serialized_value_retokenizes_to_the_same_tokens( $css ) {
		$once = safecss_filter_attr( $css );

		$expected   = self::significant_tokens( $css );
		$expected[] = array( WP_CSS_Token_Processor::TOKEN_SEMICOLON, null, null );
		$this->assertSame( $expected, self::significant_tokens( $once ) );
		$this->assertSame( $once, safecss_filter_attr( $once ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_serialized_tokens() {
		return array(
			'hash then ident after a comment'      => array( 'color: #a\\62/**/c' ),
			'hash then number after a comment'     => array( '--x: #a\\6/**/2' ),
			'dimension then ident after a comment' => array( 'font: 1p\\78/**/serif' ),
			'dimension then dimension'             => array( 'margin: 1p\\78/**/2px' ),
			'dimension then number'                => array( '--x: 1p\\78/**/-1' ),
			'at-keyword then ident'                => array( '--x: @a\\62/**/c' ),
			'hash with escape at end of input'     => array( 'color:#fff\\' ),
			'dimension with escape at end'         => array( 'width:10px\\' ),
			'hash color'                           => array( 'color: #123456' ),
			'hash name starting with a digit'      => array( '--x: #1a' ),
			'upper case unit'                      => array( 'width: 10PX' ),
			'unit read as an exponent'             => array( '--x: 1\\65 3' ),
			'number'                               => array( '--x: 1e3' ),
			'hash with null escape'                => array( '--x: #a\\0b' ),
			'hash with punctuation'                => array( '--x: #a\\2c b' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_serialized_tokens_output() {
		$this->assertSame( 'color:#ab c;', safecss_filter_attr( 'color: #a\\62/**/c' ) );
		$this->assertSame( 'font:1px serif;', safecss_filter_attr( 'font: 1p\\78/**/serif' ) );
		$this->assertSame( '--x:@ab c;', safecss_filter_attr( '--x: @a\\62/**/c' ) );
		$this->assertSame( 'color:#123456;', safecss_filter_attr( 'color: #123456' ) );
		$this->assertSame( 'width:10PX;', safecss_filter_attr( 'width: 10PX' ) );
		$this->assertSame( "color:#fff\u{FFFD};", safecss_filter_attr( 'color:#fff\\' ) );
		$this->assertSame( "width:10px\u{FFFD};", safecss_filter_attr( 'width:10px\\' ) );
		$this->assertStringNotContainsString( "\0", safecss_filter_attr( '--x: #a\\0b' ) );
		$this->assertStringNotContainsString( "\0", safecss_filter_attr( "--x: #a\0b; width: 1\0px" ) );
	}

	/**
	 * Non-trivia tokens of a style text as (type, value, unit) triples.
	 *
	 * @param string $css CSS text.
	 * @return array<int, array{string, string|null, string|null}>
	 */
	private static function significant_tokens( $css ) {
		$processor = WP_CSS_Token_Processor::create( $css );
		$tokens    = array();
		while ( $processor->next_token() ) {
			$type = $processor->get_token_type();
			if ( WP_CSS_Token_Processor::TOKEN_WHITESPACE === $type || WP_CSS_Token_Processor::TOKEN_COMMENT === $type ) {
				continue;
			}
			$tokens[] = array( $type, $processor->get_token_value(), $processor->get_token_unit() );
		}
		return $tokens;
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
			'string with delimiters'   => array( 'font-family: "a&b<c>d=e"' ),
			'url with query string'    => array( 'background: url(a?x=1&y=2)' ),
		);
	}
}
