<?php

/**
 * Test WPDB _real_escape() method.
 *
 * @group wpdb
 *
 * @covers wpdb::_real_escape
 */
class Tests_DB_RealEscape extends WP_UnitTestCase {

	/**
	 * Test that various types of input passed to `wpdb::_real_escape()` are handled correctly.
	 *
	 * Note: this test does not test the actual escaping or other logic in the function.
	 * It just and only tests and documents how the function handles various input types.
	 *
	 * @ticket 53635
	 *
	 * @dataProvider data_real_escape_input_type_handling
	 *
	 * @param mixed  $input    The input to escape.
	 * @param string $expected The expected function output.
	 */
	public function test_real_escape_input_type_handling( $input, $expected ) {
		global $wpdb;

		$this->assertSame( $expected, $wpdb->_real_escape( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @var array
	 */
	public static function data_real_escape_input_type_handling() {
		return array(
			'null'             => array(
				'input'    => null,
				'expected' => '',
			),
			'boolean false'    => array(
				'input'    => false,
				'expected' => '',
			),
			'boolean true'     => array(
				'input'    => true,
				'expected' => '1',
			),
			'integer zero'     => array(
				'input'    => 0,
				'expected' => '0',
			),
			'negative integer' => array(
				'input'    => -1327,
				'expected' => '-1327',
			),
			'positive integer' => array(
				'input'    => 47896,
				'expected' => '47896',
			),
			'float zero'       => array(
				'input'    => 0.0,
				'expected' => '0',
			),
			'positive float'   => array(
				'input'    => 25.52,
				'expected' => '25.52',
			),
			'simple string'    => array(
				'input'    => 'foobar',
				'expected' => 'foobar',
			),
			'empty array'      => array(
				'input'    => array(),
				'expected' => '',
			),
			'non-empty array'  => array(
				'input'    => array( 1, 2, 3 ),
				'expected' => '',
			),
			'simple object'    => array(
				'input'    => new stdClass(),
				'expected' => '',
			),
		);
	}

	/**
	 * Tests that special characters are escaped like mysqli_real_escape_string() escapes them.
	 *
	 * This ensures that all database engines escape strings the same way.
	 *
	 * @dataProvider data_real_escape_special_characters
	 *
	 * @param string $input    The input to escape.
	 * @param string $expected The expected function output.
	 */
	public function test_real_escape_special_characters( $input, $expected ) {
		global $wpdb;

		$this->assertSame( $expected, $wpdb->_real_escape( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_real_escape_special_characters() {
		return array(
			'NUL character'          => array(
				'input'    => "a\0b",
				'expected' => 'a\0b',
			),
			'newline'                => array(
				'input'    => "a\nb",
				'expected' => 'a\nb',
			),
			'carriage return'        => array(
				'input'    => "a\rb",
				'expected' => 'a\rb',
			),
			'backslash'              => array(
				'input'    => 'a\b',
				'expected' => 'a\\\\b',
			),
			'single quote'           => array(
				'input'    => "a'b",
				'expected' => "a\\'b",
			),
			'double quote'           => array(
				'input'    => 'a"b',
				'expected' => 'a\"b',
			),
			'Control+Z character'    => array(
				'input'    => "a\x1ab",
				'expected' => 'a\Zb',
			),
			'multibyte characters'   => array(
				'input'    => "\u{017E}lu\u{0165}ou\u{010D}k\u{00FD} k\u{016F}\u{0148} \u{1F600}",
				'expected' => "\u{017E}lu\u{0165}ou\u{010D}k\u{00FD} k\u{016F}\u{0148} \u{1F600}",
			),
			'all special characters' => array(
				'input'    => "\0\n\r\\'\"\x1a",
				'expected' => '\0\n\r\\\\\\\'\"\Z',
			),
		);
	}
}
