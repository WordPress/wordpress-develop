<?php

/**
 * @group compat
 * @group security-153
 *
 * @covers ::mb_substr
 * @covers ::_mb_substr
 */
class Tests_Compat_mbSubstr extends WP_UnitTestCase {

	/**
	 * Test that mb_substr() is always available (either from PHP or WP).
	 */
	public function test_mb_substr_availability() {
		$this->assertTrue( function_exists( 'mb_substr' ) );
	}

	/**
	 * @dataProvider data_utf8_substrings
	 */
	public function test_mb_substr( $input_string, $start, $length, $expected_character_substring ) {
		$this->assertSame( $expected_character_substring, _mb_substr( $input_string, $start, $length, 'UTF-8' ) );
	}

	/**
	 * @dataProvider data_utf8_substrings
	 */
	public function test_mb_substr_via_regex( $input_string, $start, $length, $expected_character_substring ) {
		_wp_can_use_pcre_u( false );
		$this->assertSame( $expected_character_substring, _mb_substr( $input_string, $start, $length, 'UTF-8' ) );
		_wp_can_use_pcre_u( 'reset' );
	}

	/**
	 * @dataProvider data_utf8_substrings
	 */
	public function test_8bit_mb_substr( $input_string, $start, $length, $expected_character_substring, $expected_byte_substring ) {
		$this->assertSame( $expected_byte_substring, _mb_substr( $input_string, $start, $length, '8bit' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_utf8_substrings() {
		return array(
			array(
				'input_string'                 => 'баба',
				'start'                        => 0,
				'length'                       => 3,
				'expected_character_substring' => 'баб',
				'expected_byte_substring'      => "б\xD0",
			),
			array(
				'input_string'                 => 'баба',
				'start'                        => 0,
				'length'                       => -1,
				'expected_character_substring' => 'баб',
				'expected_byte_substring'      => "баб\xD0",
			),
			array(
				'input_string'                 => 'баба',
				'start'                        => 1,
				'length'                       => null,
				'expected_character_substring' => 'аба',
				'expected_byte_substring'      => "\xB1аба",
			),
			array(
				'input_string'                 => 'баба',
				'start'                        => -3,
				'length'                       => null,
				'expected_character_substring' => 'аба',
				'expected_byte_substring'      => "\xB1а",
			),
			array(
				'input_string'                 => 'баба',
				'start'                        => -3,
				'length'                       => 2,
				'expected_character_substring' => 'аб',
				'expected_byte_substring'      => "\xB1\xD0",
			),
			array(
				'input_string'                 => 'баба',
				'start'                        => -1,
				'length'                       => 2,
				'expected_character_substring' => 'а',
				'expected_byte_substring'      => "\xB0",
			),
			array(
				'input_string'                 => 'I am your баба',
				'start'                        => 0,
				'length'                       => 11,
				'expected_character_substring' => 'I am your б',
				'expected_byte_substring'      => "I am your \xD0",
			),
		);
	}

	/**
	 * @link https://github.com/php/php-src/blob/php-5.6.8/ext/mbstring/tests/mb_substr_basic.phpt
	 */
	public function test_mb_substr_phpcore_basic() {
		$string_ascii = 'ABCDEF';
		$string_mb    = base64_decode( '5pel5pys6Kqe44OG44Kt44K544OI44Gn44GZ44CCMDEyMzTvvJXvvJbvvJfvvJjvvJnjgII=' );

		$this->assertSame(
			'DEF',
			_mb_substr( $string_ascii, 3 ),
			'Substring does not match expected for offset 3'
		);
		$this->assertSame(
			'DEF',
			_mb_substr( $string_ascii, 3, 5, 'ISO-8859-1' ),
			'Substring does not match expected for offset 3, length 5, with iso charset'
		);

		// Specific latin-1 as that is the default the core PHP test operates under.
		$this->assertSame(
			'peacrOiqng==',
			base64_encode( _mb_substr( $string_mb, 2, 7, 'latin-1' ) ),
			'Substring does not match expected for offset 2, length 7, with latin-1 charset'
		);
		$this->assertSame(
			'6Kqe44OG44Kt44K544OI44Gn44GZ',
			base64_encode( _mb_substr( $string_mb, 2, 7, 'utf-8' ) ),
			'Substring does not match expected for offset 2, length 7, with utf-8 charset'
		);
	}

	/**
	 * @link https://github.com/php/php-src/blob/php-5.6.8/ext/mbstring/tests/mb_substr_variation1.phpt
	 *
	 * @dataProvider data_mb_substr_phpcore_input_type_handling
	 *
	 * @param mixed  $input    Input to pass to the function.
	 * @param string $expected Expected function output.
	 */
	public function test_mb_substr_phpcore_input_type_handling( $input, $expected ) {
		$start  = 0;
		$length = 5;

		$this->assertSame( $expected, _mb_substr( $input, $start, $length ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_mb_substr_phpcore_input_type_handling() {
		$heredoc = <<<EOT
hello world
EOT;

		return array(
			'integer zero'                   => array(
				'input'    => 0,
				'expected' => '0',
			),
			'integer 1'                      => array(
				'input'    => 1,
				'expected' => '1',
			),
			'positive integer'               => array(
				'input'    => 12345,
				'expected' => '12345',
			),
			'negative integer'               => array(
				'input'    => -2345,
				'expected' => '-2345',
			),
			// Float data.
			'positive float with fraction'   => array(
				'input'    => 10.5,
				'expected' => '10.5',
			),
			'negative float with fraction'   => array(
				'input'    => -10.5,
				'expected' => '-10.5',
			),
			'float scientific whole number'  => array(
				'input'    => 12.3456789000e10,
				'expected' => '12345',
			),
			'float scientific with fraction' => array(
				'input'    => 12.3456789000E-10,
				'expected' => '1.234',
			),
			'float, fraction only'           => array(
				'input'    => .5,
				'expected' => '0.5',
			),
			// Null data.
			'null'                           => array(
				'input'    => null,
				'expected' => '',
			),
			// Boolean data.
			'boolean true'                   => array(
				'input'    => true,
				'expected' => '1',
			),
			'boolean false'                  => array(
				'input'    => false,
				'expected' => '',
			),
			// Empty data.
			'empty string'                   => array(
				'input'    => '',
				'expected' => '',
			),
			// String data.
			'double quoted string'           => array(
				'input'    => "string'",
				'expected' => 'strin',
			),
			'single quoted string'           => array(
				'input'    => 'string',
				'expected' => 'strin',
			),
			'heredoc string'                 => array(
				'input'    => $heredoc,
				'expected' => 'hello',
			),
			// Object data.
			'object with __toString method'  => array(
				'input'    => new ClassWithToStringForMbSubstr(),
				'expected' => 'Class',
			),
		);
	}
}

/* used in data_mb_substr_phpcore_input_type_handling() */
class ClassWithToStringForMbSubstr {
	public function __toString() {
		return 'Class object';
	}
}
