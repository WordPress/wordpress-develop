<?php

/**
 * @group compat
 * @group security-153
 */
class Tests_Compat_mbSubstr extends WP_UnitTestCase {

	function utf8_substrings() {
		return array(
			// String, start, length, character_substring, byte_substring.
			array( 'баба', 0, 3, 'баб', "б\xD0" ),
			array( 'баба', 0, -1, 'баб', "баб\xD0" ),
			array( 'баба', 1, null, 'аба', "\xB1аба" ),
			array( 'баба', -3, null, 'аба', "\xB1а" ),
			array( 'баба', -3, 2, 'аб', "\xB1\xD0" ),
			array( 'баба', -1, 2, 'а', "\xB0" ),
			array( 'I am your баба', 0, 11, 'I am your б', "I am your \xD0" ),
		);
	}

	/**
	 * @dataProvider utf8_substrings
	 */
	function test_mb_substr( $string, $start, $length, $expected_character_substring ) {
		$this->assertSame( $expected_character_substring, _mb_substr( $string, $start, $length, 'UTF-8' ) );
	}

	/**
	 * @dataProvider utf8_substrings
	 */
	function test_mb_substr_via_regex( $string, $start, $length, $expected_character_substring ) {
		_wp_can_use_pcre_u( false );
		$this->assertSame( $expected_character_substring, _mb_substr( $string, $start, $length, 'UTF-8' ) );
		_wp_can_use_pcre_u( 'reset' );
	}

	/**
	 * @dataProvider utf8_substrings
	 */
	function test_8bit_mb_substr( $string, $start, $length, $expected_character_substring, $expected_byte_substring ) {
		$this->assertSame( $expected_byte_substring, _mb_substr( $string, $start, $length, '8bit' ) );
	}

	function test_mb_substr_phpcore() {
		/* https://github.com/php/php-src/blob/php-5.6.8/ext/mbstring/tests/mb_substr_basic.phpt */
		$string_ascii = 'ABCDEF';
		$string_mb    = base64_decode( '5pel5pys6Kqe44OG44Kt44K544OI44Gn44GZ44CCMDEyMzTvvJXvvJbvvJfvvJjvvJnjgII=' );

		$this->assertSame( 'DEF', _mb_substr( $string_ascii, 3 ) );
		$this->assertSame( 'DEF', _mb_substr( $string_ascii, 3, 5, 'ISO-8859-1' ) );

		// Specific latin-1 as that is the default the core PHP test operates under.
		$this->assertSame( 'peacrOiqng==', base64_encode( _mb_substr( $string_mb, 2, 7, 'latin-1' ) ) );
		$this->assertSame( '6Kqe44OG44Kt44K544OI44Gn44GZ', base64_encode( _mb_substr( $string_mb, 2, 7, 'utf-8' ) ) );

		/* https://github.com/php/php-src/blob/php-5.6.8/ext/mbstring/tests/mb_substr_variation1.phpt */
		$start     = 0;
		$length    = 5;
		$unset_var = 10;
		unset( $unset_var );
		$heredoc  = <<<EOT
hello world
EOT;
		$inputs   = array(
			0,
			1,
			12345,
			-2345,
			// Float data.
			10.5,
			-10.5,
			12.3456789000e10,
			12.3456789000E-10,
			.5,
			// Null data.
			null,
			null,
			// Boolean data.
			true,
			false,
			true,
			false,
			// Empty data.
			'',
			'',
			// String data.
			'string',
			'string',
			$heredoc,
			// Object data.
			new ClassA(),
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- intentionally undefined data
			@$undefined_var,
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- intentionally unset data
			@$unset_var,
		);
		$outputs  = array(
			'0',
			'1',
			'12345',
			'-2345',
			'10.5',
			'-10.5',
			'12345',
			'1.234',
			'0.5',
			'',
			'',
			'1',
			'',
			'1',
			'',
			'',
			'',
			'strin',
			'strin',
			'hello',
			'Class',
			'',
			'',
		);
		$iterator = 0;
		foreach ( $inputs as $input ) {
			$this->assertSame( $outputs[ $iterator ], _mb_substr( $input, $start, $length ) );
			$iterator++;
		}

	}
}

/* used in test_mb_substr_phpcore */
class ClassA {
	public function __toString() {
		return 'Class A object';
	}
}
