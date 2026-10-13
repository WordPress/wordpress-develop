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
		$this->assertSame( 'color: red;', safecss_filter_attr( 'color:red' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_property_names_match_case_insensitively() {
		$this->assertSame( 'text-transform: capitalize;', safecss_filter_attr( 'Text-transform: capitalize' ) );
		$this->assertSame( 'color: red;', safecss_filter_attr( 'COLOR:red' ) );
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

		$this->assertSame( 'foo-bar: baz;foo-bar: baz;foo-bar: baz;', $actual );
	}

	/**
	 * @ticket 65738
	 */
	public function test_custom_property_name_must_match_grammar() {
		$this->assertSame( '--a_b-1: red;', safecss_filter_attr( '--a_b-1: red' ) );
		$this->assertSame( '--miXeD-CAse: red;', safecss_filter_attr( '--miXeD-CAse: red' ) );
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

		$this->assertSame( 'color: red;', $actual );
	}

	/**
	 * @ticket 65738
	 */
	public function test_function_must_be_in_allowed_list() {
		$this->assertSame( 'width: calc(1px);', safecss_filter_attr( 'width: calc(1px)' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: foo(1px)' ) );
		$this->assertSame( '', safecss_filter_attr( 'width: calcmax(100px + 50%)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_nested_function_must_be_in_allowed_list() {
		$this->assertSame( 'width: calc(min(1px));', safecss_filter_attr( 'width: calc(min(1px))' ) );
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
		$this->assertSame( 'transform: skewX(30deg);', safecss_filter_attr( 'transform: skewX(30deg)' ) );
		$this->assertSame( 'width: CALC(1px);', safecss_filter_attr( 'width: CALC(1px)' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_functions_nest_to_any_depth() {
		$this->assertSame(
			'background-image: linear-gradient(red 0%, blue calc(50% + var(--x)));',
			safecss_filter_attr( 'background-image: linear-gradient(red 0%, blue calc(50% + var(--x)))' )
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_color_functions_gradients_and_urls_are_allowed_on_every_property() {
		$this->assertSame( 'color: rgb(1, 2, 3);', safecss_filter_attr( 'color: rgb(1, 2, 3)' ) );
		$this->assertSame( 'color: linear-gradient(red,yellow);', safecss_filter_attr( 'color: linear-gradient(red,yellow)' ) );
		$this->assertSame( 'aspect-ratio: url(https://example.com/a.jpg);', safecss_filter_attr( 'aspect-ratio: url(https://example.com/a.jpg)' ) );
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
			'blur'        => array( 'filter: blur(5px);' ),
			'brightness'  => array( 'filter: brightness(0.4);' ),
			'contrast'    => array( 'filter: contrast(200%);' ),
			'drop-shadow' => array( 'filter: drop-shadow(16px 16px 20px rgb(0 0 255 / 50%));' ),
			'grayscale'   => array( 'filter: grayscale(50%);' ),
			'hue-rotate'  => array( 'filter: hue-rotate(90deg);' ),
			'invert'      => array( 'filter: invert(75%);' ),
			'opacity'     => array( 'filter: opacity(25%);' ),
			'saturate'    => array( 'filter: saturate(30%);' ),
			'sepia'       => array( 'filter: sepia(60%);' ),
			'chained'     => array( 'filter: blur(5px) brightness(0.4);' ),
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @dataProvider data_math_functions
	 *
	 * @param string $css CSS declaration.
	 */
	public function test_math_functions_are_allowed( $css ) {
		$this->assertSame( $css, safecss_filter_attr( $css ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_math_functions() {
		return array(
			'abs'   => array( 'width: calc(100% - abs(-10px));' ),
			'sign'  => array( 'width: calc(10px * sign(-1));' ),
			'round' => array( 'width: round(up, 10.5px, 1px);' ),
			'mod'   => array( 'width: mod(18px, 5px);' ),
			'rem'   => array( 'width: rem(18px, 5px);' ),
			'pow'   => array( 'width: calc(1px * pow(2, 3));' ),
			'sqrt'  => array( 'width: calc(1px * sqrt(16));' ),
			'hypot' => array( 'width: hypot(3px, 4px);' ),
			'log'   => array( 'width: calc(1px * log(8, 2));' ),
			'exp'   => array( 'width: calc(1px * exp(1));' ),
			'sin'   => array( 'transform: rotate(calc(sin(45deg) * 1turn));' ),
			'cos'   => array( 'width: calc(10px * cos(0deg));' ),
			'tan'   => array( 'width: calc(10px * tan(45deg));' ),
			'asin'  => array( 'transform: rotate(asin(1));' ),
			'acos'  => array( 'transform: rotate(acos(0));' ),
			'atan'  => array( 'transform: rotate(atan(1));' ),
			'atan2' => array( 'transform: rotate(atan2(1, 1));' ),
		);
	}

	/**
	 * @ticket 65738
	 *
	 * @dataProvider data_easing_functions
	 *
	 * @param string $css CSS declaration.
	 */
	public function test_easing_functions_are_allowed( $css ) {
		$add = static function ( $allowed ) {
			$allowed[] = 'transition-timing-function';
			return $allowed;
		};
		add_filter( 'safe_style_css', $add );
		$actual = safecss_filter_attr( $css );
		remove_filter( 'safe_style_css', $add );

		$this->assertSame( $css, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_easing_functions() {
		return array(
			'cubic-bezier' => array( 'transition-timing-function: cubic-bezier(0.1, 0.7, 1, 0.1);' ),
			'steps'        => array( 'transition-timing-function: steps(4, jump-end);' ),
			'linear'       => array( 'transition-timing-function: linear(0, 0.25 75%, 1);' ),
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
		$this->assertSame( 'background: url(https://example.com/a.png);', safecss_filter_attr( 'background: url(https://example.com/a.png)' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url(bad://example.com/a.png)' ) );
		$this->assertSame( '', safecss_filter_attr( 'background: url("bad://example.com/a.png")' ) );
		$this->assertSame( '', safecss_filter_attr( "background: url('  \tbad://example.com/a.png ')" ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_url_nested_in_function_must_pass_protocol_check() {
		$this->assertSame(
			'background-image: linear-gradient(url(https://example.com/a.png), red);',
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
	 * The src() function exists so var() can supply a URL, which the filter cannot check.
	 *
	 * @ticket 65738
	 */
	public function test_src_function_is_rejected() {
		$this->assertSame( '', safecss_filter_attr( 'background-image: src("https://example.com/a.png")' ) );
		$this->assertSame( '', safecss_filter_attr( 'background-image: src(var(--x))' ) );
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
		$this->assertSame( 'color: red;', safecss_filter_attr( 'width: {}; color: red' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_brace_block_is_kept_in_custom_property() {
		$this->assertSame( '--x: {a:b};', safecss_filter_attr( '--x: {a:b}' ) );
		$this->assertSame( '--x: {};', safecss_filter_attr( '--x: {}' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_square_bracket_block_is_kept_in_standard_property() {
		$this->assertSame( 'grid-template-columns: [a] 1fr [b];', safecss_filter_attr( 'grid-template-columns: [a] 1fr [b]' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_bare_parenthesis_block_is_kept_inside_function() {
		$this->assertSame( 'width: calc(3em + (10px * 2));', safecss_filter_attr( 'width: calc(3em + (10px * 2))' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_bare_parenthesis_block_is_kept_in_custom_property() {
		$this->assertSame( '--x: (1px);', safecss_filter_attr( '--x: (1px)' ) );
	}

	/**
	 * CSS closes a comment, string or url token that the input ends inside,
	 * so the declaration is kept and the token is written closed.
	 *
	 * @ticket 65738
	 * @dataProvider data_input_ending_inside_a_token
	 *
	 * @param string $css      Input whose last token is a comment, string or url token cut off by the end of the input.
	 * @param string $expected Expected output.
	 */
	public function test_token_cut_off_by_the_end_of_the_input_is_written_closed( $css, $expected ) {
		$this->assertSame( $expected, safecss_filter_attr( $css ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_input_ending_inside_a_token() {
		return array(
			'comment in the value'               => array( 'color: red /*', 'color: red;' ),
			'comment after the last declaration' => array( 'color: red; /*', 'color: red;' ),
			'comment in the second value'        => array( 'color: red; width: 1px /*', 'color: red;width: 1px;' ),
			'comment "/*/"'                      => array( 'color: red; width: 1px /*/', 'color: red;width: 1px;' ),
			'string in the second value'         => array( 'color: red; width: "abc', 'color: red;width: "abc";' ),
			'string in a standard property'      => array( 'font-family: "abc', 'font-family: "abc";' ),
			'string with escaped quote'          => array( 'color: red; font-family: "a\\"', 'color: red;font-family: "a\\22 ";' ),
			'url token'                          => array( 'background: url(http://x/a.png', 'background: url(http://x/a.png);' ),
			'url token with trailing whitespace' => array( 'background: url(http://x/a.png ', 'background: url(http://x/a.png);' ),
			'url token in the second value'      => array( 'color: red; background: url(http://x/a.png', 'color: red;background: url(http://x/a.png);' ),
			'string in a custom property'        => array( '--x: "abc', '--x: "abc";' ),
			'comment in a custom property'       => array( '--x: red /*', '--x: red;' ),
			'url token in a custom property'     => array( '--x: url(a', '--x: url(a);' ),
			'string after a rejected value'      => array( 'color: red; foo: "abc', 'color: red;' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_token_terminated_at_end_of_input_is_kept() {
		$this->assertSame( 'color: red;', safecss_filter_attr( 'color: red /* ok */' ) );
		$this->assertSame( 'color: red;', safecss_filter_attr( 'color: red /**/' ) );
		$this->assertSame( 'color: red;', safecss_filter_attr( 'color: red; /**/' ) );
		$this->assertSame( 'font-family: "abc";', safecss_filter_attr( 'font-family: "abc"' ) );
		$this->assertSame( 'font-family: "a\\5c ";', safecss_filter_attr( 'font-family: "a\\\\"' ) );
		$this->assertSame( '--x: "abc";', safecss_filter_attr( '--x: "abc"' ) );
		$this->assertSame( 'background: url(http://x/a.png);', safecss_filter_attr( 'background: url(http://x/a.png)' ) );
		$this->assertSame( 'background: url(http://x/a.png);', safecss_filter_attr( 'background: url(http://x/a.png )' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_important_is_preserved() {
		$this->assertSame( 'color: red !important;', safecss_filter_attr( 'color: red !important' ) );
		$this->assertSame( 'color: red !important;', safecss_filter_attr( 'color: red!important' ) );
		$this->assertSame( 'color: red !important;', safecss_filter_attr( 'color: red ! important ;' ) );
		$this->assertSame( 'color: red !important;width: 1px;', safecss_filter_attr( 'color: red !important; width: 1px' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_comments_are_dropped() {
		$this->assertSame( 'color: red;', safecss_filter_attr( 'color: /* a */ red /* b */' ) );
		$this->assertSame( 'color: red blue;', safecss_filter_attr( 'color: red /* a */blue' ) );
		$this->assertSame( 'color: red;', safecss_filter_attr( '/* a */ color /* b */ : red' ) );
	}

	/**
	 * A pair of tokens that would read as other tokens when written next to
	 * each other keeps an empty comment between them, as CSS Syntax Level 3
	 * §9 requires. There is one case per marked cell of its table.
	 *
	 * @ticket 65738
	 * @dataProvider data_marked_token_pairs
	 *
	 * @param string $first  First token, the row of the table.
	 * @param string $second Second token, the column of the table.
	 */
	public function test_marked_token_pair_keeps_an_empty_comment( $first, $second ) {
		// A bad url makes CSS drop the declaration.
		$expected = 'url(a b)' === $second ? '' : "--x: {$first}/**/{$second};";

		$this->assertSame( $expected, safecss_filter_attr( "--x: {$first}/**/{$second}" ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_marked_token_pairs() {
		$columns = array(
			'ident'      => 'b',
			'function'   => 'calc(1)',
			'url'        => 'url(u)',
			'bad url'    => 'url(a b)',
			'-'          => '-',
			'number'     => '1',
			'percentage' => '1%',
			'dimension'  => '1px',
			'CDC'        => '-->',
			'('          => '(1)',
			'*'          => '*',
			'%'          => '%',
		);

		$marked_after_names = array( 'ident', 'function', 'url', 'bad url', '-', 'number', 'percentage', 'dimension', 'CDC' );
		$rows               = array(
			'ident'      => array( 'a', array_merge( $marked_after_names, array( '(' ) ) ),
			'at-keyword' => array( '@a', $marked_after_names ),
			'hash'       => array( '#a', $marked_after_names ),
			'dimension'  => array( '1px', $marked_after_names ),
			'#'          => array( '#', $marked_after_names ),
			'-'          => array( '-', $marked_after_names ),
			'number'     => array( '1', array( 'ident', 'function', 'url', 'bad url', 'number', 'percentage', 'dimension', 'CDC', '%' ) ),
			'@'          => array( '@', array( 'ident', 'function', 'url', 'bad url', '-', 'CDC' ) ),
			'.'          => array( '.', array( 'number', 'percentage', 'dimension' ) ),
			'+'          => array( '+', array( 'number', 'percentage', 'dimension' ) ),
			'/'          => array( '/', array( '*' ) ),
		);

		$pairs = array();
		foreach ( $rows as $row => list( $first, $marked ) ) {
			foreach ( $marked as $column ) {
				$pairs[ "{$row} then {$column}" ] = array( $first, $columns[ $column ] );
			}
		}

		return $pairs;
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_unmarked_token_pairs
	 *
	 * @param string $css      Input with a comment between two tokens.
	 * @param string $expected Filtered output.
	 */
	public function test_unmarked_token_pair_drops_the_comment( $css, $expected ) {
		$this->assertSame( $expected, safecss_filter_attr( $css ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_unmarked_token_pairs() {
		return array(
			'ident then comma'  => array( '--x: a/**/,b', '--x: a,b;' ),
			'number then -'     => array( '--x: 1/**/-', '--x: 1-;' ),
			'hash then ('       => array( '--x: #a/**/(1)', '--x: #a(1);' ),
			'at-keyword then (' => array( '--x: @a/**/(1)', '--x: @a(1);' ),
			'. then ident'      => array( '--x: ./**/a', '--x: .a;' ),
			'+ then ident'      => array( '--x: +/**/a', '--x: +a;' ),
			'/ then ident'      => array( '--x: //**/a', '--x: /a;' ),
			'dimension then *'  => array( '--x: 1px/**/*', '--x: 1px*;' ),
			'string then ident' => array( '--x: "a"/**/b', '--x: "a"b;' ),
			') then function'   => array( 'width: calc(1px)/**/calc(2px)', 'width: calc(1px)calc(2px);' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_whitespace_runs_become_one_space() {
		$this->assertSame( 'color: red blue;', safecss_filter_attr( "color:   red \n\t blue  " ) );
		$this->assertSame( 'aspect-ratio: calc( 16 / 9 );', safecss_filter_attr( 'aspect-ratio: calc( 16 / 9 );' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_escapes_are_decoded_before_checks() {
		// Property name.
		$this->assertSame( 'color: red;', safecss_filter_attr( '\63 olor: red' ) );
		// Allowed function name.
		$this->assertSame( 'width: calc(1px);', safecss_filter_attr( 'width: c\61 lc(1px)' ) );
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
		$this->assertSame( 'margin-top: \2 px;', safecss_filter_attr( 'margin-top: \2px' ) );
		// An escape of a letter decodes and re-serializes without the escape.
		$this->assertSame( 'color: red;', safecss_filter_attr( 'color: r\65 d' ) );
		$this->assertSame( 'font-family: "a\3c b";', safecss_filter_attr( 'font-family: "a<b"' ) );
		$this->assertSame( 'font-family: "a\22 b";', safecss_filter_attr( "font-family: 'a\"b'" ) );
	}

	/**
	 * CSS closes a block left open at the end of the input, so the
	 * declaration is checked like any other and written with the block closed.
	 *
	 * @ticket 65738
	 * @dataProvider data_blocks_left_open_at_end_of_input
	 *
	 * @param string $css      Input ending inside one or more blocks.
	 * @param string $expected Filtered output.
	 */
	public function test_block_left_open_at_end_of_input_is_closed( $css, $expected ) {
		$this->assertSame( $expected, safecss_filter_attr( $css ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_blocks_left_open_at_end_of_input() {
		return array(
			'function'                       => array( 'width: calc(1px', 'width: calc(1px);' ),
			'nested functions'               => array( 'width: var(--a, var(--b', 'width: var(--a, var(--b));' ),
			'parenthesis inside a function'  => array( 'width: calc(3em + (10px * 2)', 'width: calc(3em + (10px * 2));' ),
			'square bracket'                 => array( 'width: [1px', 'width: [1px];' ),
			'url function with a string'     => array( 'background: url("a.png"', 'background: url("a.png");' ),
			'url function with whitespace'   => array( 'background: url( "a.png" ', 'background: url( "a.png");' ),
			// The `;` is inside the open function, so there is one declaration, as a browser reads it.
			'semicolon inside the function'  => array( 'width: calc(1px; color: red', 'width: calc(1px; color: red);' ),
			'custom property function'       => array( '--x: calc(1px', '--x: calc(1px);' ),
			'custom property parenthesis'    => array( '--x: (1px', '--x: (1px);' ),
			'custom property square bracket' => array( '--x: [a', '--x: [a];' ),
			'custom property brace'          => array( '--x: {a:b', '--x: {a:b};' ),
			'after an accepted declaration'  => array( 'color: red; width: calc(1px', 'color: red;width: calc(1px);' ),
			'function not on the allowlist'  => array( 'width: foo(1px', '' ),
			'url function with a bad scheme' => array( 'background: url("javascript:a"', '' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_closed_block_at_end_of_input_is_kept() {
		$this->assertSame( 'width: calc(1px);', safecss_filter_attr( 'width: calc(1px)' ) );
		$this->assertSame( '--x: (1px);', safecss_filter_attr( '--x: (1px)' ) );
		$this->assertSame( '--x: {a:b};', safecss_filter_attr( '--x: {a:b}' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_declaration_css_drops_is_dropped() {
		// Unmatched `)`.
		$this->assertSame( '', safecss_filter_attr( 'width: clamp(min(100px, 350px), 50%, 500px), 600px)' ) );
		$this->assertSame( '', safecss_filter_attr( 'margin-bottom: 2px)' ) );
		// Item with no colon.
		$this->assertSame( 'width: 1px;', safecss_filter_attr( 'color; width: 1px' ) );
		// Newline inside a string.
		$this->assertSame( 'width: 1px;', safecss_filter_attr( "font-family: \"a\nb; width: 1px" ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_output_format() {
		$this->assertSame( 'margin-top: 2px;', safecss_filter_attr( 'margin-top: 2px' ) );
		$this->assertSame( 'font-family: "a";', safecss_filter_attr( "font-family: 'a'" ) );
		$this->assertSame( 'background: url(a.png);', safecss_filter_attr( 'background: url(a.png)' ) );
		$this->assertSame( 'background: url( "a.png" );', safecss_filter_attr( "background: url( 'a.png' )" ) );
		$this->assertSame( 'font-weight: bold;font-size: 15px;', safecss_filter_attr( 'font-weight: bold; font-size: 15px' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_null_bytes_are_not_emitted() {
		$actual = safecss_filter_attr( "color: a\0b" );
		$this->assertStringNotContainsString( "\0", $actual );
		$this->assertSame( "color: a\u{FFFD}b;", $actual );
	}

	/**
	 * @ticket 65738
	 */
	public function test_empty_allowed_list_returns_the_input() {
		$input = 'color: red /* keep me */; width: calc(1px; foo: bar';

		add_filter( 'safe_style_css', '__return_empty_array' );
		$actual = safecss_filter_attr( $input );
		$legacy = safecss_filter_attr( "color:\0 red;\nwidth: 1px" );
		remove_filter( 'safe_style_css', '__return_empty_array' );

		$this->assertSame( $input, $actual );
		$this->assertSame( 'color: red;width: 1px', $legacy );
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_non_array_allowed_list
	 *
	 * @param mixed $allowed_attr Value the `safe_style_css` filter returns.
	 */
	public function test_non_array_allowed_list_uses_the_default_list( $allowed_attr ) {
		$callback = static function () use ( $allowed_attr ) {
			return $allowed_attr;
		};

		add_filter( 'safe_style_css', $callback );
		$actual = safecss_filter_attr( 'color: red; user-select: none' );
		add_filter( 'wp_kses_force_legacy_css_parser', '__return_true' );
		$legacy = safecss_filter_attr( 'color: red; user-select: none' );
		remove_filter( 'wp_kses_force_legacy_css_parser', '__return_true' );
		remove_filter( 'safe_style_css', $callback );

		$this->assertSame( 'color: red;', $actual, 'The parser-based filter should use the default list.' );
		$this->assertSame( 'color: red', $legacy, 'The legacy filter should use the default list.' );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public function data_non_array_allowed_list() {
		return array(
			'null'         => array( null ),
			'false'        => array( false ),
			'empty string' => array( '' ),
			'zero'         => array( 0 ),
			'string'       => array( 'color' ),
			'true'         => array( true ),
			'ArrayObject'  => array( new ArrayObject( array( 'color' ) ) ),
		);
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_allowed_list_with_entries_that_are_not_strings
	 *
	 * @param array  $allowed_attr    Allowed list the `safe_style_css` filter returns.
	 * @param string $expected        Expected output of the parser-based filter.
	 * @param string $expected_legacy Expected output of the legacy filter.
	 */
	public function test_allowed_list_entries_that_are_not_strings_are_ignored( $allowed_attr, $expected, $expected_legacy ) {
		$callback = static function () use ( $allowed_attr ) {
			return $allowed_attr;
		};

		add_filter( 'safe_style_css', $callback );
		$actual = safecss_filter_attr( 'color: red; display: none' );
		add_filter( 'wp_kses_force_legacy_css_parser', '__return_true' );
		$legacy = safecss_filter_attr( 'color: red; display: none' );
		remove_filter( 'wp_kses_force_legacy_css_parser', '__return_true' );
		remove_filter( 'safe_style_css', $callback );

		$this->assertSame( $expected, $actual, 'Parser-based output does not match.' );
		$this->assertSame( $expected_legacy, $legacy, 'Legacy output does not match.' );
	}

	/**
	 * @return array<string, array{array, string, string}>
	 */
	public function data_allowed_list_with_entries_that_are_not_strings() {
		return array(
			'array entry'          => array( array( 'color', array( 'display' ) ), 'color: red;', 'color: red' ),
			'null entry'           => array( array( 'color', null ), 'color: red;', 'color: red' ),
			'integer entry'        => array( array( 'color', 5 ), 'color: red;', 'color: red' ),
			'only false'           => array( array( false ), '', '' ),
			'only null'            => array( array( null ), '', '' ),
			'only an array'        => array( array( array( 'color', 'display' ) ), '', '' ),
			'only a wildcard list' => array( array( array( '--*' ) ), '', '' ),
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
		$this->assertSame( 'margin-top: 2px;', $actual );
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
		$this->assertSame( 'margin-top: 2px;', safecss_filter_attr( 'margin-top: 2px' ) );
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_ampersand_delimiters
	 *
	 * @param string $css Input with a `&` delimiter.
	 */
	public function test_ampersand_delimiter_is_rejected( $css ) {
		$this->assertSame( '', safecss_filter_attr( $css ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_ampersand_delimiters() {
		return array(
			'top level' => array( 'color: red & blue' ),
			'in calc'   => array( 'width: calc(1px & 2px)' ),
			'in custom' => array( '--x: a & b' ),
			'in var'    => array( 'color: var(--x, a&b)' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_ampersand_delimiter_rejects_only_its_own_declaration() {
		$this->assertSame( 'color: red;', safecss_filter_attr( 'color: red; width: 1px &' ) );
		$this->assertSame( 'width: 1px;', safecss_filter_attr( 'color: a & b; width: 1px' ) );
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_control_character_delimiters
	 *
	 * @param string $css Input with a control character delimiter.
	 */
	public function test_control_character_delimiter_is_rejected( $css ) {
		$this->assertSame( 'width: 1px;', safecss_filter_attr( "$css; width: 1px" ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_control_character_delimiters() {
		return array(
			'U+0001'    => array( "color: red\x01" ),
			'U+0008'    => array( "color: red\x08" ),
			'U+000B'    => array( "color: red\x0B" ),
			'U+000E'    => array( "color: red\x0E" ),
			'U+001F'    => array( "color: red\x1F" ),
			'U+007F'    => array( "color: red\x7F" ),
			'in calc'   => array( "width: calc(1px \x01 2px)" ),
			'in custom' => array( "--x: a\x01b" ),
			'in var'    => array( "color: var(--x, a\x01)" ),
		);
	}

	/**
	 * wp_sanitize_html_kses() removes raw control characters from the
	 * document and decodes character references afterward, so `&#1;` reaches
	 * the filter as a control character.
	 *
	 * @ticket 65738
	 */
	public function test_control_character_from_a_character_reference_is_rejected() {
		$this->assertSame( '<p style="width: 1px;">x</p>', wp_kses_post( '<p style="color: red&#1;; width: 1px">x</p>' ) );
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_output_without_ampersand
	 *
	 * @param string $css Input with a `&` in a token other than a delimiter.
	 */
	public function test_output_contains_no_ampersand( $css ) {
		$output = safecss_filter_attr( $css );

		$this->assertNotSame( '', $output );
		$this->assertStringNotContainsString( '&', $output );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_output_without_ampersand() {
		return array(
			'string'        => array( 'font-family: "a&b"' ),
			'quoted url'    => array( 'background: url("a?x=1&y=2")' ),
			'unquoted url'  => array( 'background: url(a?x=1&y=2)' ),
			'escaped ident' => array( 'font-family: a\26 b' ),
			'escaped hash'  => array( 'color: #a\26 b' ),
			'custom string' => array( '--x: "&amp;"' ),
		);
	}

	/**
	 * @ticket 65738
	 * @dataProvider data_delimiters_kept
	 *
	 * @param string $css      Input with a `<`, `>` or `=` delimiter or a `<!--` or `-->` token.
	 * @param string $expected Filtered output.
	 */
	public function test_other_delimiters_are_kept( $css, $expected ) {
		$this->assertSame( $expected, safecss_filter_attr( $css ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_delimiters_kept() {
		return array(
			'less than'    => array( '--x: a < b', '--x: a < b;' ),
			'greater than' => array( '--x: a > b', '--x: a > b;' ),
			'equals'       => array( '--x: a = b', '--x: a = b;' ),
			'CDO'          => array( '--x: <!-- a', '--x: <!-- a;' ),
			'CDC'          => array( '--x: a -->', '--x: a -->;' ),
			'in calc'      => array( 'width: calc(1px < 2px)', 'width: calc(1px < 2px);' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_delimiters_are_escaped_inside_strings_and_urls() {
		$this->assertSame( 'font-family: "a\26 b\3c c\3e d=e";', safecss_filter_attr( 'font-family: "a&b<c>d=e"' ) );
		$this->assertSame( 'background: url(a?x=1\26 y=2);', safecss_filter_attr( 'background: url(a?x=1&y=2)' ) );
		$this->assertSame( 'background: url("a?x=1\26 y=2");', safecss_filter_attr( 'background: url("a?x=1&y=2")' ) );
		$this->assertSame( '--x: "\\3c !--";', safecss_filter_attr( '--x: "<!--"' ) );
	}

	/**
	 * @ticket 65738
	 */
	public function test_kses_rejects_ampersand_delimiter_in_style_attribute() {
		$this->assertSame( '<p style="width: 1px;">x</p>', wp_kses_post( '<p style="color: a &amp; b; width: 1px">x</p>' ) );
	}

	/**
	 * The serialized value must re-tokenize to the tokens the filter checked
	 * and be unchanged by a second filtering.
	 *
	 * @ticket 65738
	 * @dataProvider data_serialized_tokens
	 *
	 * @param string $css Input.
	 */
	public function test_serialized_value_retokenizes_to_the_same_tokens( $css ) {
		$once = safecss_filter_attr( $css );

		$expected   = self::significant_tokens( $css );
		$expected[] = array( WP_CSS_Token_Processor::TOKEN_SEMICOLON, null, null, null );
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
			'id hash starting with a digit escape' => array( '--x: #\\31 a' ),
			'id hash starting with -'              => array( '--x: #-a' ),
		);
	}

	/**
	 * @ticket 65738
	 */
	public function test_serialized_tokens_output() {
		$this->assertSame( 'color: #ab/**/c;', safecss_filter_attr( 'color: #a\\62/**/c' ) );
		$this->assertSame( 'font: 1px/**/serif;', safecss_filter_attr( 'font: 1p\\78/**/serif' ) );
		$this->assertSame( '--x: @ab/**/c;', safecss_filter_attr( '--x: @a\\62/**/c' ) );
		$this->assertSame( 'color: #123456;', safecss_filter_attr( 'color: #123456' ) );
		$this->assertSame( 'width: 10PX;', safecss_filter_attr( 'width: 10PX' ) );
		$this->assertSame( "color: #fff\u{FFFD};", safecss_filter_attr( 'color:#fff\\' ) );
		$this->assertSame( "width: 10px\u{FFFD};", safecss_filter_attr( 'width:10px\\' ) );
		$this->assertStringNotContainsString( "\0", safecss_filter_attr( '--x: #a\\0b' ) );
		$this->assertStringNotContainsString( "\0", safecss_filter_attr( "--x: #a\0b; width: 1\0px" ) );
	}

	/**
	 * A hash name that starts like an identifier keeps the escapes an
	 * identifier needs, so it is read back with the same type flag.
	 *
	 * @ticket 65738
	 */
	public function test_hash_keeps_its_type_flag() {
		$this->assertSame( '--x: #\31 a;', safecss_filter_attr( '--x: #\31 a' ) );
		$this->assertSame( '--x: #1a;', safecss_filter_attr( '--x: #1a' ) );
	}

	/**
	 * Non-trivia tokens of a style text as (type, flag, value, unit) tuples.
	 *
	 * @param string $css CSS text.
	 * @return array<int, array{string, string|null, string|null, string|null}>
	 */
	private static function significant_tokens( $css ) {
		$processor = WP_CSS_Token_Processor::create( $css );
		$tokens    = array();
		while ( $processor->next_token() ) {
			$token = $processor->get_token();
			$type  = $token->get_type();
			if ( WP_CSS_Token_Processor::TOKEN_WHITESPACE === $type || WP_CSS_Token_Processor::TOKEN_COMMENT === $type ) {
				continue;
			}
			$tokens[] = array( $type, $token->get_flag(), $token->get_value(), $token->get_unit() );
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
			'open function'            => array( 'width: calc(3em + 10px' ),
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
