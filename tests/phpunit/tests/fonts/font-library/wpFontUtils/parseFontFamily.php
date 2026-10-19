<?php
/**
 * Test the CSS font family parser and serializer in WP_Font_Utils.
 *
 * @package WordPress
 * @subpackage Font Library
 *
 * @group fonts
 * @group font-library
 *
 * @coversDefaultClass WP_Font_Utils
 */
class Tests_Fonts_WpFontUtils_ParseFontFamily extends WP_UnitTestCase {

	/**
	 * @ticket 63568
	 *
	 * @covers ::parse_font_family_list
	 *
	 * @dataProvider data_parse_list
	 *
	 * @param string $value    CSS font family value.
	 * @param array  $expected Expected parsed entries.
	 */
	public function test_parse_list( $value, $expected ) {
		$this->assertSame( $expected, self::parse_list( $value ) );
	}

	/**
	 * Generic arguments retain their meaning after the parser decodes CSS escapes.
	 *
	 * @ticket 63568
	 * @covers ::parse_font_family_list
	 */
	public function test_parse_list_accepts_generic_arguments() {
		foreach ( array( 'kai', 'fangsong', 'khmer-mul', 'nastaliq' ) as $argument ) {
			$escaped = sprintf( '\\%x ', ord( $argument[0] ) ) . substr( $argument, 1 );
			$entries = self::parse_list( 'GENERIC(/* before */' . $escaped . '/* after */)' );

			$this->assertSame(
				array(
					array(
						'type'  => 'generic',
						'value' => 'generic(' . $argument . ')',
					),
				),
				$entries
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_parse_list() {
		return array(
			'one name'                       => array(
				'value'    => '"Inter"',
				'expected' => array(
					array(
						'type'   => 'name',
						'value'  => 'Inter',
						'quoted' => true,
					),
				),
			),
			'identifier sequence'            => array(
				'value'    => 'Open Sans',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'Open Sans',
					),
				),
			),
			'a name and a generic'           => array(
				'value'    => '"ACME, Sans", sans-serif',
				'expected' => array(
					array(
						'type'   => 'name',
						'value'  => 'ACME, Sans',
						'quoted' => true,
					),
					array(
						'type'  => 'generic',
						'value' => 'sans-serif',
					),
				),
			),
			'a quoted generic is a name'     => array(
				'value'    => '"serif", serif',
				'expected' => array(
					array(
						'type'   => 'name',
						'value'  => 'serif',
						'quoted' => true,
					),
					array(
						'type'  => 'generic',
						'value' => 'serif',
					),
				),
			),
			'a generic keeps its case'       => array(
				'value'    => 'SANS-SERIF',
				'expected' => array(
					array(
						'type'  => 'generic',
						'value' => 'sans-serif',
					),
				),
			),
			'the generic function'           => array(
				'value'    => 'generic(kai)',
				'expected' => array(
					array(
						'type'  => 'generic',
						'value' => 'generic(kai)',
					),
				),
			),
			'a CSS-wide keyword alone'       => array(
				'value'    => 'inherit',
				'expected' => array(
					array(
						'type'  => 'keyword',
						'value' => 'inherit',
					),
				),
			),
			'an escape inside an identifier' => array(
				'value'    => 'ACME\\,Sans',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'ACME,Sans',
					),
				),
			),
			'a comment between entries'      => array(
				'value'    => 'Inter/* comment */, serif',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'Inter',
					),
					array(
						'type'  => 'generic',
						'value' => 'serif',
					),
				),
			),
		);
	}

	/**
	 * The parser must reject invalid syntax and must not accept a valid prefix.
	 *
	 * @ticket 63568
	 *
	 * @covers ::is_valid_css_font_family
	 *
	 * @dataProvider data_parse_list_rejects
	 *
	 * @param string $value CSS font family value.
	 */
	public function test_parse_list_rejects( $value ) {
		$this->assertFalse( WP_Font_Utils::is_valid_css_font_family( $value ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_parse_list_rejects() {
		return array(
			'empty value'                  => array( '' ),
			'whitespace only'              => array( "  \n\t " ),
			'unterminated string'          => array( '"Inter' ),
			'unterminated single quote'    => array( "'Inter" ),
			'unterminated comment'         => array( 'Inter/* comment' ),
			'a newline inside a string'    => array( "\"In\nter\"" ),
			'extra token after a string'   => array( '"Inter" Sans' ),
			'a string after an identifier' => array( 'Inter "Sans"' ),
			'empty list entry'             => array( 'Inter, , serif' ),
			'trailing comma'               => array( 'Inter, ' ),
			'leading comma'                => array( ', Inter' ),
			'a second declaration'         => array( '"Inter"; color:red' ),
			'a rule after the value'       => array( 'Inter}body{color:red}' ),
			'a url function'               => array( 'url(javascript:alert(1))' ),
			'an expression function'       => array( 'expression(alert(1))' ),
			'a var function'               => array( 'var(--font)' ),
			'an unquoted digit start'      => array( '12345' ),
			'an unquoted hyphen digit'     => array( '-1 Font' ),
			'a plain apostrophe name'      => array( "O'Reilly Sans" ),
			'a trailing backslash'         => array( 'Inter\\' ),
			'an unknown generic function'  => array( 'generic(font[name])' ),
			'an unknown generic argument'  => array( 'generic(unknown)' ),
			'an escaped generic delimiter' => array( 'generic(\\29\\3b color\\3a red)' ),
			'an escaped generic comment'   => array( 'generic(\\29\\3b color\\3a red\\3b\\2f\\2a)' ),
			'a keyword inside a list'      => array( 'inherit, serif' ),
			'invalid UTF-8'                => array( "\"A\xC3\x28B\"" ),
			'an at-rule'                   => array( '@import url(x)' ),
		);
	}

	/**
	 * The plain name path accepts values that earlier WordPress versions accepted.
	 *
	 * @ticket 63568
	 *
	 * @covers ::parse_font_family_list
	 *
	 * @dataProvider data_parse_list_with_plain_names
	 *
	 * @param string     $value    CSS font family value or plain name.
	 * @param array|null $expected Expected parsed entries.
	 */
	public function test_parse_list_with_plain_names( $value, $expected ) {
		$this->assertSame( $expected, self::parse_list( $value, true ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_parse_list_with_plain_names() {
		return array(
			'the original apostrophe case'         => array(
				'value'    => "O'Reilly Sans",
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => "O'Reilly Sans",
					),
				),
			),
			'a plain name inside a list'           => array(
				'value'    => "Arial, O'Reilly Sans, serif",
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'Arial',
					),
					array(
						'type'  => 'name',
						'value' => "O'Reilly Sans",
					),
					array(
						'type'  => 'generic',
						'value' => 'serif',
					),
				),
			),
			'a name that starts with a digit'      => array(
				'value'    => '12345',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => '12345',
					),
				),
			),
			'a percent sequence'                   => array(
				'value'    => 'Font 50%AB',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'Font 50%AB',
					),
				),
			),
			// Raw text that is not valid CSS is one plain name. The serializer escapes it.
			'a second declaration is a plain name' => array(
				'value'    => '"A"; color:red',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => '"A"; color:red',
					),
				),
			),
			'a url function is a plain name'       => array(
				'value'    => 'url(javascript:alert(1))',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'url(javascript:alert(1))',
					),
				),
			),
			'markup is a plain name'               => array(
				'value'    => '</style><script>alert(1)</script>',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => '</style><script>alert(1)</script>',
					),
				),
			),
			'a trailing backslash is a plain name' => array(
				'value'    => 'Inter\\',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'Inter\\',
					),
				),
			),
			'an asterisk is a plain name'          => array(
				'value'    => 'Bodoni*',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'Bodoni*',
					),
				),
			),
			'a control character is an error'      => array(
				'value'    => "A\x01B",
				'expected' => null,
			),
			'a double quote inside a plain name'   => array(
				'value'    => 'O"Reilly Sans',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => 'O"Reilly Sans',
					),
				),
			),
			'a value that starts with a quote is a plain name' => array(
				'value'    => '"Inter',
				'expected' => array(
					array(
						'type'  => 'name',
						'value' => '"Inter',
					),
				),
			),
		);
	}

	/**
	 * Core accepts the escaped CSS string that a font upload client sends.
	 *
	 * The name comes from the Gutenberg font upload test font. Core must not
	 * require that client, but it must read its value without loss.
	 *
	 * @ticket 63568
	 *
	 * @covers ::parse_font_family_list
	 */
	public function test_parse_list_reads_an_escaped_css_string() {
		$css     = '"\\22 Ephesis\\22  font with \\3C special \\5C \\3E  {chars} \\26  things\\2C  ya\'know?"';
		$entries = self::parse_list( $css );

		$this->assertIsArray( $entries, 'The escaped CSS string should be valid.' );
		$this->assertSame(
			'"Ephesis" font with <special \\> {chars} & things, ya\'know?',
			$entries[0]['value'],
			'The decoded name should keep every character and every space.'
		);
	}

	/**
	 * Equivalent CSS escape forms must produce the same decoded name.
	 *
	 * @ticket 63568
	 *
	 * @covers ::parse_font_family_list
	 *
	 * @dataProvider data_equivalent_escapes
	 *
	 * @param string[] $values   Equivalent CSS values.
	 * @param string   $expected Expected decoded name.
	 */
	public function test_equivalent_escapes( $values, $expected ) {
		foreach ( $values as $value ) {
			$entries = self::parse_list( $value );

			$this->assertIsArray( $entries, "The value $value should be valid." );
			$this->assertSame( $expected, $entries[0]['value'], "The value $value should decode to $expected." );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_equivalent_escapes() {
		return array(
			'an ampersand'     => array(
				'values'   => array( '"Tom \\26  Jerry"', '"Tom \\000026  Jerry"', '"Tom & Jerry"' ),
				'expected' => 'Tom & Jerry',
			),
			'a double quote'   => array(
				'values'   => array( '"O\\22 Reilly"', "'O\"Reilly'", '"O\\000022 Reilly"' ),
				'expected' => 'O"Reilly',
			),
			'a comma'          => array(
				'values'   => array( 'ACME\\,Sans', '"ACME,Sans"', '"ACME\\2c Sans"' ),
				'expected' => 'ACME,Sans',
			),
			'plain characters' => array(
				'values'   => array( '"\\41 BC"', '"ABC"', 'ABC', '\\41 BC' ),
				'expected' => 'ABC',
			),
		);
	}

	/**
	 * The serializer must produce a value that decodes to the same name.
	 *
	 * @ticket 63568
	 *
	 * @covers ::serialize_font_family_name
	 * @covers ::parse_font_family_list
	 *
	 * @dataProvider data_serialize_name_round_trip
	 *
	 * @param string $name Decoded font name.
	 */
	public function test_serialize_name_round_trip( $name ) {
		$css     = self::serialize_name( $name );
		$entries = self::parse_list( $css );

		$this->assertIsArray( $entries, "The serialized value $css should be valid CSS." );
		$this->assertCount( 1, $entries, 'The serialized value should hold one entry.' );
		$this->assertSame( 'name', $entries[0]['type'], 'The entry should be a name.' );
		$this->assertSame( $name, $entries[0]['value'], 'The decoded name should not change.' );
		$this->assertSame( $entries, self::parse_list( self::call_font_utils( 'serialize_font_family_list', $entries ) ), 'The list serializer should keep the name.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_serialize_name_round_trip() {
		return array(
			'a plain name'           => array( 'Inter' ),
			'a browser keyword'      => array( '-webkit-body' ),
			'an Apple keyword'       => array( '-apple-system' ),
			'a Chromium keyword'     => array( 'BlinkMacSystemFont' ),
			'a name with spaces'     => array( 'Open Sans' ),
			'an apostrophe'          => array( "O'Reilly Sans" ),
			'a double quote'         => array( 'O"Reilly Sans' ),
			'a comma'                => array( 'ACME, Sans' ),
			'an ampersand'           => array( 'Tom & Jerry' ),
			'a percent sequence'     => array( 'Font 50%AB' ),
			'two spaces'             => array( 'A  B' ),
			'digits only'            => array( '12345' ),
			'a hyphen and digit'     => array( '-1 Font' ),
			'a semicolon'            => array( 'A;B' ),
			'braces'                 => array( 'A{B}' ),
			'an equals sign'         => array( 'A=B' ),
			'a backslash'            => array( 'A\\B' ),
			'a backslash and a zero' => array( 'A\\0B' ),
			'a generic name'         => array( 'serif' ),
			'a CSS-wide keyword'     => array( 'inherit' ),
			'unicode'                => array( '日本語 😀' ),
			'angle brackets'         => array( 'A<B>' ),
			'markup'                 => array( '</style><script>alert(1)</script>' ),
			'a tab'                  => array( "tab\there" ),
			'a newline'              => array( "A\nB" ),
			'a delete character'     => array( "A\x7fB" ),
			'zero'                   => array( '0' ),
			'leading whitespace'     => array( ' leading' ),
			'trailing whitespace'    => array( 'trailing ' ),
		);
	}

	/**
	 * A serialized name must survive the CSS filter of a style attribute.
	 *
	 * @ticket 63568
	 *
	 * @covers ::serialize_font_family_name
	 *
	 * @dataProvider data_serialize_name_round_trip
	 *
	 * @param string $name Decoded font name.
	 */
	public function test_serialize_name_survives_safecss_filter_attr( $name ) {
		$css      = self::serialize_name( $name );
		$filtered = safecss_filter_attr( 'font-family: ' . $css );

		$this->assertSame( 'font-family: ' . $css, $filtered, 'The CSS filter should not change the value.' );

		$entries = self::parse_list( substr( $filtered, strlen( 'font-family: ' ) ) );

		$this->assertIsArray( $entries, 'The filtered value should still be valid CSS.' );
		$this->assertSame( $name, $entries[0]['value'], 'The decoded name should not change.' );
	}

	/**
	 * The serializer must not write a character that can close a style element.
	 *
	 * @ticket 63568
	 *
	 * @covers ::serialize_font_family_name
	 */
	public function test_serialize_name_escapes_angle_bracket() {
		$css = self::serialize_name( '</STYLE><script>alert(1)</script>' );

		$this->assertStringNotContainsString( '<', $css );
		$this->assertSame( '"\\3c /STYLE\\3e \\3c script\\3e alert(1)\\3c /script\\3e "', $css );
	}

	/**
	 * The serializer must not write a literal comma, because some clients
	 * split a font family list at each comma.
	 *
	 * @ticket 63568
	 *
	 * @covers ::serialize_font_family_name
	 */
	public function test_serialize_name_escapes_comma() {
		$css = self::serialize_name( 'ACME, Sans' );

		$this->assertStringNotContainsString( ',', $css );
		$this->assertSame( '"ACME\\2c  Sans"', $css );
	}

	/**
	 * The descriptor must name one family.
	 *
	 * @ticket 63568
	 *
	 * @covers ::get_font_face_family
	 */
	public function test_get_font_face_family_selects_the_first_family() {
		$this->assertSame( '"ACME\\2c  Sans"', WP_Font_Utils::get_font_face_family( '"ACME, Sans", sans-serif' ) );
		$this->assertSame( '"Inter"', WP_Font_Utils::get_font_face_family( 'Inter, serif' ) );
		$this->assertSame( '"O\'Reilly Sans"', WP_Font_Utils::get_font_face_family( "O'Reilly Sans" ) );
		$this->assertSame( '', WP_Font_Utils::get_font_face_family( 'inherit' ) );
		$this->assertSame( '"\\"A\\"\\3b  color:red"', WP_Font_Utils::get_font_face_family( '"A"; color:red' ) );
		$this->assertSame( '', WP_Font_Utils::get_font_face_family( "A\x01B" ) );
		$this->assertSame( '', WP_Font_Utils::get_font_face_family( '""' ) );
	}

	/**
	 * Long input and repeated escapes must terminate.
	 *
	 * @ticket 63568
	 *
	 * @covers ::parse_font_family_list
	 */
	public function test_parse_list_handles_long_input() {
		$entries = self::parse_list( '"' . str_repeat( '\\26 ', 20000 ) . '"' );
		$this->assertIsArray( $entries );
		$this->assertSame( str_repeat( '&', 20000 ), $entries[0]['value'] );

		$entries = self::parse_list( str_repeat( 'A,', 20000 ) . 'A' );
		$this->assertIsArray( $entries );
		$this->assertCount( 20001, $entries );

		$entries = self::parse_list( str_repeat( '/*x*/', 20000 ) . 'A' );
		$this->assertIsArray( $entries );
		$this->assertCount( 1, $entries );
	}

	/**
	 * Calls the private font family parser.
	 *
	 * @param string $value             CSS font family value.
	 * @param bool   $allow_plain_names Whether to accept plain names.
	 * @return array[]|null Parsed entries, or null if the value is invalid.
	 */
	private static function parse_list( $value, $allow_plain_names = false ) {
		return self::call_font_utils( 'parse_font_family_list', $value, $allow_plain_names );
	}

	/**
	 * Calls the private font name serializer.
	 *
	 * @param string $name Decoded font name.
	 * @return string The name as a quoted CSS string.
	 */
	private static function serialize_name( $name ) {
		return self::call_font_utils( 'serialize_font_family_name', $name );
	}

	/**
	 * Calls a private method of WP_Font_Utils.
	 *
	 * @param string $name    Method name.
	 * @param mixed  ...$args Method arguments.
	 * @return mixed The return value of the method.
	 */
	private static function call_font_utils( $name, ...$args ) {
		$method = new ReflectionMethod( 'WP_Font_Utils', $name );
		$method->setAccessible( true );

		return $method->invokeArgs( null, $args );
	}
}
