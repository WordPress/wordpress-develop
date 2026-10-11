<?php
/**
 * Test WP_Font_Utils::sanitize_font_family().
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 *
 * @covers WP_Font_Utils::sanitize_font_family
 */
class Tests_Fonts_WpFontUtils_SanitizeFontFamily extends WP_UnitTestCase {

	/**
	 * @dataProvider data_should_sanitize_font_family
	 *
	 * @param string $font_family Font family to test.
	 * @param string $expected    Expected family.
	 */
	public function test_should_sanitize_font_family( $font_family, $expected ) {
		$this->assertSame(
			$expected,
			WP_Font_Utils::sanitize_font_family(
				$font_family
			)
		);
	}

	/**
	 * The sanitizer must not change a value that it produced.
	 *
	 * @ticket 63568
	 *
	 * @dataProvider data_should_sanitize_font_family
	 *
	 * @param string $font_family Font family to test.
	 * @param string $expected    Expected family.
	 */
	public function test_should_sanitize_font_family_once( $font_family, $expected ) {
		$once  = WP_Font_Utils::sanitize_font_family( $font_family );
		$twice = WP_Font_Utils::sanitize_font_family( $once );

		$this->assertSame( $once, $twice, 'A second call should return the same value.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_should_sanitize_font_family() {
		return array(
			'data_families_with_spaces_and_numbers'     => array(
				'font_family' => 'Arial, Rock 3D , Open Sans,serif',
				'expected'    => 'Arial, "Rock 3D", "Open Sans", serif',
			),
			'data_single_font_family'                   => array(
				'font_family' => 'Rock 3D',
				'expected'    => '"Rock 3D"',
			),
			'data_many_spaces_and_existing_quotes'      => array(
				'font_family' => 'Rock 3D serif, serif,sans-serif, "Open Sans"',
				'expected'    => '"Rock 3D serif", serif, sans-serif, "Open Sans"',
			),
			'data_empty_family'                         => array(
				'font_family' => ' ',
				'expected'    => '',
			),
			// Markup in a raw name stays in the name, with CSS escapes for "<" and ";".
			'data_font_family_with_markup'              => array(
				'font_family' => "   Rock      3D</style><script>alert('XSS');</script>\n    ",
				'expected'    => '"Rock      3D\\3c /style\\3e \\3c script\\3e alert(\'XSS\')\\3b \\3c /script\\3e "',
			),
			'data_font_family_with_generic_names'       => array(
				'font_family' => 'generic(kai), generic(fangsong), Rock 3D',
				'expected'    => 'generic(kai), generic(fangsong), "Rock 3D"',
			),

			// Semantic matrix for Trac #63568. The input is CSS unless the key says otherwise.
			'basic name'                                => array(
				'font_family' => 'Inter',
				'expected'    => 'Inter',
			),
			'quoted basic name'                         => array(
				'font_family' => '"Inter"',
				'expected'    => '"Inter"',
			),
			'quoted browser keyword'                    => array(
				'font_family' => '"-webkit-body"',
				'expected'    => '"-webkit-body"',
			),
			'unquoted browser keyword'                  => array(
				'font_family' => '-webkit-body',
				'expected'    => '-webkit-body',
			),
			'quoted Apple system keyword'               => array(
				'font_family' => '"-apple-system"',
				'expected'    => '"-apple-system"',
			),
			'quoted Chromium system keyword'            => array(
				'font_family' => '"BlinkMacSystemFont"',
				'expected'    => '"BlinkMacSystemFont"',
			),
			'escaped browser keyword'                   => array(
				'font_family' => '"\\2d webkit-body"',
				'expected'    => '"-webkit-body"',
			),
			'quoted and unquoted browser keywords'      => array(
				'font_family' => '\'-webkit-body\', -webkit-body',
				'expected'    => '"-webkit-body", -webkit-body',
			),
			'system font keywords'                      => array(
				'font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
				'expected'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			),
			'quoted generic name'                       => array(
				'font_family' => '"serif", serif',
				'expected'    => '"serif", serif',
			),
			'unquoted words'                            => array(
				'font_family' => 'Open Sans',
				'expected'    => '"Open Sans"',
			),
			'apostrophe'                                => array(
				'font_family' => '"O\'Reilly Sans"',
				'expected'    => '"O\'Reilly Sans"',
			),
			'legacy plain apostrophe'                   => array(
				'font_family' => 'O\'Reilly Sans',
				'expected'    => '"O\'Reilly Sans"',
			),
			'double quote'                              => array(
				'font_family' => '\'O"Reilly Sans\'',
				'expected'    => '"O\\"Reilly Sans"',
			),
			'both quote types'                          => array(
				'font_family' => '"O\'Reilly \\"Sans\\""',
				'expected'    => '"O\'Reilly \\"Sans\\""',
			),
			'comma in a name'                           => array(
				'font_family' => '"ACME, Sans", sans-serif',
				'expected'    => '"ACME\\2c  Sans", sans-serif',
			),
			'escaped comma'                             => array(
				'font_family' => 'ACME\\,Sans, serif',
				'expected'    => '"ACME\\2c Sans", serif',
			),
			'ampersand'                                 => array(
				'font_family' => '"Tom & Jerry"',
				'expected'    => '"Tom \\26  Jerry"',
			),
			'short hex escape'                          => array(
				'font_family' => '"Tom \\26  Jerry"',
				'expected'    => '"Tom \\26  Jerry"',
			),
			'six-digit escape'                          => array(
				'font_family' => '"Tom \\000026 Jerry"',
				'expected'    => '"Tom \\26 Jerry"',
			),
			'six-digit escape with a name space'        => array(
				'font_family' => '"Tom \\000026  Jerry"',
				'expected'    => '"Tom \\26  Jerry"',
			),
			'percent sequence'                          => array(
				'font_family' => '"Font 50%AB"',
				'expected'    => '"Font 50%AB"',
			),
			'significant spaces'                        => array(
				'font_family' => '"A  B"',
				'expected'    => '"A  B"',
			),
			'identifier whitespace'                     => array(
				'font_family' => 'A  B',
				'expected'    => '"A B"',
			),
			'numeric name'                              => array(
				'font_family' => '"12345"',
				'expected'    => '"12345"',
			),
			'hyphen and digit'                          => array(
				'font_family' => '"-1 Font"',
				'expected'    => '"-1 Font"',
			),
			'question mark'                             => array(
				'font_family' => '"What?"',
				'expected'    => '"What?"',
			),
			'semicolon in a name'                       => array(
				'font_family' => '"A;B"',
				'expected'    => '"A\\3b B"',
			),
			'braces in a name'                          => array(
				'font_family' => '"A{B}"',
				'expected'    => '"A{B}"',
			),
			'equals sign in a name'                     => array(
				'font_family' => '"A=B"',
				'expected'    => '"A=B"',
			),
			'backslash'                                 => array(
				'font_family' => '"A\\\\B"',
				'expected'    => '"A\\5c B"',
			),
			// wp_kses_no_null() removes a backslash that zeros follow.
			'backslash before a zero'                   => array(
				'font_family' => '"A\\\\0B"',
				'expected'    => '"A\\5c 0B"',
			),
			'escaped quote'                             => array(
				'font_family' => '"O\\22 Reilly Sans"',
				'expected'    => '"O\\"Reilly Sans"',
			),
			'generic distinction'                       => array(
				'font_family' => '"serif", serif',
				'expected'    => '"serif", serif',
			),
			'CSS-wide name'                             => array(
				'font_family' => '"inherit", sans-serif',
				'expected'    => '"inherit", sans-serif',
			),
			'existing generic function'                 => array(
				'font_family' => 'Inter, generic(kai)',
				'expected'    => 'Inter, generic(kai)',
			),
			'unicode'                                   => array(
				'font_family' => '"日本語 😀"',
				'expected'    => '"日本語 😀"',
			),
			'literal angle brackets'                    => array(
				'font_family' => '"A<B>"',
				'expected'    => '"A\\3c B\\3e "',
			),
			'CSS comments'                              => array(
				'font_family' => 'Inter/* comment */, serif',
				'expected'    => 'Inter, serif',
			),
			'CSS-wide keyword alone'                    => array(
				'font_family' => 'inherit',
				'expected'    => 'inherit',
			),
			/*
			 * A CSS-wide keyword is invalid inside a list. The plain name path
			 * reads the part as a font name and returns valid CSS.
			 */
			'CSS-wide keyword inside a list'            => array(
				'font_family' => 'inherit, serif',
				'expected'    => '"inherit", serif',
			),
			'leading and trailing whitespace'           => array(
				'font_family' => "  \n Inter \t ",
				'expected'    => 'Inter',
			),
			'zero as a quoted name'                     => array(
				'font_family' => '"0"',
				'expected'    => '"0"',
			),
			'escaped newline in a string'               => array(
				'font_family' => "\"Tom \\\n Jerry\"",
				'expected'    => '"Tom  Jerry"',
			),
			'escape before hexadecimal characters'      => array(
				'font_family' => '"\\41 BC"',
				'expected'    => '"ABC"',
			),
			'NUL becomes the replacement character'     => array(
				'font_family' => "\"A\0B\"",
				'expected'    => '"A' . "\u{FFFD}" . 'B"',
			),
			'invalid code point escape'                 => array(
				'font_family' => '"A\\110000 B"',
				'expected'    => '"A' . "\u{FFFD}" . 'B"',
			),
			'surrogate escape'                          => array(
				'font_family' => '"A\\d800 B"',
				'expected'    => '"A' . "\u{FFFD}" . 'B"',
			),

			/*
			 * An entry that is not valid CSS is raw text. It becomes one font name,
			 * and the serializer escapes it, so it stays inert.
			 */
			'unterminated string'                       => array(
				'font_family' => '"Inter',
				'expected'    => '"\\"Inter"',
			),
			'unterminated comment'                      => array(
				'font_family' => 'Inter/* comment',
				'expected'    => '"Inter/* comment"',
			),
			'extra token after a quoted family'         => array(
				'font_family' => '"Inter" Sans',
				'expected'    => '"\\"Inter\\" Sans"',
			),
			// Trac #63568: the second attachment of the ticket uses this name.
			'legacy plain double quote'                 => array(
				'font_family' => 'O"Reilly Sans',
				'expected'    => '"O\\"Reilly Sans"',
			),
			'empty list entry'                          => array(
				'font_family' => 'Inter, , serif',
				'expected'    => 'Inter, serif',
			),
			'trailing comma'                            => array(
				'font_family' => 'Inter, ',
				'expected'    => 'Inter',
			),
			'leading comma'                             => array(
				'font_family' => ', Inter',
				'expected'    => 'Inter',
			),
			'only commas'                               => array(
				'font_family' => ' , ',
				'expected'    => '',
			),
			'quoted name with a comma and a plain name' => array(
				'font_family' => '"ACME, Sans", O\'Reilly',
				'expected'    => '"ACME\\2c  Sans", "O\'Reilly"',
			),
			'second declaration'                        => array(
				'font_family' => '"A"; color:red',
				'expected'    => '"\\"A\\"\\3b  color:red"',
			),
			'javascript url'                            => array(
				'font_family' => 'url(javascript:alert(1))',
				'expected'    => '"url(javascript:alert(1))"',
			),
			'expression function'                       => array(
				'font_family' => 'expression(alert(1))',
				'expected'    => '"expression(alert(1))"',
			),
			'rule injection'                            => array(
				'font_family' => 'Inter}body{color:red}',
				'expected'    => '"Inter}body{color:red}"',
			),
			'trailing backslash'                        => array(
				'font_family' => 'Inter\\',
				'expected'    => '"Inter\\5c "',
			),
			// Trac #63568: real font names that are not valid CSS.
			'asterisk'                                  => array(
				'font_family' => 'Bodoni*',
				'expected'    => '"Bodoni*"',
			),
			'parentheses'                               => array(
				'font_family' => 'Font (Display)',
				'expected'    => '"Font (Display)"',
			),

			// Invalid values return an empty string.
			'control character'                         => array(
				'font_family' => "A\x01B",
				'expected'    => '',
			),
			'invalid UTF-8'                             => array(
				'font_family' => "\"A\xC3\x28B\"",
				'expected'    => '',
			),

		);
	}

	/**
	 * A name that contains markup cannot create an HTML element in a style element.
	 *
	 * @ticket 63568
	 */
	public function test_should_escape_angle_brackets_in_a_name() {
		$sanitized = WP_Font_Utils::sanitize_font_family( '"</Style><script>alert(1)</script>"' );

		$this->assertSame( '"\\3c /Style\\3e \\3c script\\3e alert(1)\\3c /script\\3e "', $sanitized );
		$this->assertStringNotContainsString( '<', $sanitized, 'The sanitized value should not contain "<".' );
	}

	/**
	 * Long input and repeated escapes must terminate.
	 *
	 * @ticket 63568
	 */
	public function test_should_handle_long_input() {
		$long = '"' . str_repeat( '\\26 ', 20000 ) . '"';

		$this->assertSame(
			'"' . str_repeat( '\\26 ', 20000 ) . '"',
			WP_Font_Utils::sanitize_font_family( $long )
		);

		$this->assertSame(
			str_repeat( 'A, ', 9999 ) . 'A',
			WP_Font_Utils::sanitize_font_family( str_repeat( 'A,', 9999 ) . 'A' )
		);
	}
}
