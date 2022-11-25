<?php

/**
 * @group formatting
 *
 * @covers ::wp_parse_str
 */
class Tests_Formatting_wpParseStr extends WP_UnitTestCase {

	/**
	 * Tests parsing of a string into variables.
	 *
	 * Note: While the function under test does not contain any significant logic,
	 * these tests document the behavior and safeguard PHP cross-version compatibility.
	 *
	 * @dataProvider data_wp_parse_str
	 *
	 * @param mixed $input    Value to parse.
	 * @param array $expected Expected function output.
	 */
	public function test_wp_parse_str( $input, $expected ) {
		wp_parse_str( $input, $output );
		$this->assertSame( $expected, $output );
	}

	/**
	 * Data Provider.
	 *
	 * @return array
	 */
	public function data_wp_parse_str() {
		return array(
			'null'              => array(
				'input'    => null,
				'expected' => array(),
			),
			'boolean false'     => array(
				'input'    => false,
				'expected' => array(),
			),
			'boolean true'      => array(
				'input'    => true,
				'expected' => array(
					1 => '',
				),
			),
			'integer 0'         => array(
				'input'    => 0,
				'expected' => array(
					0 => '',
				),
			),
			'integer 456'       => array(
				'input'    => 456,
				'expected' => array(
					456 => '',
				),
			),
			'float 12.53'       => array(
				'input'    => 12.53,
				'expected' => array(
					'12_53' => '',
				),
			),
			'plain string'      => array(
				'input'    => 'foobar',
				'expected' => array(
					'foobar' => '',
				),
			),
			'query string'      => array(
				'input'    => 'x=5&_baba=dudu&',
				'expected' => array(
					'x'     => '5',
					'_baba' => 'dudu',
				),
			),
			'stringable object' => array(
				'input'    => new Fixture_Formatting_wpParseStr(),
				'expected' => array(
					'foobar' => '',
				),
			),
		);
	}

	/**
	 * Tests that the result array only contains the result of the string parsing
	 * when provided with different types of input for the `$output` parameter.
	 *
	 * @dataProvider data_wp_parse_str_result_array_is_always_overwritten
	 *
	 * @param array|null $output   Value for the `$output` parameter.
	 * @param array      $expected Expected function output.
	 */
	public function test_wp_parse_str_result_array_is_always_overwritten( $output, $expected ) {
		wp_parse_str( 'key=25&thing=text', $output );
		$this->assertSame( $expected, $output );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_wp_parse_str_result_array_is_always_overwritten() {
		// Standard value for expected output.
		$expected = array(
			'key'   => '25',
			'thing' => 'text',
		);

		return array(
			'output null'                                 => array(
				'output'   => null,
				'expected' => $expected,
			),
			'output empty array'                          => array(
				'output'   => array(),
				'expected' => $expected,
			),
			'output non empty array, no conflicting keys' => array(
				'output'   => array(
					'foo' => 'bar',
				),
				'expected' => $expected,
			),
			'output non empty array, conflicting keys'    => array(
				'output'   => array(
					'key' => 'value',
				),
				'expected' => $expected,
			),
		);
	}
}

/**
 * Fixture for use in the tests.
 */
class Fixture_Formatting_wpParseStr {
	public function __toString() {
		return 'foobar';
	}
}
