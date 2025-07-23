<?php
/**
 * Tests covering WordPress’ RFC2047 (MIME encoding) handling.
 *
 * @package WordPress
 * @group rfc2047
 *
 * @covers ::wp_decode_rfc2047
 */
class Tests_Formatting_wpDecodeRfc2047 extends WP_UnitTestCase {
	/**
	 *
	 * @dataProvider data_rfc2047_strings
	 *
	 * @param string      $encoded
	 * @param string|null $decoded
	 * @return void
	 */
	public function test_decodes_rfc2047_properly( string $encoded, ?string $decoded = null ) {
		$result = wp_decode_rfc2047( $encoded, 'bail-on-error' );

		if ( isset( $decoded ) ) {
			$this->assertSame(
				$decoded,
				$result,
				'Failed to properly decode input text.'
			);
		} else {
			$this->assertNull(
				$result,
				'Improperly decoded invalid input.'
			);
		}
	}

	public static function data_rfc2047_strings() {
		return array(
			'simple_q_encoded_ascii' => array(
				'=?US-ASCII?Q?Keith_Moore?=',
				'Keith Moore'
			),
			'simple_b_encoded_ascii' => array(
				'=?US-ASCII?B?SGVsbG8gV29ybGQ=?=',
				'Hello World'
			),
			'utf8_q_encoded_text' => array(
				'=?UTF-8?Q?Caf=C3=A9?=',
				'Café'
			),
			'utf8_b_encoded_text' => array(
				'=?UTF-8?B?4piVIFN0cmluZw==?=',
				'☕ String'
			),
			'iso_8859_1_q_encoded' => array(
				'=?ISO-8859-1?Q?Andr=E9?=',
				'André'
			),
			'iso_8859_1_b_encoded' => array(
				'=?ISO-8859-1?B?QW5kcuk=?=',
				'André'
			),
			'shift_jis_q_encoded' => array(
				'=?SHIFT_JIS?Q?=93=FA=96=7B=8C=EA?=',
				'日本語'
			),
			'shift_jis_b_encoded' => array(
				'=?SHIFT_JIS?B?k/qWe4zqg2WDWINn?=',
				'日本語テスト'
			),
			'multiple_encodings_in_one' => array(
				'=?UTF-8?Q?Caf=C3=A9?= and =?US-ASCII?B?SGVsbG8=?=',
				'Café and Hello'
			),
			'underscore_to_space_q' => array(
				'=?US-ASCII?Q?Mary_Johnson?=',
				'Mary Johnson'
			),
			'equals_in_q_encoding' => array(
				'=?US-ASCII?Q?foo=3Dbar?=',
				'foo=bar'
			),
			'question_mark_in_q_encoding' => array(
				'=?US-ASCII?Q?What=3F?=',
				'What?'
			),
			'invalid_charset' => array(
				'=?INVALID-CHARSET?Q?Test?=',
				null
			),
			'missing_encoding_type' => array(
				'=?UTF-8?Test?=',
				null
			),
			'invalid_encoding_indicator' => array(
				'=?UTF-8?X?VGVzdA==?=',
				null
			),
			'malformed_b_encoding' => array(
				'=?UTF-8?B?Invalid_Base64???',
				null
			),
			'missing_closing_delimiter' => array(
				'=?UTF-8?Q?Missing_End',
				null
			),
			'empty_encoded_word' => array(
				'=?UTF-8?Q??=',
				''
			),
			'only_equals_signs' => array(
				'=?UTF-8?Q?=3D=3D?=',
				'=='
			),
			'lowercase_q_encoding' => array(
				'=?UTF-8?q?lowercase?=',
				'lowercase'
			),
			'lowercase_b_encoding' => array(
				'=?UTF-8?b?bG93ZXJjYXNl?=',
				'lowercase'
			),
			'mixed_case_encoding' => array(
				'=?UTF-8?Q?Mixed_Case?=',
				'Mixed Case'
			),
			'nested_encoded_words' => array(
				'=?UTF-8?Q?=3F=3F?=',
				'?=?'
			),
			'whitespace_around_encoded' => array(
				' =?UTF-8?Q?Padded?= ',
				' Padded '
			),
			'consecutive_encoded_words' => array(
				'=?UTF-8?Q?First?==?UTF-8?Q?Second?=',
				'FirstSecond'
			),
			'space_between_consecutive_encoded_words' => array(
				'=?UTF-8?Q?First?= =?UTF-8?Q?Second?=',
				'FirstSecond'
			),
			'newline_between_encoded_words' => array(
				"=?UTF-8?Q?First?=\n =?UTF-8?Q?Second?=",
				'FirstSecond'
			),
			'tab_between_encoded_words' => array(
				"=?UTF-8?Q?First?=\t =?UTF-8?Q?Second?=",
				'FirstSecond'
			),
			'long_utf8_string_b' => array(
				'=?UTF-8?B?8J+YgCBUaGlzIGlzIGEgbG9uZyBzdHJpbmcgdGhhdCBzaG91bGQgYmUgZW5jb2RlZCBpbiBCYXNlNjQ=?=',
				'☕ This is a long string that should be encoded in Base64'
			),
			'long_utf8_string_q' => array(
				'=?UTF-8?Q?Long_string_with_=C3=A9_and_=E2=98=95?=',
				'Long string with é and ☕'
			),
			'special_chars_in_q' => array(
				'=?UTF-8?Q?Special_Chars_=28=29_=2B_=2D_=5F?=',
				'Special Chars () + - _'
			),
			'space_at_end_of_q' => array(
				'=?US-ASCII?Q?Space_at_end_?=',
				'Space at end '
			),
			'space_at_beginning_of_q' => array(
				'=?US-ASCII?Q?_Space_at_beginning?=',
				' Space at beginning'
			),
			'only_spaces_in_q' => array(
				'=?US-ASCII?Q?_?=',
				' '
			),
			'control_characters_q' => array(
				'=?US-ASCII?Q?Line1=0ALine2?=',
				"Line1\nLine2"
			),
			'backslash_in_q' => array(
				'=?US-ASCII?Q?Backslash_=5C?=',
				'Backslash \\'
			),
			'percent_sign_in_q' => array(
				'=?US-ASCII?Q?Percent_=25?=',
				'Percent %'
			),
			'dollar_sign_in_q' => array(
				'=?US-ASCII?Q?Dollar_=24?=',
				'Dollar $'
			),
			'at_symbol_in_q' => array(
				'=?US-ASCII?Q?At_=40?=',
				'At @'
			),
			'hash_symbol_in_q' => array(
				'=?US-ASCII?Q?Hash_=23?=',
				'Hash #'
			),
			'ampersand_in_q' => array(
				'=?US-ASCII?Q?Ampersand_=26?=',
				'Ampersand &'
			),
			'asterisk_in_q' => array(
				'=?US-ASCII?Q?Asterisk_=2A?=',
				'Asterisk *'
			),
			'exclamation_in_q' => array(
				'=?US-ASCII?Q?Exclamation_=21?=',
				'Exclamation !'
			),
			'quote_in_q' => array(
				'=?US-ASCII?Q?Quote_=22?=',
				'Quote "'
			),
			'single_quote_in_q' => array(
				'=?US-ASCII?Q?Single_=27?=',
				"Single '"
			),
			'colon_in_q' => array(
				'=?US-ASCII?Q?Colon_=3A?=',
				'Colon :'
			),
			'semicolon_in_q' => array(
				'=?US-ASCII?Q?Semicolon_=3B?=',
				'Semicolon ;'
			),
			'comma_in_q' => array(
				'=?US-ASCII?Q?Comma_=2C?=',
				'Comma ,'
			),
			'period_in_q' => array(
				'=?US-ASCII?Q?Period_=2E?=',
				'Period .'
			),
			'slash_in_q' => array(
				'=?US-ASCII?Q?Slash_=2F?=',
				'Slash /'
			),
			'less_than_in_q' => array(
				'=?US-ASCII?Q?Less_Than_=3C?=',
				'Less Than <'
			),
			'greater_than_in_q' => array(
				'=?US-ASCII?Q?Greater_Than_=3E?=',
				'Greater Than >'
			),
			'brackets_in_q' => array(
				'=?US-ASCII?Q?Brackets_=5B_=5D?=',
				'Brackets [ ]'
			),
			'curly_braces_in_q' => array(
				'=?US-ASCII?Q?Curly_=7B_=7D?=',
				'Curly { }'
			),
			'pipe_in_q' => array(
				'=?US-ASCII?Q?Pipe_=7C?=',
				'Pipe |'
			),
			'tilde_in_q' => array(
				'=?US-ASCII?Q?Tilde_=7E?=',
				'Tilde ~'
			),
			'caret_in_q' => array(
				'=?US-ASCII?Q?Caret_=5E?=',
				'Caret ^'
			),
			'accent_in_q' => array(
				'=?US-ASCII?Q?Accent_=60?=',
				'Accent `'
			),
			'invalid_hex_sequence_q' => array(
				'=?US-ASCII?Q?Invalid=XX?=',
				null
			),
			'incomplete_hex_sequence_q' => array(
				'=?US-ASCII?Q?Incomplete=X?=',
				null
			),
			'hex_sequence_with_lowercase_q' => array(
				'=?US-ASCII?Q?Lowercase_hex=c3=a9?=',
				'Lowercase hexé'
			),
			'non_ascii_in_b_encoding' => array(
				'=?UTF-8?B?8J+YgA==?=',
				'☕'
			),
			'b_encoding_with_whitespace' => array(
				'=?UTF-8?B?SGVsb G8=?=',
				null
			),
			'b_encoding_with_invalid_chars' => array(
				'=?UTF-8?B?SGVsbG8@V29ybGQ=?=',
				null
			),
			'empty_charset' => array(
				'=?UTF-8?Q??=',
				''
			),
			'missing_charset' => array(
				'?Q?Test?=',
				null
			),
			'charset_with_spaces' => array(
				'=? UTF-8 ?Q?Test?=',
				null
			),
			'charset_with_dashes' => array(
				'=?UTF-8-with-dashes?Q?Test?=',
				null
			),
			'multiple_question_marks_in_data' => array(
				'=?US-ASCII?Q?Multiple=3F=3F=3F?=',
				'Multiple???'
			),
			'encoded_word_at_end_of_string' => array(
				'Start =?UTF-8?Q?End?=',
				'Start End'
			),
			'encoded_word_at_beginning_of_string' => array(
				'=?UTF-8?Q?Start?= End',
				'Start End'
			),
			'only_encoded_word' => array(
				'=?UTF-8?Q?Only_Word?=',
				'Only Word'
			),
			'invalid_base64_padding_b' => array(
				'=?UTF-8?B?SGVsbG8=?',
				null
			),
			'extra_equals_in_b_encoding' => array(
				'=?UTF-8?B?SGVsbG8gV29ybGQ====?=',
				null
			),
			'mixed_valid_invalid_encoding' => array(
				'=?UTF-8?Q?Valid?= and =?INVALID?Q?Invalid?=',
				null
			),
			'unclosed_encoded_word' => array(
				'=?UTF-8?Q?Unclosed',
				null
			),
			'unclosed_with_spaces' => array(
				' =?UTF-8?Q?Unclosed ',
				null
			),
			'encoded_word_with_extra_equals' => array(
				'=?UTF-8?Q?Extra=3D?=',
				'Extra='
			),
			'q_encoding_with_line_break' => array(
				"=?US-ASCII?Q?Line1=0ALine2?=",
				"Line1\nLine2"
			),
			'b_encoding_multiline' => array(
				'=?UTF-8?B?VGhpcyBpcyBhIHRlc3QKbXVsdGlsaW5l?=',
				"This is a test\nmultiline"
			),
			'utf8_emoji_b' => array(
				'=?UTF-8?B?4q2QIOKtkA==?=',
				'⭐ ⭐'
			),
			'utf8_emoji_q' => array(
				'=?UTF-8?Q?=F0=9F=98=8A_=F0=9F=98=8A?=',
				'😊 😊'
			),
			'chinese_characters_b' => array(
				'=?UTF-8?B?5Lit5paH5rWL6K+V?=',
				'中文测试'
			),
			'chinese_characters_q' => array(
				'=?UTF-8?Q?=E4=B8=AD=E6=96=87=E6=B5=8B=E8=AF=95?=',
				'中文测试'
			),
			'georgian_characters_b' => array(
				'=?UTF-8?B?4YOQ4YOR4YOS4YOT4YOU4YOV4YOW4YOX4YOY?=',
				'აბგდევზთი'
			),
			'georgian_characters_q' => array(
				'=?UTF-8?Q?=E1=83=90=E1=83=91=E1=83=92=E1=83=93=E1=83=94=E1=83=95=E1=83=96=E1=83=97=E1=83=98?=',
				'აბგდევზთი'
			),
		);
	}
}
